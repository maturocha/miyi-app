<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Order_details;
use App\Promotion;
use App\Product;

class OrderDetailsUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'id_product' => 'sometimes|required|integer|exists:products,id',
            'promotion_id' => 'nullable|integer|exists:promotions,id',
            'quantity' => 'sometimes|required|numeric|min:0.01',
            'discount' => 'nullable|numeric|min:0|max:100',
            'price_unit' => 'sometimes|required|numeric|min:0.01',
            'weight' => 'nullable|numeric|min:0.01|regex:/^\d+(\.\d{1,2})?$/',
        ];

        // Validaciones adicionales si se especifica una promoción
        if ($this->input('promotion_id')) {
            $rules['promotion_id'] .= '|promotion_valid_for_product';
            $rules['discount'] .= '|discount_matches_promotion';
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        // Validar weight cuando type_product = 'w'
        $validator->after(function ($validator) {
            $productId = $this->input('id_product');
            
            // Si no se envía id_product, obtenerlo del detalle existente
            if (!$productId && $this->route('detail')) {
                $detail = $this->route('detail');
                $productId = $detail->id_product;
            }
            
            if ($productId) {
                $product = Product::find($productId);
                
                if ($product && $product->type_product === 'w') {
                    $weight = $this->input('weight');
                    
                    // Si no se envía weight, obtenerlo del detalle existente
                    if (!$weight && $this->route('detail')) {
                        $detail = $this->route('detail');
                        $weight = $detail->weight;
                    }
                    
                    if (!$weight || $weight <= 0) {
                        $validator->errors()->add('weight', 'El campo weight es requerido y debe ser mayor a 0 para productos tipo peso.');
                    }
                }
            }
        });

        $validator->addExtension('promotion_valid_for_product', function ($attribute, $value, $parameters, $validator) {
            $promotion = Promotion::find($value);
            $productId = $this->input('id_product');
            
            // Si no se envía id_product, obtenerlo del detalle existente
            if (!$productId && $this->route('detail')) {
                $detail = $this->route('detail');
                $productId = $detail->id_product;
            }
            
            if (
                !$promotion ||
                !$promotion->is_active ||
                ($promotion->starts_at && $promotion->starts_at->toDateString() > now()->toDateString()) ||
                ($promotion->ends_at && $promotion->ends_at->toDateString() < now()->toDateString())
            ) {
                return false;
            }
            
            return $promotion->products()->where('products.id', $productId)->exists();
        });

        $validator->addReplacer('promotion_valid_for_product', function ($message, $attribute, $rule, $parameters) {
            return 'La promoción seleccionada no es válida para este producto o no está activa.';
        });

        $validator->addExtension('discount_matches_promotion', function ($attribute, $value, $parameters, $validator) {
            $promotionId = $this->input('promotion_id');
            $promotion = Promotion::find($promotionId);
            $frontendDiscount = $this->input('discount', 0);
            
            if (!$promotion) {
                return false;
            }
            
            // Para tipos que calculan price_final directamente, el discount debe ser 0
            if (in_array($promotion->type, [Promotion::BUY_X_GET_Y, Promotion::NTH_PERCENT])) {
                return $frontendDiscount == 0;
            }
            
            // Para tipos que modifican el discount, validar que sea razonable
            if (in_array($promotion->type, [Promotion::LINE_PERCENT, Promotion::BUY_X_TOTAL_DISCOUNT])) {
                $expectedDiscount = $this->calculateExpectedDiscount($promotion);
                $tolerance = 0.01; // Tolerancia de 0.01% para diferencias de redondeo
                
                return abs($frontendDiscount - $expectedDiscount) <= $tolerance;
            }
            
            return true;
        });

        $validator->addReplacer('discount_matches_promotion', function ($message, $attribute, $rule, $parameters) {
            return 'El descuento enviado no coincide con el esperado para la promoción aplicada.';
        });
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'discount' => $this->input('discount', 0),
        ]);
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Obtener el detalle existente para valores por defecto
        $detail = $this->route('detail');
        
        // Si no se envía id_product, usar el del detalle existente
        if (!isset($validated['id_product']) && $detail) {
            $validated['id_product'] = $detail->id_product;
        }
        
        // Si no se envía quantity, usar el del detalle existente
        if (!isset($validated['quantity']) && $detail) {
            $validated['quantity'] = $detail->quantity;
        }
        
        // Si no se envía price_unit, usar el del detalle existente
        if (!isset($validated['price_unit']) && $detail) {
            $validated['price_unit'] = $detail->price_unit;
        }
        
        // Si no se envía weight y el detalle tiene weight, usar el del detalle existente
        if (!isset($validated['weight']) && $detail && $detail->weight) {
            $validated['weight'] = $detail->weight;
        }
        
        // Obtener el producto para verificar type_product
        $product = Product::find($validated['id_product']);
        $isWeightProduct = $product && $product->type_product === 'w';
        
        // Determinar la cantidad a usar para cálculos (weight si es producto por peso, quantity si no)
        $calculationQuantity = $isWeightProduct && isset($validated['weight']) && $validated['weight'] > 0
            ? $validated['weight']
            : $validated['quantity'];
        
        if (isset($validated['promotion_id']) && $validated['promotion_id']) {
            $promotion = Promotion::find($validated['promotion_id']);
            
            if ($promotion && $product) {
                // Guardar snapshot completo de la promoción como JSON
                $validated['promotion_snapshot'] = [
                    'id' => $promotion->id,
                    'name' => $promotion->name,
                    'type' => $promotion->type,
                    'params' => $promotion->params,
                ];
                
                $validated = $this->applyPromotion($validated, $promotion, $product, $calculationQuantity);
            }
        } elseif ($this->has('promotion_id') && $this->input('promotion_id') === null) {
            // Si se elimina la promoción, mantener el snapshot existente (no borrarlo)
            // Solo limpiar promotion_id
            $validated['promotion_id'] = null;
        }
        
        // Solo calcular price_final si no fue establecido por una promoción y no se envía explícitamente
        if (!isset($validated['price_final']) && !$this->has('price_final')) {
            $validated['price_final'] = Order_details::calculateFinalPrice($calculationQuantity, $validated['price_unit'], $validated['discount']);
        }
        
        return $validated;
    }

    /**
     * Aplicar promoción al detalle del pedido
     *
     * @param array $data
     * @param Promotion $promotion
     * @param Product $product
     * @param float $calculationQuantity Cantidad a usar para cálculos (weight si es producto por peso, quantity si no)
     * @return array
     */
    private function applyPromotion(array $data, Promotion $promotion, Product $product, float $calculationQuantity): array
    {
        // Verificar si la promoción está activa
        if (!$promotion->is_active || 
            $promotion->starts_at > now()->toDateString() ||
            ($promotion->ends_at && $promotion->ends_at < now()->toDateString())) {
            return $data; // No aplicar promoción si no está activa
        }

        // Verificar si el producto está en la promoción
        if ($promotion->products()->where('products.id', $product->id)->count() === 0) {
            return $data; // No aplicar promoción si el producto no está incluido
        }

        $params = $promotion->params;
        
        switch ($promotion->type) {
            case Promotion::LINE_PERCENT:
                return $this->applyLinePercentPromotion($data, $params);
                
            case Promotion::NTH_PERCENT:
                return $this->applyNthPercentPromotion($data, $params, $calculationQuantity);
                
            case Promotion::BUY_X_GET_Y:
                return $this->applyBuyXGetYPromotion($data, $params, $calculationQuantity);
                
            case Promotion::BUY_X_TOTAL_DISCOUNT:
                return $this->applyBuyXTotalDiscountPromotion($data, $params, $calculationQuantity);
                
            default:
                return $data;
        }
    }

    /**
     * Aplicar promoción de descuento porcentual en línea
     *
     * @param array $data
     * @param array $params
     * @return array
     */
    private function applyLinePercentPromotion(array $data, array $params): array
    {
        // No modificar 'discount' aquí. El frontend ya envía el porcentaje y
        // la validación verifica que sea el correcto. El cálculo de price_final
        // se hace posteriormente con el discount recibido.
        return $data;
    }

    /**
     * Aplicar promoción de N-ésimo elemento con descuento
     *
     * @param array $data
     * @param array $params
     * @param float $calculationQuantity Cantidad a usar para cálculos (weight si es producto por peso, quantity si no)
     * @return array
     */
    private function applyNthPercentPromotion(array $data, array $params, float $calculationQuantity): array
    {
        $n = $params['n'] ?? 2;
        $percent = $params['percent'] ?? 0;
        
        if ($calculationQuantity >= $n) {
            // Calcular cuántos N-ésimos items hay
            $nthItems = floor($calculationQuantity / $n);
            
            // El descuento se aplica solo a los N-ésimos items
            // Descuento promedio = (cantidad_con_descuento / cantidad_total) * porcentaje_descuento
            $discountPercentage = ($nthItems / $calculationQuantity) * $percent;
            
            // Redondear a 2 decimales
            $data['discount'] = round($discountPercentage, 2);
        } else {
            // No hay suficientes items, no hay descuento
            $data['discount'] = 0;
        }
        
        return $data;
    }

    /**
     * Aplicar promoción de compra X obtén Y
     * Ejemplo: "lleva 8 paga 7" significa x=8 (llevas 8), y=7 (pagas 7)
     * Los items gratis son: x - y = 8 - 7 = 1
     *
     * @param array $data
     * @param array $params
     * @param float $calculationQuantity Cantidad a usar para cálculos (weight si es producto por peso, quantity si no)
     * @return array
     */
    private function applyBuyXGetYPromotion(array $data, array $params, float $calculationQuantity): array
    {
        $x = $params['x'] ?? 1;
        $y = $params['y'] ?? 1;
        
        // Calcular cuántos grupos completos de X unidades se pueden formar
        $groups = floor($calculationQuantity / $x);
        
        // Si no hay grupos completos, no hay descuento
        if ($groups == 0) {
            $data['discount'] = 0;
            return $data;
        }
        
        // Calcular cuántos items son gratis por grupo: x - y
        // Ejemplo: lleva 8 paga 7 -> gratis = 8 - 7 = 1
        $freeItemsPerGroup = $x - $y;
        
        // Calcular cuántos items son gratis en total (solo en grupos completos)
        $freeItems = $groups * $freeItemsPerGroup;
        
        // Calcular el descuento porcentual: (items_gratis / cantidad_total) * 100
        // Ejemplo: lleva 8 paga 7, quantity=8 -> (1/8) * 100 = 12.5%
        // Ejemplo: lleva 8 paga 7, quantity=9 -> (1/9) * 100 = 11.11% (solo 1 grupo completo)
        $discountPercentage = ($freeItems / $calculationQuantity) * 100;
        
        // Redondear a 2 decimales
        $data['discount'] = round($discountPercentage, 2);
        
        return $data;
    }

    /**
     * Aplicar promoción de compra X obtén descuento sobre el total (múltiples niveles)
     *
     * @param array $data
     * @param array $params
     * @param float $calculationQuantity Cantidad a usar para cálculos (weight si es producto por peso, quantity si no)
     * @return array
     */
    private function applyBuyXTotalDiscountPromotion(array $data, array $params, float $calculationQuantity): array
    {
        // No modificar 'discount' aquí. El frontend ya envía el porcentaje y
        // la validación verifica que sea el correcto. El cálculo de price_final
        // se hace posteriormente con el discount recibido.
        return $data;
    }

    /**
     * Calcular el descuento esperado según el tipo de promoción
     *
     * @param Promotion $promotion
     * @return float
     */
    private function calculateExpectedDiscount(Promotion $promotion): float
    {
        $productId = $this->input('id_product');
        
        // Si no se envía id_product, obtenerlo del detalle existente
        if (!$productId && $this->route('detail')) {
            $detail = $this->route('detail');
            $productId = $detail->id_product;
        }
        
        $product = Product::find($productId);
        $isWeightProduct = $product && $product->type_product === 'w';
        
        // Determinar la cantidad a usar para cálculos (weight si es producto por peso, quantity si no)
        $weight = $this->input('weight');
        $quantity = $this->input('quantity');
        
        // Si no se envía weight o quantity, obtenerlos del detalle existente
        if ($this->route('detail')) {
            $detail = $this->route('detail');
            if (!$weight && $detail->weight) {
                $weight = $detail->weight;
            }
            if (!$quantity) {
                $quantity = $detail->quantity;
            }
        }
        
        $calculationQuantity = $isWeightProduct && $weight && $weight > 0
            ? $weight
            : ($quantity ?? 0);
        
        switch ($promotion->type) {
            case Promotion::LINE_PERCENT:
                $percent = $promotion->params['percent'] ?? 0;
                return $percent; // Solo el descuento de la promoción
                
            case Promotion::BUY_X_TOTAL_DISCOUNT:
                $discounts = $promotion->params['discounts'] ?? [];
                
                // Ordenar descuentos por cantidad X de mayor a menor
                usort($discounts, function($a, $b) {
                    return $b['x'] <=> $a['x'];
                });
                
                $bestDiscount = 0;
                
                // Buscar el mejor descuento aplicable
                foreach ($discounts as $discount) {
                    $x = $discount['x'] ?? 1;
                    $percent = $discount['percent'] ?? 0;
                    
                    if ($calculationQuantity >= $x && $percent > $bestDiscount) {
                        $bestDiscount = $percent;
                        break;
                    }
                }
                
                return $bestDiscount; // Solo el descuento de la promoción
                
            default:
                return 0;
        }
    }
}

