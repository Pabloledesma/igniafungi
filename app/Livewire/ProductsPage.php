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
    
    #[Url]
    public $selected_brands = [];

    #[Url]
    public $featured = false;

    #[Url]
    public $in_stock = false;

    #[Url]
    public $price_range = 1000000;


    public function render()
    {
        // Iniciamos el Query Builder (sin ejecutar get ni paginate aún)
        $query = Product::query()->where('is_active', 1);

        // Filtro de Categorías: Usamos whereIn porque $selected_categories es un array
        if (!empty($this->selected_categories)) {
            $query->whereIn('category_id', $this->selected_categories);
        }

        // Filtro de Marcas
        if (!empty($this->selected_brands)) {
            $query->whereIn('brand_id', $this->selected_brands);
        }

        // Filtro de Destacados
        if ($this->featured) {
            $query->where('is_featured', true);
        }

        // Filtro de Stock
        if ($this->in_stock) {
            $query->where('in_stock', true);
        }

        if($this->price_range != 1000000) {
            $query->whereBetween('price', [0, $this->price_range]);
        }


        $attributes = ['id', 'name', 'slug'];

        return view('livewire.products-page', [
            // Ejecutamos la paginación una sola vez al final
            'products' => $query->latest()->paginate(9), 
            'brands' => Brand::where('is_active', 1)->get($attributes),
            'categories' => Category::where('is_active', 1)->get($attributes)
        ]);
    }
}
