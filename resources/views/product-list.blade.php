<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Products</h1>

    <div class="grid grid-cols-3 gap-4">
        @forelse ($products as $product)
            <div class="border p-4 rounded shadow">
                <img src="{{ asset('storage/' . ($product['image'] ?? 'default.png')) }}" alt="{{ $product['name'] }}" class="mb-2 w-full h-48 object-cover rounded">
                <h2 class="font-semibold">{{ $product['name'] }}</h2>
                <p class="text-gray-600">Price: ${{ $product['price'] }}</p>
            </div>
        @empty
            <p>No products found.</p>
        @endforelse
    </div>
</div>
