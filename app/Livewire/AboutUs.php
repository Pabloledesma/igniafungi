<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Title;

#[Title('Sobre Nosotros | Ignia Fungi')]
class AboutUs extends Component
{
    public function render()
    {
        return view('livewire.about-us');
    }
}
