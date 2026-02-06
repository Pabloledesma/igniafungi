<?php

namespace App\Services\Ai\Handlers;

use App\Services\Ai\Contracts\IntentHandler;
use App\Services\Ai\ConversationContext;
use App\Models\Category;
use Illuminate\Support\Str;

class CatalogHandler implements IntentHandler
{
    protected array $keywords = [
        'que tienen',
        'que venden',
        'catalogo',
        'productos',
        'lista de precios',
        'disponibles',
        'menu',
        'opciones'
    ];

    public function canHandle(string $content, ConversationContext $context): bool
    {
        $normalized = Str::lower($content);

        // Exact keywords
        if (Str::contains($normalized, ['catalogo', 'catálogo', 'lista de precios', 'portafolio'])) {
            return true;
        }

        // Patterns: "que venden", "que tienen", "que hongos", "cuales productos"
        // Also "agregar más" to show catalog again
        if (Str::contains($normalized, ['agregar más', 'agregar mas', 'ver más', 'ver mas', 'añadir otro'])) {
            return true;
        }

        return preg_match('/(que|qué|cuales|cuáles|ver|mostrar).*(venden|tienen|ofrecen|productos|hay|disponibles)/i', $content) === 1;
    }

    public function handle(string $content, ConversationContext $context): array
    {
        $categories = Category::with([
            'products' => function ($q) {
                $q->where('is_active', true)->where('stock', '>', 0);
            }
        ])->where('is_active', true)->get();

        $payload = $categories->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name, // e.g. "Hongos Frescos"
                'products' => $cat->products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->price])
            ];
        })->toArray();

        return [
            'type' => 'catalog',
            'message' => 'Aquí tienes nuestro catálogo de productos frescos y deshidratados:',
            'payload' => $payload
        ];
    }
}
