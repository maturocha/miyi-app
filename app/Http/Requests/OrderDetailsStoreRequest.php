<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Order_details;
use App\Promotion;
use App\Product;

class OrderDetailsStoreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'id_order' => 'required|integer|exists:orders,id',
            'id_product' => 'required|integer|exists:products,id',
            'promotion_id' => 'nullable|integer|exists:promotions,id',
            'quantity' => 'required|numeric|min:0.01',
            'discount' => 'nullable|numeric|min:0|max:100',
            'price_unit' => 'required|numeric|min:0.01',
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
        $validator->addExtension('promotion_valid_for_product', function ($attribute, $value, $parameters, $validator) {
            $promotion = Promotion::find($value);
            $productId = $this->input('id_product');
            
            if (!$promotion || !$promotion->is_active || 
                $promotion->starts_at > now()->toDateString() || 
                ($promotion->ends_at && $promotion->ends_at < now()->toDateString())) {
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
        
        if (isset($validated['promotion_id']) && $validated['promotion_id']) {
            $promotion = Promotion::find($validated['promotion_id']);
            $product = Product::find($validated['id_product']);
            
            if ($promotion && $product) {
                $validated = $this->applyPromotion($validated, $promotion, $product);
            }
        }
        
        // Solo calcular price_final si no fue establecido por una promoción
        if (!isset($validated['price_final'])) {
            $validated['price_final'] = Order_details::calculateFinalPrice($validated['quantity'], $validated['price_unit'], $validated['discount']);
        }
        
        return $validated;
    }

    /**
     * Aplicar promoción al detalle del pedido
     *
     * @param array $data
     * @param Promotion $promotion
     * @param Product $product
     * @return array
     */
    private function applyPromotion(array $data, Promotion $promotion, Product $product): array
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
                return $this->applyNthPercentPromotion($data, $params);
                
            case Promotion::BUY_X_GET_Y:
                return $this->applyBuyXGetYPromotion($data, $params);
                
            case Promotion::BUY_X_TOTAL_DISCOUNT:
                return $this->applyBuyXTotalDiscountPromotion($data, $params);
                
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
     * @return array
     */
    private function applyNthPercentPromotion(array $data, array $params): array
    {
        $n = $params['n'] ?? 2;
        $percent = $params['percent'] ?? 0;
        $quantity = $data['quantity'];
        
        if ($quantity >= $n) {
            // Calcular cuántos N-ésimos items hay
            $nthItems = floor($quantity / $n);
            
            // El descuento se aplica solo a los N-ésimos items
            // Descuento promedio = (cantidad_con_descuento / cantidad_total) * porcentaje_descuento
            $discountPercentage = ($nthItems / $quantity) * $percent;
            
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
     * @return array
     */
    private function applyBuyXGetYPromotion(array $data, array $params): array
    {
        $x = $params['x'] ?? 1;
        $y = $params['y'] ?? 1;
        $quantity = $data['quantity'];
        
        // Calcular cuántos grupos completos de X unidades se pueden formar
        $groups = floor($quantity / $x);
        
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
        $discountPercentage = ($freeItems / $quantity) * 100;
        
        // Redondear a 2 decimales
        $data['discount'] = round($discountPercentage, 2);
        
        return $data;
    }

    /**
     * Aplicar promoción de compra X obtén descuento sobre el total (múltiples niveles)
     *
     * @param array $data
     * @param array $params
     * @return array
     */
    private function applyBuyXTotalDiscountPromotion(array $data, array $params): array
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
        $quantity = $this->input('quantity', 0);
        
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
                    
                    if ($quantity >= $x && $percent > $bestDiscount) {
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
