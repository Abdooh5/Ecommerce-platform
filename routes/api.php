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

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');



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
    Route::get('orders/status/{status}', [OrderController::class, 'filterByStatus']);

});
Route::get('products', [ProductController::class, 'index']);
Route::get('products/category/{category}', [ProductController::class, 'Products_By_Category']);
Route::get('categories', [CategoryController::class, 'index']);
Route::get('products/search', [ProductController::class, 'Search_product']);

Route::middleware(['auth:sanctum', 'CheckAdmin'])->group(function () {
    Route::get('allorders/admin', [OrderController::class, 'adminIndex']);
    Route::apiResource('categories', CategoryController::class);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
    Route::put('orders/{order}/status', [OrderController::class, 'changeStatus']);
    Route::get('statistics', [OrderController::class, 'adminStatistics']);


});