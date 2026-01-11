<?php
namespace Tests\Feature\Webhooks;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\Delivery;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BoldWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Definimos el secreto en el entorno de pruebas
        config(['services.bold.webhook_secret' => '']);
    }

    private function generateMockSignature($payload): string
    {
        // 1. Convertir a JSON
        $json = json_encode($payload);
        // 2. Codificar en Base64 (como pide Bold)
        $encoded = base64_encode($json);
        // 3. Generar Hash
        return hash_hmac('sha256', $encoded, '');
    }

    /** @test */
    public function it_returns_401_if_signature_is_missing_or_invalid()
    {
        $payload = ['type' => 'SALE_APPROVED'];

        // Petición sin el header X-Bold-Signature
        $response = $this->postJson('/api/webhooks/bold', $payload);

        $response->assertStatus(401);
    }
    /**
     * @test
     * @dataProvider boldPaymentProvider
     */
    public function it_processes_all_types_of_approved_payments($payload)
    {
        $referenceInJson = data_get($payload, 'data.metadata.reference');

        $order = Order::factory()->create([
            'reference' => $referenceInJson,
            'status' => 'pending'
        ]);

        $product = Product::factory()->create(['stock' => 10]);

        // Creamos el Item de la orden que vincula ambos
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_amount' => 10000
        ]);

        // 1. Convertir payload a JSON
        $jsonPayload = json_encode($payload);

        // 2. CODIFICAR EN BASE64 (Esto es lo que faltaba en el test)
        $base64Payload = base64_encode($jsonPayload);
        // 3. Generar la firma usando el Base64
        $signature = hash_hmac('sha256', $base64Payload, '');

        $response = $this->withHeaders([
            'x-bold-signature' => $signature,
        ])->postJson('/api/webhooks/bold', $payload);

        // 3. ASSERT
        $response->assertStatus(200);

        // A. Validar Inventario
        $this->assertEquals(8, $product->fresh()->stock, "El stock no se descontó correctamente para {$payload['data']['payment_method']}");

        // B. Validar Estado de la Orden
        $this->assertEquals('paid', $order->fresh()->status);

        // C. Validar Agendamiento de Entrega
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'status' => 'scheduled'
        ]);
    }

    /** @test */
    public function it_prevents_double_inventory_decrement_on_duplicated_webhook()
    {
        // 1. ARRANGE
        $product = Product::factory()->create(['stock' => 10]);
        $reference = 'REF-DOUBLE-CHECK';
        $order = Order::factory()->create(['reference' => $reference, 'status' => 'pending']);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2
        ]);

        $payload = [
            'data' => [
                'status' => 'APPROVED', // IMPORTANTE: Sin esto el switch del controlador no hace nada
                'metadata' => [
                    'reference' => $reference
                ]
            ]
        ];
        // 1. Convertir payload a JSON
        $jsonPayload = json_encode($payload);

        // 2. CODIFICAR EN BASE64 (Esto es lo que faltaba en el test)
        $base64Payload = base64_encode($jsonPayload);
        // 3. Generar la firma usando el Base64
        $secret = config('services.bold.webhook_secret');
        $signature = hash_hmac('sha256', $base64Payload, $secret);
        // 2. ACT: Enviamos el webhook por primera vez
        $this->withHeaders(['X-Bold-Signature' => $signature])->postJson('/api/webhooks/bold', $payload);
        $this->assertEquals(8, $product->fresh()->stock, "El stock no se descontó correctamente.");

        // 3. ACT: Enviamos el mismo webhook por SEGUNDA vez
        $response = $this->withHeaders(['X-Bold-Signature' => $signature])->postJson('/api/webhooks/bold', $payload);

        // 4. ASSERT: El stock NO debe haber bajado a 6
        $response->assertStatus(200);
        $this->assertEquals(8, $product->fresh()->stock, "¡Error de Idempotencia! El stock se descontó dos veces.");
    }

    /** @test */
    public function it_shows_the_thank_you_page_correctly()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'reference' => 'REF-OK',
            'status' => 'paid'
        ]);

        Delivery::factory()->create([
            'order_id' => $order->id,
            'scheduled_at' => now()->addDays(2),
            'status' => 'scheduled'
        ]);

        $response = $this->actingAs($user)
            ->get(route('order.thanks', ['reference' => 'REF-OK']));

        $response->assertStatus(200);
        $response->assertSee('REF-OK');
        $response->assertSee('Pago Confirmado');
    }

    /** @test */
    public function it_processes_approved_payment_with_empty_secret_sandbox()
    {
        // CREA LA ORDEN PRIMERO para que el controlador la encuentre
        $order = Order::factory()->create(['reference' => 'ORD-100', 'status' => 'pending']);
        $payload = [
            'data' => [
                'reference' => 'ORD-100',
                'status' => 'APPROVED',
                'payment_method' => 'tarjeta web'
            ]
        ];

        $body = json_encode($payload);

        // 1. CODIFICAR EL BODY EN BASE64 (Paso vital según Bold)
        $payloadEncoded = base64_encode($body);

        // 2. FIRMAR CON SECRETO VACÍO
        $secret = "";
        $signature = hash_hmac('sha256', $payloadEncoded, $secret);

        $response = $this->withHeaders([
            'X-Bold-Signature' => $signature,
        ])->postJson('/api/webhooks/bold', $payload);

        $response->assertStatus(200);
    }

    /**
     * Data Provider con los ejemplos que me pasaste
     */
    public static function boldPaymentProvider(): array
    {
        return [
            'Tarjeta Web' => [
                json_decode('{ "type": "SALE_APPROVED", "data": { "payment_method": "CARD_WEB", "metadata": { "reference": "WEB-ORD-009876" }, "amount": {"total": 59900} } }', true)
            ],
            'Nequi' => [
                json_decode('{ "type": "SALE_APPROVED", "data": { "payment_method": "NEQUI", "metadata": { "reference": "APP-VENTA-45210" }, "amount": {"total": 125000} } }', true)
            ],
            'PSE' => [
                json_decode('{ "type": "SALE_APPROVED", "data": { "payment_method": "PSE", "metadata": { "reference": "ECOM-FACT-10556" }, "amount": {"total": 99900} } }', true)
            ],
        ];
    }
}