<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;

class ProductList extends Component
{
    public $products = [];

    public function mount()
    {
        // جلب المنتجات من API الباكيند
        $response = Http::get(url('/api/products'));

        if ($response->successful()) {
            $this->products = $response->json()['data'] ?? [];
        }
    }

    public function render()
    {
        return view('livewire.product-list');
    }
}
