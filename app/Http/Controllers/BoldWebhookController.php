<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class BoldWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Validar la firma (Seguridad)
        if (!$this->isValidSignature($request)) {
            Log::warning("Intento de Webhook de Bold con firma inválida", [
                'ip' => $request->ip(),
                'signature' => $request->header('X-Bold-Signature')
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $payload = $request->all();
        
        // 2. Usamos data_get para buscar la referencia en cualquier lugar posible
        // Esto busca en data.reference o en data.metadata.reference
        $reference = data_get($payload, 'data.metadata.reference') 
              ?? data_get($payload, 'data.reference');

        if (!$reference) {
            return response()->json(['error' => 'No reference found in payload'], 400);
        }

        $order = Order::where('reference', $reference)->first();

        if (!$order) {
            Log::error("Webhook de Bold: Orden no encontrada", ['reference' => $reference]);
            return response()->json(['message' => 'Order not found'], 404);
        }

        $status = data_get($payload, 'data.status');
        // Lógica de estados basada en la documentación de Bold
        switch ($status) {
            case 'APPROVED': // Estado para éxito según estándar Bold
            case 'SALE_APPROVED': 
                $order->update(['status' => 'paid']);
                $order->completeOrder(); // Método que ya tienes para limpiar carrito/enviar mail
                Log::info("Orden #{$order->id} PAGADA exitosamente.");
                return response()->json(['status' => 'success'], 200);

            case 'REJECTED': // Estado para rechazos (fondos insuficientes, pin inválido, etc.)
                $order->update(['status' => 'failed']);
                Log::warning("Orden #{$order->id} RECHAZADA por la pasarela.");
                return response()->json(['status' => 'rejected'], 200);

            case 'FAILED':
            case 'ERROR':
                $order->update(['status' => 'failed']);
                Log::error("Orden #{$order->id} falló por error técnico en Bold.");
                return response()->json(['status' => 'error'], 200);

            default:
                Log::info("Webhook de Bold recibió un estado no manejado: {$status}");
                return response()->json(['status' => 'ignored'], 200);
        }
    }

    private function isValidSignature(Request $request): bool
    {
        $signature = $request->header('X-Bold-Signature') ?? $request->header('x-bold-signature');
        // Si config devuelve null, usamos string vacío por seguridad
        $secret = config('services.bold.webhook_secret') ?? ''; 

        $payloadEncoded = base64_encode($request->getContent());
        $expectedSignature = hash_hmac('sha256', $payloadEncoded, $secret);

        return hash_equals($expectedSignature, (string)$signature);
    }
}