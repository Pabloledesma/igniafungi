<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_returns_successful_response()
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    }

    public function test_sitemap_contains_static_routes()
    {
        $response = $this->get('/sitemap.xml');

        $response->assertSee(url('/'));
        $response->assertSee(url('/products'));
        $response->assertSee(url('/blog'));
    }

    public function test_sitemap_contains_dynamic_products()
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'slug' => 'test-product'
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee(url('/products/test-product'));
    }

    public function test_sitemap_contains_dynamic_posts()
    {
        $user = \App\Models\User::factory()->create();

        $post = Post::create([
            'title' => 'Test Post',
            'slug' => 'test-post',
            'summary' => 'Summary',
            'content' => 'Content',
            'image' => 'image.jpg',
            'is_published' => true,
            'user_id' => $user->id,
            'meta_description' => 'Meta'
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee(url('/blog/test-post'));
    }
}
