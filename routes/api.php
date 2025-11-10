<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Models\User;


//تسجيل مستخدم جديد وتسجيل الدخول والخروج
Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');


//---------------المستخدمين المسجلين ----------------------------
Route::middleware(['auth:sanctum'])->group(function () {

    // عرض جميع الطلبات الخاصة بالمستخدم
    Route::get('orders', [OrderController::class, 'index']);

    // إنشاء طلب جديد
    Route::post('orders', [OrderController::class, 'store']);

    // عرض تفاصيل طلب واحد
    Route::get('orders/{order}', [OrderController::class, 'show']);

    // تحديث الطلب (مثل تعديل العنوان أو طريقة الدفع)
    Route::put('orders/{id}', [OrderController::class, 'update']);
    // إلغاء الطلب
    Route::put('orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    // حذف الطلب
    Route::delete('orders/{order}', [OrderController::class, 'destroy']);
    // تصفية الطلبات حسب الحالة
    Route::get('orders/status/{status}', [OrderController::class, 'filterByStatus']);
    // إضافة مراجعة جديدة
    Route::post('reviews', [ReviewController::class, 'store']);
    // تحديث مراجعة
    Route::put('reviews/{review}', [ReviewController::class, 'update']);
    // عرض تفاصيل مراجعة واحدة
    Route::get('reviews/{review}', [ReviewController::class, 'show']);
});

//--------------- الزوار -----------------------------

// عرض جميع المراجعات
Route::get('reviews', [ReviewController::class, 'index']);
// عرض جميع المنتجات
Route::get('products', [ProductController::class, 'index'])->name('api.products.index');
// عرض منتج واحد
Route::get('products/{product}', [ProductController::class, 'show']);
// تصفية المنتجات حسب الفئة
Route::get('products/category/{category}', [ProductController::class, 'Products_By_Category']);
// عرض جميع الفئات
Route::get('categories', [CategoryController::class, 'index']);
// البحث عن منتج
Route::get('search/products/', [ProductController::class, 'Search_product']);

Route::middleware(['auth:sanctum', 'CheckAdmin'])->group(function () {

    // عرض جميع المستخدمين
    Route::get('users', [UserController::class, 'userindex']);
    // عرض جميع الطلبات (للمسؤول)
    Route::get('All_orders/admin', [OrderController::class, 'adminIndex']);
    // إدارة الفئات
   // Route::apiResource('categories', CategoryController::class);
   //عرض جميع الفئات
 
   // إنشاء فئة جديدة
   Route::post('categories', [CategoryController::class, 'store']);
   // تحديث فئة
   Route::put('categories/{category}', [CategoryController::class, 'update']);
   // حذف فئة
   Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
    // ---------------إدارة المنتجات----------------

    // إنشاء منتج جديد
    Route::post('products', [ProductController::class, 'store']);
    // تحديث منتج
    Route::put('products/{product}', [ProductController::class, 'update']);
    // حذف منتج
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
    // تغيير حالة الطلب
    Route::put('orders/{order}/status', [OrderController::class, 'changeStatus']);
    // إحصائيات المسؤول
    Route::get('statistics', [OrderController::class, 'adminStatistics']);
    // حذف مراجعة
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);
    // الموافقة على مراجعة
    Route::put('reviews/{review}/approve', [ReviewController::class, 'approveReview']);


});