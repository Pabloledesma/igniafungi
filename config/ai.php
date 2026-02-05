<?php

return [
    'default' => env('AI_PROVIDER', 'gemini'),

    'drivers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash-001'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
        ],
    ],

    // System Persona / Instructions
    'system_prompt_path' => resource_path('markdown/GEMINI.md'),
];
