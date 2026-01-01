<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class BoldWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Bold envía los datos en el cuerpo de la petición
        $data = $request->all();
        
        // El campo 'reference' o 'order_id' identifica tu venta
        $order = Order::find($data['bold-order-id'] ?? $data['reference']);

        if (!$order) {
            Log::error("Webhook de Bold: Orden no encontrada", ['data' => $data]);
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Verificar el estado según los rangos de Sandbox
        // Estados posibles: approved, rejected, error
        if ($data['bold-tx-status'] === 'approved') {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'processing'
            ]);
            Log::info("Orden #{$order->id} marcada como PAGADA vía Webhook.");
        }

        return response()->json(['status' => 'received'], 200);
    }
}