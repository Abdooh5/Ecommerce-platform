<?php

namespace App\Http\Controllers;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\Store_ProductRequest;
use App\Http\Requests\Update_ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use PhpParser\Node\Expr\Cast\String_;

class ProductController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $page = request()->get('page', 1);
        $products = Cache::remember('products_page_' . $page, 3600, function () {
            return Product::with('category', 'reviews')->paginate(10);
        });

        return ProductResource::collection($products);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {



    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Store_ProductRequest $request)
    {
        $validatedData = $request->validated();

        $validatedData['slug'] = str()->slug($request->name, '-');

        if ($request->hasFile('image')) {
            $validatedData['image'] = $request->file('image')->store('products', 'public');
        }
        $product = Product::create($validatedData);

        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $product)
    {
        try {
            $product = Product::with('category', 'reviews')->findOrFail($product);
            return new ProductResource($product);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Failed to retrieve product',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */

    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Update_ProductRequest $request, Product $product)
    {
        // ✅ التحقق من الصلاحية عبر السياسة
        $this->authorize('update', $product);

        try {
            // ✅ التحقق من صحة البيانات القادمة من الطلب
            $validatedData = $request->validated();

            // ✅ إنشاء slug من الاسم (مع ضمان أن الحقل name متاح في request)
            if ($request->filled('name')) {
                $validatedData['slug'] = str()->slug($request->name, '-');
            }

            // ✅ في حال رفع صورة جديدة
            if ($request->hasFile('image')) {

                // حذف الصورة القديمة إن كانت موجودة ومسارها صالح
                if (!empty($product->image) && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }

                // رفع الصورة الجديدة مع اسم فريد (store يولد اسمًا فريدًا)
                $path = $request->file('image')->store('products', 'public');
                $validatedData['image'] = $path;
            }

            // ✅ تحديث المنتج بالبيانات الجديدة
            $product->update($validatedData);

            // إعادة تحميل البيانات والعلاقات
            $product->refresh()->load('category', 'reviews');

            // إعداد رابط الصورة الكامل (يتطلب php artisan storage:link)
            $fullImageUrl = $product->image ? asset('storage/' . $product->image) : null;

            // ✅ إرسال استجابة JSON واضحة ومنسقة
            return response()->json([
                'message' => 'Product updated successfully',
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'description' => $product->description,
                    'image' => $product->image,
                    'full_image_url' => $fullImageUrl,
                    'category' => $product->category,
                ],
            ], 200);

        } catch (\Exception $e) {
            // ✅ معالجة أي خطأ أثناء العملية (رفع، حذف، تحديث، إلخ)
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();
        return response()->json(null, 200);
    }
    public function Products_By_Category(Category $category)
    {
        try {
            $category = Category::findOrFail($category->id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Failed to retrieve category',
                'error' => $e->getMessage(),
            ], 404);
        }
        $products = Product::where('category_id', $category->id)->with('category', 'reviews')->get();
        return ProductResource::collection($products);
    }
    public function Search_product(Request $request)
    {
        $searchTerm = $request->input('search');

        $products = Product::where('name', 'like', "%{$searchTerm}%")
            ->orWhere('description', 'like', "%{$searchTerm}%")
            ->with('category', 'reviews')
            ->get();

        return ProductResource::collection($products);
    }
}
