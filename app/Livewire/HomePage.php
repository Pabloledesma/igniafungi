<?php

namespace App\Livewire;

use App\Models\Strain;
use Livewire\Component;
use App\Models\Category;
use Livewire\Attributes\Title;


class HomePage extends Component
{
    public function render()
    {
        $strains = Strain::where('is_active', '=', 1)->get();
        $categories = Category::where('is_active', '=', 1)->get();

        $reviews = app(\App\Services\GooglePlacesService::class)->getReviews();

        return view('livewire.home-page', [
            'strains' => $strains,
            'categories' => $categories,
            'reviews' => $reviews
        ]);
    }
}
