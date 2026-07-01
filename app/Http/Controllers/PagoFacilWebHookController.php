<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PagoFacilWebHookController extends Controller
{
    public function callback(Request $request)
    {
        Log::info('Webhook PagoFácil recibido:', $request->all());

        $data = $request->all();
        $transactionId = $data['TransactionID'] ?? $data['transactionId'] ?? $data['pagofacilTransactionId'] ?? $data['transaction_id'] ?? null;
        $pago = null;

        // Intentar buscar por transaction_id
        if ($transactionId) {
            $pago = Pago::where('transaction_id', $transactionId)->first();
        }

        // intentar buscar usando los patrones alternativos en PedidoID/paymentNumber
        if (!$pago) {
            $pedidoID = $data['PedidoID'] ?? $data['paymentNumber'] ?? null;

            if ($pedidoID) {
                // Formato QR-PIJ-{pago_id}-XYZ
                if (preg_match('/QR-PIJ-(\d+)/', $pedidoID, $m)) {
                    $pago = Pago::find($m[1]);
                }
                // Formato V{pedido_id}-C{cuota_num}
                elseif (preg_match('/V(\d+)-C(\d+)/', $pedidoID, $m)) {
                    $pago = Pago::where('id_pedido', $m[1])->where('numero_cuota', $m[2])->first();
                }
                // Formato V{pedido_id}
                elseif (preg_match('/V(\d+)/', $pedidoID, $m)) {
                    $pago = Pago::where('id_pedido', $m[1])->where('estado_pago', '!=', 'completado')->first();
                    if (!$pago) {
                        $pago = Pago::where('id_pedido', $m[1])->first();
                    }
                }
            }
        }

        // Procesar el pago si fue encontrado
        if ($pago) {
            if ($pago->estado_pago !== 'completado') {
                $totalPagado = Pago::where('id_pedido', $pago->id_pedido)
                    ->where('estado_pago', 'completado')
                    ->where('id', '!=', $pago->id)
                    ->sum('monto');

                $saldoPendiente = max(0, $pago->pedido->total - $totalPagado);
                $newSaldoPendiente = max(0, $saldoPendiente - $pago->monto);

                $pago->update([
                    'estado_pago' => 'completado',
                    'saldo_pendiente' => $newSaldoPendiente,
                    'updated_at' => now(),
                ]);

                $totalExpectedCuotas = $pago->total_cuotas;
                $completedCuotasCount = Pago::where('id_pedido', $pago->id_pedido)
                    ->where('estado_pago', 'completado')
                    ->count();

                if ($completedCuotasCount === $totalExpectedCuotas) {
                    $pago->pedido->update([
                        'estado_pedido' => 'confirmado',
                    ]);
                }

                Bitacora::create([
                    'id_usuario' => $pago->pedido->id_cliente,
                    'evento' => 'callback_pagofacil',
                    'ip' => $request->ip() ?? '127.0.0.1',
                    'recurso' => 'pagos/' . $pago->id,
                    'detalle' => json_encode([
                        'id' => $pago->id,
                        'gateway' => 'pagofacil_webhook',
                        'estado' => 'completado',
                        'transactionId' => $pago->transaction_id ?? $transactionId,
                        'saldo_pendiente' => $pago->saldo_pendiente,
                    ], JSON_UNESCAPED_UNICODE),
                    'user_agent' => $request->userAgent() ?? 'System',
                ]);
            }

            return response()->json([
                'error' => 0,
                'status' => 1,
                'message' => "Pago registrado exitosamente para la transacción/pago ID {$pago->id}.",
            ]);
        }

        return response()->json([
            'error' => 1,
            'status' => 0,
            'message' => 'No se pudo asociar la transacción a ningún pago registrado en el sistema.',
        ], 404);
    }
}
