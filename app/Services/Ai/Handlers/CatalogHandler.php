<?php

namespace App\Services\Ai\Handlers;

use App\Models\Category;
use App\Services\Ai\Contracts\IntentHandler;
use App\Services\Ai\Contracts\ToolExecutor;
use App\Services\Ai\ConversationContext;
use Illuminate\Support\Str;

class CatalogHandler implements IntentHandler, ToolExecutor
{
    protected array $keywords = [
        'que tienen',
        'que venden',
        'catalogo',
        'productos',
        'lista de precios',
        'disponibles',
        'menu',
        'opciones',
    ];

    public function canHandle(string $content, ConversationContext $context): bool
    {
        $normalized = Str::lower(trim($content));

        // 1. Numeric Selection (if context exists)
        if (preg_match('/^\d+$/', $normalized)) {
            $map = $context->get('catalog_selection_map');
            if ($map && isset($map[$normalized])) {
                return true;
            }
        }

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
        $normalized = Str::lower(trim($content));

        // Handle Selection
        if (preg_match('/^\d+$/', $normalized)) {
            $map = $context->get('catalog_selection_map');
            if ($map && isset($map[$normalized])) {
                $categoryId = $map[$normalized];

                return $this->showCategoryProducts($categoryId);
            }
        }

        $categories = Category::with([
            'products' => function ($q) {
                $q->where('is_active', true)->where('stock', '>', 0);
            },
        ])->where('is_active', true)->get();

        $selectionMap = [];
        $index = 1;

        $payload = $categories->map(function ($cat) use (&$selectionMap, &$index) {
            $selectionMap[$index] = $cat->id;

            return [
                'index' => $index++,
                'id' => $cat->id,
                'name' => $cat->name, // e.g. "Hongos Frescos"
                'products' => $cat->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->price]),
            ];
        })->toArray();

        // Store map in context
        $context->set('catalog_selection_map', $selectionMap);

        $message = 'Aquí tienes nuestro catálogo. Escribe el número de la categoría para ver más detalles:';
        foreach ($categories as $i => $cat) {
            $num = $i + 1;
            $message .= "\n{$num}. ".$cat->name;
        }

        return [
            'type' => 'catalog',
            'message' => $message,
            'payload' => $payload,
        ];
    }

    protected function showCategoryProducts($categoryId)
    {
        $category = Category::with(['products' => fn ($q) => $q->where('is_active', true)])->find($categoryId);

        if (! $category) {
            return ['type' => 'system', 'message' => 'Categoría no encontrada.'];
        }

        $message = "Productos en **{$category->name}**:";
        $payload = [];

        foreach ($category->products as $p) {
            $message .= "\n- {$p->name} ($".number_format($p->price, 0, ',', '.').')';
            $payload[] = [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price,
            ];
        }

        return [
            'type' => 'catalog',
            'message' => $message,
            'payload' => $payload,
        ];
    }

    public function supportedTools(): array
    {
        return ['GET_PRODUCT', 'SHOW_CATALOG'];
    }

    public function executeTool(string $toolName, array $params, ConversationContext $context): string
    {
        if ($toolName === 'GET_PRODUCT') {
            $name = $params['product_name'] ?? '';
            $res = $this->findProduct($name, $context);

            if (isset($res['error'])) {
                return 'Error: '.$res['error'];
            }

            $str = "Producto: {$res['product']} (Stock: {$res['stock']}). Precio: $".number_format($res['price'], 0, ',', '.');
            if (! empty($res['description'])) {
                $str .= " Descripción: {$res['description']}";
            }
            if (! empty($res['short_description'])) {
                $str .= " Resumen: {$res['short_description']}";
            }
            if (! empty($res['category'])) {
                $str .= " Categoría: {$res['category']}";
            }

            return $str;
        }

        if ($toolName === 'SHOW_CATALOG') {
            $res = $this->handle('', $context);

            return json_encode($res['payload']);
        }

        return "Error: Tool {$toolName} not supported by CatalogHandler.";
    }

    public function findProduct(string $productName, ConversationContext $context): array
    {
        $p = \App\Models\Product::where('name', 'like', "%{$productName}%")->where('is_active', true)->first();

        if (! $p) {
            return ['error' => 'Producto no encontrado.'];
        }

        $context->addProduct($p->id);

        $description = $p->description;

        $posts = \App\Models\Post::where('product_id', $p->id)->where('is_published', true)->take(3)->get();
        if ($posts->isNotEmpty()) {
            $description .= "\n\n💡 Información Adicional (Blog):";
            foreach ($posts as $post) {
                $description .= "\n- [{$post->title}]: ".($post->summary ?? Str::limit($post->content, 100));
            }
        }

        return [
            'product' => $p->name,
            'stock' => $p->stock,
            'price' => $p->price,
            'description' => $description,
            'short_description' => $p->short_description,
            'category' => $p->category->name ?? '',
        ];
    }
}
