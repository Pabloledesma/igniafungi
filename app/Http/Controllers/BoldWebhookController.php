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
        
        // Bold envía la información dentro de 'data'
        $data = $payload['data'] ?? [];
        $reference = $data['reference'] ?? null;
        $status = $data['status'] ?? null; 

        if (!$reference) {
            return response()->json(['error' => 'No reference found in payload'], 400);
        }

        $order = Order::where('reference', $reference)->first();

        if (!$order) {
            Log::error("Webhook de Bold: Orden no encontrada", ['reference' => $reference]);
            return response()->json(['message' => 'Order not found'], 404);
        }

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
        $signature = $request->header('x-bold-signature');
        $secret = '';
        
        $payloadEncoded = base64_encode($request->getContent());

        // Calculamos el hash esperado
        $expectedSignature = hash_hmac('sha256', $payloadEncoded, $secret);

        // hash_equals es resistente a ataques de tiempo (timing attacks)
        return hash_equals($expectedSignature, (string)$signature);
    }
}