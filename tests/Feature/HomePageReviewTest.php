<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Livewire\HomePage;
use Livewire\Livewire;
use Mockery;
use App\Services\GooglePlacesService;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Strain;
use App\Models\Category;

class HomePageReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create required data for the view
        Strain::create([
            'name' => 'Test Strain',
            'slug' => 'test-strain',
            'price' => 1000, // Added dummy price if needed
            'is_active' => 1,
            'image' => 'test.jpg', // Added dummy image
            // Add other required fields if any. checking model...
        ]);
        Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => 1,
            'image' => 'test_cat.jpg',
        ]);
    }

    public function test_homepage_shows_banner_when_no_reviews()
    {
        $mockService = Mockery::mock(GooglePlacesService::class);
        $mockService->shouldReceive('getReviews')->andReturn([]);
        $this->app->instance(GooglePlacesService::class, $mockService);

        Livewire::test(HomePage::class)
            ->assertSee('¿Ya probaste nuestra cosecha?')
            ->assertSee('Déjanos tu opinión en Google');
    }

    public function test_homepage_shows_reviews_when_available()
    {
        $mockReviews = collect([
            (object) [
                'author_name' => 'Test User',
                'profile_photo_url' => 'http://example.com/photo.jpg',
                'rating' => 5,
                'text' => 'Great mushrooms!',
                'relative_time_description' => '2 days ago'
            ]
        ]);

        $mockService = Mockery::mock(GooglePlacesService::class);
        $mockService->shouldReceive('getReviews')->andReturn($mockReviews);
        $this->app->instance(GooglePlacesService::class, $mockService);

        Livewire::test(HomePage::class)
            ->assertSee('Test User')
            ->assertSee('Great mushrooms!')
            ->assertDontSee('¿Ya probaste nuestra cosecha?');
    }
}
