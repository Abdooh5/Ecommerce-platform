<?php

namespace App\Http\Controllers;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */

    public function adminIndex(Request $request)
    {
        $this->authorize('viewAny', Order::class); // يتحقق أن المستخدم إدمن أو عنده صلاحية

        // استلام فلاتر من الـ request
        $userId = $request->input('user_id');
        $status = $request->input('status');
        $from = $request->input('from'); // تاريخ البداية
        $to = $request->input('to');     // تاريخ النهاية
        $sort = $request->input('sort', 'desc'); // desc أو asc

        $orders = Order::with(['user', 'orderItems.product'])
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->orderBy('created_at', $sort)
            ->paginate(15);

        return response()->json([
            'filters' => [
                'user_id' => $userId,
                'status' => $status,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
            ],
            'orders' => $orders,
        ]);
    }
    public function adminStatistics()
{
    $this->authorize('viewAny', Order::class);

    $stats = [
        'total_users' => User::count(),
        'total_orders' => Order::count(),
        'total_sales' => Order::whereNotIn('status', ['cancelled'])->sum('total_amount'),
        'pending' => Order::where('status', 'pending')->count(),
        'processing' => Order::where('status', 'processing')->count(),
        'shipped' => Order::where('status', 'shipped')->count(),
        'delivered' => Order::where('status', 'delivered')->count(),
        'canceled' => Order::where('status', 'canceled')->count(),
    ];

    return response()->json([
        'stats' => $stats
    ]);
}


    public function index()
    {
        $user = Auth::user();
        $this->authorize('viewAny', Order::class);

        $page = request()->get('page', 1);
        $orders = Cache::remember('orders_user_' . $user->id . '_page_' . $page, 3600, function () use ($user) {
            return Order::with('orderItems.product')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        });
        return response()->json([
            'orders' => $orders
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */



    public function store(StoreOrderRequest $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $itemsInput = $request->input('items');
        $shippingAddress = $request->input('shipping_address', null);
        $paymentMethod = $request->input('payment_method', null);

        // استخدم transaction + row locking لتأمين الكميات
        $order = DB::transaction(function () use ($user, $itemsInput, $shippingAddress, $paymentMethod) {

            // جمع product_ids المطلوبة
            $productIds = collect($itemsInput)->pluck('product_id')->unique()->values()->all();

            $products = Product::whereIn('id', $productIds)
                ->select('id', 'name', 'price', 'stock') // جلب الأعمدة الضرورية فقط
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                $missing = array_diff($productIds, $products->keys()->toArray());
                throw new \Exception('Products not found: ' . implode(',', $missing));
            }

            $total = 0;
            $orderItemsData = [];

            // تحقق من وجود كل منتج وتوفر الكمية
            foreach ($itemsInput as $item) {
                $pid = (int) $item['product_id'];
                $qty = (int) $item['quantity'];

                // if (!isset($products[$pid])) {
                //     // المنتج غير موجود (النقطة هذه نادراً ما تحصل لأننا تحققنا في Request)
                //     throw new \Exception("Product ID {$pid} not found");
                // }

                $product = $products[$pid];

                if ($product->stock < $qty) {
                    throw new InsufficientStockException("Insufficient stock for product ID {$pid} only there are {$product->stock} items left.");
                }

                $price = $product->price;
                //حساب المجوع العنصر المطلوب 
                $lineTotal = bcmul($price, $qty, 2); // دقة عشرية إن رغبت
                // المجموع الكلي للطلب
                $total = bcadd($total, $lineTotal, 2);

                $orderItemsData[] = [
                    'product_id' => $pid,
                    'quantity' => $qty,
                    'price' => $price,
                ];
            }

            // إنشاء الطلب
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_amount' => $total,
                'shipping_address' => $shippingAddress,
                'payment_method' => $paymentMethod,
            ]);

            // إنشاء Order Items وتحديث المخزون
            foreach ($orderItemsData as $oi) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $oi['product_id'],
                    'quantity' => $oi['quantity'],
                    'price' => $oi['price'],
                ]);

                // إنقاص المخزون (استخدمت decrement لمنع سباقات بسيطة ولكن نحن داخل قفل الصف)
                $product = $products[$oi['product_id']];
                $product->decrement('stock', $oi['quantity']);
            }

            // هنا يمكنك إطلاق event مثلا OrderCreated
            // event(new \App\Events\OrderCreated($order));

            return $order;
        }, 5); // عدد محاولات التكرار لو فشل lock مؤقتاً

        // أعد تحميل العلاقات لردّ منسق
        $order->load('orderItems.product', 'user');

        return response()->json([

            'message' => 'Order created successfully',
            'order' => $order
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $this->authorize('view', $order); // ✅ استخدام الـ Policy
        $order = $order->load('orderItems.product', 'user');
        return response()->json($order);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrderRequest $request, $id)
    {
        try {
            $order = Order::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'الطلب غير موجود.'
            ], 404);
        }

        $this->authorize('update', $order);
        $request->validated();

        // السماح بالتعديل فقط إذا لم يتم شحن الطلب
        if (in_array($order->status, ['shipped', 'delivered', 'cancelled'])) {
            return response()->json([
                'message' => 'لا يمكن تعديل الطلبات بعد شحنها.'
            ], 403);
        }

        $itemsInput = $request->input('items');
        $shippingAddress = $request->input('shipping_address', $order->shipping_address);
        $paymentMethod = $request->input('payment_method', $order->payment_method);

        DB::beginTransaction();
        try {
            // إعادة المخزون القديم
            foreach ($order->orderItems as $oldItem) {
                $oldItem->product->increment('stock', $oldItem->quantity);
            }

            // حذف العناصر القديمة
            $order->orderItems()->delete();

            $newTotal = 0;

            foreach ($itemsInput as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new InsufficientStockException("Insufficient stock for product ID {$product->id} only there are {$product->stock} items left.");
                }

                $price = $product->price;
                $subtotal = bcmul($item['quantity'], $price, 2);
                $newTotal = bcadd($newTotal, $subtotal, 2);

                $product->decrement('stock', $item['quantity']);

                $order->orderItems()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $price,
                    'order_id' => $order->id
                ]);
            }

            $order->update([
                'shipping_address' => $shippingAddress,
                'payment_method' => $paymentMethod,
                'total_amount' => $newTotal,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'تم تحديث الطلب بنجاح.',
                'order' => $order->load('orderItems.product')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشل تحديث الطلب.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        // إعادة المخزون
        foreach ($order->orderItems as $item) {
            $item->product->increment('stock', $item->quantity);
        }

        $order->delete();
        return response()->json(['message' => 'Order deleted successfully'], 200);
    }
    public function changeStatus(Request $request, Order $order)
    {
        $this->authorize('changeStatus', $order);

        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $order->status = $request->input('status');
        $order->save();

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order,
        ]);
    }
    public function cancel(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'الطلب غير موجود.'
            ], 404);
        }

        // تحقق من أن المستخدم هو صاحب الطلب
        $this->authorize('update', $order);

        // السماح بالإلغاء فقط إذا لم يتم شحن الطلب
        if (in_array($order->status, ['shipped', 'delivered'])) {
            return response()->json([
                'message' => 'لا يمكن إلغاء هذا الطلب لأنه تم شحنه أو تسليمه.'
            ], 403);
        }

        // تغيير الحالة إلى "cancelled"
        $order->update([
            'status' => 'cancelled'
        ]);

        // إعادة المخزون تلقائيًا
        foreach ($order->orderItems as $item) {
            $item->product->increment('stock', $item->quantity);
        }

        return response()->json([
            'message' => 'تم إلغاء الطلب بنجاح.',
            'order' => $order->load('orderItems.product')
        ], 200);
    }
    public function filterByStatus($status)
    {
        $user = Auth::user();
        $this->authorize('viewAny', Order::class);

        $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            return response()->json([
                'message' => 'حالة غير صالحة.'
            ], 400);
        }

        $orders = Order::with('orderItems.product')
            ->where('user_id', $user->id)
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'orders' => $orders
        ]);
    }

}
