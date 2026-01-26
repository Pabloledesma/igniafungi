<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index()
    {
        $posts = Post::where('is_published', '=', true)->latest()->get();
        $products = Product::where('is_active', '=', true)->latest()->get();

        return response()->view('sitemap', [
            'posts' => $posts,
            'products' => $products,
        ])->header('Content-Type', 'text/xml');
    }
}
