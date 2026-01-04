<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class BoldWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Validar la firma antes de cualquier otra cosa
        if (!$this->isValidSignature($request)) {
            Log::warning("Intento de Webhook de Bold con firma inválida", [
                'ip' => $request->ip(),
                'signature' => $request->header('X-Bold-Signature')
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        // Bold envía los datos en el cuerpo de la petición
        $data = $request->all();

        $reference = data_get($data, 'data.metadata.reference');

        if (!$reference) {
            return response()->json(['error' => 'Reference not found in payload'], 400);
        }
        
        // El campo 'reference' o 'order_id' identifica tu venta
        $order = Order::where('reference', $reference)->first();

        if (!$order) {
            Log::error("Webhook de Bold: Orden no encontrada", ['data' => $data]);
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Verificar el estado según los rangos de Sandbox
        // Estados posibles: approved, rejected, error
        if ($data['type'] === 'SALE_APPROVED') {
            $order->completeOrder();
            Log::info("Orden #{$order->id} procesada exitosamente.");
            return response()->json(['status' => 'approved'], 200);
        }

        return response()->json(['status' => 'event_ignored'], 200);
    }

    private function isValidSignature(Request $request): bool
    {
        $signature = $request->header('x-bold-signature');
        $secret = '';
        
        $payloadEncoded = base64_encode($request->getContent());

        // Calculamos el hash esperado
        $expectedSignature = hash_hmac('sha256', $payloadEncoded, $secret);

        // hash_equals es resistente a ataques de tiempo (timing attacks)
        return hash_equals($expectedSignature, (string)$signature);
    }
}