<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;

use App\Models\Product;

class StripePaymentController extends Controller
{
   
    
    public function createPaymentIntent(Request $request)
    {
        // ✅ 1. استلام البيانات القادمة من React
        $amount = $request->input('amount');
        $items = $request->input('items', []); // مصفوفة المنتجات والكمية
    
        // ✅ 2. التحقق من أن السلة غير فارغة
        if (empty($items)) {
            return response()->json([
                'error' => 'السلة فارغة. لا يمكن إنشاء عملية دفع.',
            ], 400);
        }
    
        // ✅ 3. التحقق من توفر الكميات في المخزون
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                return response()->json([
                    'error' => "المنتج غير موجود (ID: {$item['product_id']})",
                ], 400);
            }
    
            if ($product->stock < $item['quantity']) {
                return response()->json([
                    'error' => "الكمية المطلوبة غير متوفرة للمنتج: {$product->name}. المتاح حالياً: {$product->stock}",
                ], 400);
            }
        }
    
        // ✅ 4. إنشاء الـ Payment Intent بعد التحقق من الكميات
        Stripe::setApiKey(config('services.stripe.secret'));
    
        $amountInCents = intval($amount * 100);
    
        $intent = PaymentIntent::create([
            'amount' => $amountInCents,
            'currency' => 'usd',
            'metadata' => [
                'integration_check' => 'accept_a_payment'
            ]
        ]);
    
        // ✅ 5. إرجاع client_secret إلى React
        return response()->json([
            'client_secret' => $intent->client_secret
        ]);
    }
    
}
