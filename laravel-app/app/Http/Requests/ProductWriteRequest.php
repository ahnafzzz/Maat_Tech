<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';
        $product = $this->route('product');
        $product = $product instanceof Product ? $product : null;

        return [
            'name' => [$required, 'string', 'max:255'],
            'category_id' => [$required, 'integer', 'exists:categories,id'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product?->id)],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product?->id)],
            'price' => [$required, 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'compare_at_price' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'discount_amount' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock' => [$required, 'integer', 'min:0', 'max:2147483647'],
            'status' => ['sometimes', Rule::in(['active', 'draft', 'archived'])],
            'is_featured' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:15000'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'video' => ['sometimes', 'nullable', 'file', 'mimes:mp4,webm,mov', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:102400'],
            'image' => ['prohibited'],
            'image_path' => ['prohibited'],
            'image_url' => ['prohibited'],
            'video_path' => ['prohibited'],
            'video_url' => ['prohibited'],
            'remove_images' => $creating
                ? ['prohibited']
                : ['sometimes', 'array', 'max:10'],
            'remove_images.*' => ['string', 'max:255', 'distinct'],
            'remove_video' => $creating
                ? ['prohibited']
                : ['sometimes', 'boolean'],
        ];
    }
}
