<?php

namespace App\Livewire;

use App\Models\Post;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;

class BlogIndex extends Component
{
    use WithPagination;

    #[Title('Blog Fungi | Ignia Fungi')]
    public function render()
    {
        return view('livewire.blog-index', [
            'posts' => Post::where('is_published', true)
                ->latest()
                ->paginate(6) // Mostramos de a 6 posts por página
        ]);
    }
}