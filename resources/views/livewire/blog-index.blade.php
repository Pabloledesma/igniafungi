<div class="max-w-[85rem] px-4 py-10 sm:px-6 lg:px-8 lg:py-14 mx-auto">
    <div class="max-w-2xl mx-auto text-center mb-10 lg:mb-14">
        <h2 class="text-2xl font-bold md:text-4xl md:leading-tight dark:text-white">Explora el Mundo Fungi</h2>
        <p class="mt-1 text-gray-600 dark:text-gray-400">Recetas, consejos de cultivo y beneficios para tu salud.</p>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($posts as $post)
        <a class="group flex flex-col h-full border border-gray-200 hover:border-transparent hover:shadow-lg transition-all duration-300 rounded-xl p-5 dark:border-gray-700 dark:hover:border-transparent dark:hover:shadow-black/[.4]" 
           href="{{ route('blog.show', $post->slug) }}">
            
            <div class="aspect-w-16 aspect-h-11">
                <img class="w-full object-cover h-48 rounded-xl" 
                     src="{{ $post->image ? asset('storage/' . $post->image) : asset('images/placeholder-fungi.jpg') }}" 
                     alt="{{ $post->title }}">
            </div>
            
            <div class="my-6">
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-300 dark:group-hover:text-white">
                    {{ $post->title }}
                </h3>
                <p class="mt-5 text-gray-600 dark:text-gray-400 line-clamp-3">
                    {{ $post->summary }}
                </p>
            </div>
            
            <div class="mt-auto flex items-center gap-x-3">
                <img class="w-8 h-8 rounded-full" src="{{ $post->user->profile_photo_url ?? 'https://ui-avatars.com/api/?name='.$post->user->name }}" alt="Autor">
                <div>
                    <h5 class="text-sm text-gray-800 dark:text-gray-200">Por {{ $post->user->name }}</h5>
                </div>
            </div>
        </a>
        @endforeach
    </div>

    <div class="mt-12">
        {{ $posts->links() }}
    </div>
</div>