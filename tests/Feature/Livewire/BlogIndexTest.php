<?php

namespace Tests\Feature\Livewire;

use App\Livewire\BlogIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Tests\TestCase;

class BlogIndexTest extends TestCase
{
    public function test_renders_successfully()
    {
        Livewire::test(BlogIndex::class)
            ->assertStatus(200);
    }
}
