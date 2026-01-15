<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ManualController extends Controller
{
    public function index()
    {
        $manuals = \App\Models\Manual::where('is_published', true)
            ->orderBy('category')
            ->orderBy('title')
            ->get()
            ->groupBy('category');

        return view('manuals.index', compact('manuals'));
    }

    public function show($slug)
    {
        $manual = \App\Models\Manual::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return view('manuals.show', compact('manual'));
    }
}
