<?php

namespace App\Providers;

use App\Models\Order;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Product;
use App\Policies\ProductPolicy;
use App\Policies\OrderPolicy ;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Product::class => ProductPolicy::class,
        Order::class => OrderPolicy::class
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
