<?php
namespace Tests\Feature\Webhooks;

use Tests\TestCase;
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
        config(['services.bold.webhook_secret' => 'test_secret']);
    }

    private function generateMockSignature($payload): string
    {
        // Es vital que sea el JSON exacto
        return hash_hmac('sha256', json_encode($payload), 'test_secret');
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
        // Creamos la orden con la referencia que viene en el JSON de Bold
        $referenceInJson = data_get($payload, 'data.metadata.reference');
        
        $order = Order::factory()->create([
            'reference' => $referenceInJson,
            'status' => 'pending'
        ]);

         // 1. ARRANGE
        $product = Product::factory()->create(['stock' => 10]);
        
        // Creamos el Item de la orden que vincula ambos
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_amount' => 10000
        ]);

        $jsonPayload = json_encode($payload);
        $signature = hash_hmac('sha256', $jsonPayload, 'test_secret');

        $response = $this->withHeaders([
                'X-Bold-Signature' => $signature,
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