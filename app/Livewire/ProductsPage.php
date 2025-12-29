<?php

namespace App\Livewire;

use App\Models\Brand;
use App\Models\Product;
use Livewire\Component;
use App\Models\Category;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Livewire\Attributes\Title;

#[Title('Products Page')]
class ProductsPage extends Component
{
    use WithPagination;
    #[Url]
    public $selected_categories = [];
    
    public function render()
    {
        $products = Product::where('is_active', 1)->paginate();
        $attributes = ['id', 'name', 'slug'];

        if(!empty($this->selected_categories)){
            $products = Product::where('category_id', $this->selected_categories)->paginate();
        }

        return view('livewire.products-page', [
            'products' => $products,
            'brands' => Brand::where('is_active', 1)->get($attributes),
            'categories' => Category::where('is_active', 1)->get($attributes)
        ]);
    }
}
