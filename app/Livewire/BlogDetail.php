<?php

namespace App\Livewire;


use App\Models\Post;
use Livewire\Component;
use Livewire\Attributes\Title;

class BlogDetail extends Component
{
    public $post;

    public function mount($slug)
    {
        // Buscamos el post por el slug que viene en la URL
        $this->post = Post::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();
    }

    #[Title('Blog | Ignia Fungi')]
    public function render()
    {
        return view('livewire.blog-detail');
    }
}