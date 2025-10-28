<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // items يجب أن يكون مصفوفة
            'items' => 'required|array|min:1',
            // لكل عنصر: product_id موجود في جدول products و quantity عدد صحيح >=1
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            // (اختياري) بيانات الشحن أو العنوان
            'shipping_address' => 'nullable|string|max:1000',
            // (اختياري) طريقة الدفع
            'payment_method' => 'nullable|string|max:100',


        ];

    }
    public function messages(): array
    {
        return [
            'items.required' => 'يجب إضافة عناصر للطلب.',
            'items.*.product_id.exists' => 'أحد المنتجات غير موجود.',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل.',
        ];
    }
}
