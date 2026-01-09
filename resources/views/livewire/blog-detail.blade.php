<div>
    {{-- SEO y Meta Tags --}}
    @section('title', $post->title . ' | Ignia Fungi')
    @section('meta_description', Str::limit($post->summary, 155))
    @section('meta_image', asset('storage/' . $post->image))

        @push('schema')
        <script type="application/ld+json">
        {
        "@@context": "https://schema.org",
        "@@type": "BlogPosting",
        "headline": "{{ $post->title }}",
        "description": "{{ Str::limit($post->summary, 160) }}",
        "image": "{{ asset('storage/' . $post->image) }}",
        "author": {
            "@@type": "Person",
            "name": "{{ $post->user->name }}",
            "url": "{{ route('about') }}"
        },
        "publisher": {
            "@@type": "Organization",
            "name": "Ignia Fungi",
            "logo": {
            "@@type": "ImageObject",
            "url": "{{ asset('images/logo_ignia_sin_texto.png') }}"
            }
        },
        "datePublished": "{{ $post->created_at->toIso8601String() }}",
        "dateModified": "{{ $post->updated_at->toIso8601String() }}",
        "mainEntityOfPage": {
            "@@type": "WebPage",
            "@@id": "{{ route('blog.show', $post->slug) }}"
        }
        }
        </script>
        @endpush
    <div class="bg-white dark:bg-gray-950 min-h-screen">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
                
                <div class="lg:col-span-2">
                    <nav class="mb-6 text-sm text-gray-500 flex items-center gap-2">
                        <a href="{{ route('blog.index') }}" class="hover:text-orange-600">Blog</a> 
                        <span class="text-gray-300">/</span> 
                        <span class="font-medium text-orange-600">{{ $post->category?->name ?? 'General'}}</span>
                    </nav>
                    
                    <h1 class="text-4xl md:text-5xl font-black text-gray-900 dark:text-white mb-8 leading-tight">
                        {{ $post->title }}
                    </h1>
                    
                    @if($post->image)
                        <div class="relative mb-10">
                            <img src="{{ asset('storage/' . $post->image) }}" class="w-full h-[450px] object-cover rounded-3xl shadow-2xl">
                        </div>
                    @endif

                    <article class="prose prose-lg prose-orange max-w-none dark:prose-invert">
                        {!! $post->content !!}
                    </article>
                </div>

                <div class="lg:col-span-1">
                    <div class="sticky top-24 space-y-8">
                        
                        @php $relatedProduct = $post->relatedProduct; @endphp

                        @if($relatedProduct)
                            <div class="p-6 bg-orange-50 dark:bg-gray-900 rounded-3xl border border-orange-100 dark:border-gray-800 shadow-xl">
                                <h3 class="text-xl font-bold text-orange-900 dark:text-orange-500 mb-4 text-center italic">¡Cosecha Fresca!</h3>
                                
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm mb-5">
                                    <img src="{{ asset('storage/' . ($relatedProduct->images[0] ?? '')) }}" class="w-full h-40 object-cover rounded-xl mb-4">
                                    <h4 class="font-bold text-gray-800 dark:text-white text-lg">{{ $relatedProduct->name }}</h4>
                                    <p class="text-orange-600 font-black text-xl mt-1">${{ number_format($relatedProduct->price) }} COP</p>
                                </div>

                                <button wire:click="addToCart({{ $relatedProduct->id }})" 
                                        class="w-full bg-orange-600 text-white font-bold py-4 rounded-2xl hover:bg-orange-700 transition-all">
                                    Añadir al Carrito
                                </button>
                            </div>
                        @endif

                        <div class="p-8 bg-gray-900 rounded-3xl text-center border border-gray-800">
                            <h3 class="text-2xl font-bold text-white mb-4">¿Buscas más?</h3>
                            <p class="text-gray-400 mb-6 text-sm">Explora nuestra selección completa.</p>
                            <a href="{{ route('products') }}" class="inline-block w-full border-2 border-orange-600 text-white font-bold py-3 rounded-2xl hover:bg-orange-600">
                                Ver Catálogo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>