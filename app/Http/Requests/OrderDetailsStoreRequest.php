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
            
            if (!$promotion) {
                return false;
            }

            // Verificar si la promoción está activa
            if (!$promotion->is_active) {
                return false;
            }

            // Verificar fechas de vigencia
            if ($promotion->starts_at > now()->toDateString()) {
                return false;
            }

            if ($promotion->ends_at && $promotion->ends_at < now()->toDateString()) {
                return false;
            }

            // Verificar si el producto está en la promoción
            return $promotion->products()->where('products.id', $productId)->exists();
        });

        $validator->addReplacer('promotion_valid_for_product', function ($message, $attribute, $rule, $parameters) {
            return 'La promoción seleccionada no es válida para este producto o no está activa.';
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
        
        // Aplicar promoción si se especifica
        if (isset($validated['promotion_id']) && $validated['promotion_id']) {
            $promotion = Promotion::find($validated['promotion_id']);
            $product = Product::find($validated['id_product']);
            
            if ($promotion && $product) {
                $validated = $this->applyPromotion($validated, $promotion, $product);
            }
        }
        
        // Calcular price_final solo si no se aplicó promoción o como respaldo
        if (!isset($validated['price_final'])) {
            $validated['price_final'] = Order_details::calculateFinalPrice(
                $validated['quantity'],
                $validated['price_unit'],
                $validated['discount']
            );
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
        $percent = $params['percent'] ?? 0;
        $quantity = $data['quantity'];
        $basePrice = $data['price_unit'];
        $currentDiscount = $data['discount'] ?? 0;
        
        // Calcular el descuento total (actual + promoción)
        $totalDiscount = min(100, $currentDiscount + $percent);
        
        // Calcular el precio final con el descuento total
        $subtotal = $quantity * $basePrice;
        $discountAmount = ($subtotal * $totalDiscount) / 100;
        $finalPrice = $subtotal - $discountAmount;
        
        $data['price_final'] = $finalPrice;
        
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
        $basePrice = $data['price_unit'];
        $currentDiscount = $data['discount'] ?? 0;
        
        // Para NTH_PERCENT, aplicamos el descuento solo al N-ésimo elemento
        if ($quantity >= $n) {
            // Calcular cuántos elementos N-ésimos hay
            $nthItem = floor($quantity / $n);
            
            // Calcular el descuento por elemento N-ésimo
            $discountPerNth = ($basePrice * $percent) / 100;
            
            // Calcular el descuento total en monto
            $discountAmount = $nthItem * $discountPerNth;
            
            // Calcular el subtotal sin descuento de promoción
            $subtotal = $quantity * $basePrice;
            
            // Aplicar descuento actual primero
            $currentDiscountAmount = ($subtotal * $currentDiscount) / 100;
            $subtotalAfterCurrentDiscount = $subtotal - $currentDiscountAmount;
            
            // Aplicar descuento de promoción
            $finalPrice = $subtotalAfterCurrentDiscount - $discountAmount;
            
            $data['price_final'] = $finalPrice;
        } else {
            // Si no aplica la promoción, calcular precio normal
            $data['price_final'] = Order_details::calculateFinalPrice(
                $quantity,
                $basePrice,
                $currentDiscount
            );
        }
        
        return $data;
    }

    /**
     * Aplicar promoción de compra X obtén Y
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
        $basePrice = $data['price_unit'];
        $currentDiscount = $data['discount'] ?? 0;
        
        // Para BUY_X_GET_Y, calculamos cuántos grupos de X+Y se pueden formar
        $groups = floor($quantity / ($x + $y));
        $paidItems = $groups * $x;
        $freeItems = $groups * $y;
        
        // Si hay items adicionales, se pagan normalmente
        $remainingItems = $quantity - ($paidItems + $freeItems);
        $paidItems += $remainingItems;
        
        // Calcular el precio total
        $totalPrice = $paidItems * $basePrice;
        
        // Aplicar descuento actual al precio total
        $currentDiscountAmount = ($totalPrice * $currentDiscount) / 100;
        $finalPrice = $totalPrice - $currentDiscountAmount;
        
        $data['price_final'] = $finalPrice;
        
        return $data;
    }
}
