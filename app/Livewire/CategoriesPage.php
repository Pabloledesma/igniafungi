<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Category;

class CategoriesPage extends Component
{
    public function render()
    {
        $categories = Category::where('is_active', 1)->get();
        return view('livewire.categories-page', [
            'categories' => $categories
        ]);
    }
}
