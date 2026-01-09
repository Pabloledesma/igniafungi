<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
        <div class="lg:col-span-2">
            <nav class="mb-5 text-sm text-gray-500">
                <a href="{{ route('blog.index') }}" class="hover:text-orange-600">Blog</a> / 
                <span>{{ $post->category?->name ?? 'General'}}</span>
            </nav>
            
            <h1 class="text-4xl font-extrabold text-gray-900 mb-6">{{ $post->title }}</h1>
            
            @if($post->image)
                <img src="{{ asset('storage/' . $post->image) }}" class="w-full h-96 object-cover rounded-xl mb-8 shadow-lg">
            @endif

            <div class="prose prose-orange max-w-none dark:prose-invert">
                {!! $post->content !!}
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="sticky top-24 p-6 bg-orange-50 rounded-2xl border border-orange-100 shadow-sm">
                <h3 class="text-xl font-bold text-orange-900 mb-4 text-center">¡Cosecha Fresca Disponible!</h3>
                
                {{-- Aquí inyectamos el producto que mencionas en el post --}}
                @if($relatedProduct = $post->relatedProduct)
                    <div class="bg-white p-4 rounded-xl shadow-sm mb-4">
                        <img src="{{ $relatedProduct->images[0] ?? '' }}" class="w-full h-32 object-cover rounded-lg mb-2">
                        <h4 class="font-bold text-gray-800">{{ $relatedProduct->name }}</h4>
                        <p class="text-orange-600 font-bold">${{ number_format($relatedProduct->price) }} COP</p>
                    </div>

                    <button wire:click="addToCart({{ $relatedProduct->id }})" 
                            class="w-full bg-orange-600 text-white font-bold py-3 rounded-xl hover:bg-orange-700 transition shadow-md">
                        Comprar ahora
                    </button>
                @endif
                
                <p class="text-xs text-orange-800 mt-4 text-center">🚚 Envíos rápidos en Bogotá y alrededores.</p>
            </div>
        </div>
    </div>
</div>