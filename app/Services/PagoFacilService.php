<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Pago;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PagoFacilService
{
    /**
     * Authenticate with PagoFacil API and return the access token.
     *
     * @throws \Exception
     */
    public function login(): string
    {
        $baseUrl = config('services.pagofacil.base_url');
        $serviceToken = config('services.pagofacil.service_token');
        $secretToken = config('services.pagofacil.secret_token');

        if (!$serviceToken || !$secretToken) {
            throw new \Exception('Las credenciales de PagoFacil (PAGOFACIL_SERVICE_TOKEN, PAGOFACIL_SECRET_TOKEN) no están configuradas en el entorno.');
        }

        $response = Http::withHeaders([
            'tcTokenService' => $serviceToken,
            'tcTokenSecret' => $secretToken,
        ])->post("{$baseUrl}/login");

        if ($response->failed()) {
            Log::error('PagoFacil Login Request Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Error de comunicación con el servicio de autenticación de PagoFacil.');
        }

        $data = $response->json();

        if (($data['error'] ?? 1) !== 0 || !isset($data['values']['accessToken'])) {
            Log::error('PagoFacil Authentication Error', $data);
            throw new \Exception('Autenticación fallida con PagoFacil: ' . ($data['message'] ?? 'Error desconocido'));
        }

        return $data['values']['accessToken'];
    }

    /**
     * Generate a new QR code from PagoFacil for the given Payment.
     *
     * @return array Array containing transactionId and qrBase64
     *
     * @throws \Exception
     */
    public function generateQr(Pago $pago): array
    {
        $token = $this->login();
        $baseUrl = config('services.pagofacil.base_url');
        $pedido = $pago->pedido;
        $cliente = $pedido->cliente;
        // dd($token, $pago, $baseUrl, $pedido, $cliente);

        $clientName = $cliente ? "{$cliente->nombre} {$cliente->apellido}" : 'Cliente Pijama Cloud';
        $clientCI = $cliente ? $cliente->ci : '1000000';
        $clientPhone = $cliente ? ($cliente->telefono ?? '70000000') : '70000000';
        $clientEmail = $cliente ? $cliente->email : 'cliente@pijama.com';
        $paymentCode = "QR-PIJ-{$pago->id}" . "-" . strtoupper(uniqid());

        $body = [
            'paymentMethod' => 34, // QR
            'clientName' => $clientName,
            'documentType' => 1, // CI
            'documentId' => $clientCI,
            'phoneNumber' => $clientPhone,
            'email' => $clientEmail,
            'paymentNumber' => $paymentCode,
            'amount' => (float) $pago->monto,
            'currency' => 2, // BOB
            'clientCode' => "CLI-{$pedido->id_cliente}",
            // 'callbackUrl' => config('services.pagofacil.callback_url'),
            'callbackUrl' => "https://thepross.xyz/puente/pagofacil/callback",
            'orderDetail' => [
                [
                    'serial' => 1,
                    'product' => "Abono Pedido #{$pago->id_pedido} (Cuota {$pago->numero_cuota}/{$pago->total_cuotas})",
                    'quantity' => 1,
                    'price' => (float) $pago->monto,
                    'discount' => 0,
                    'total' => (float) $pago->monto,
                ],
            ],
        ];

        $response = Http::withToken($token)
            ->post("{$baseUrl}/generate-qr", $body);

        if ($response->failed()) {
            Log::error('PagoFacil generate-qr Request Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Error al comunicarse con la API de generación de QR de PagoFacil.');
        }

        $data = $response->json();

        if (($data['error'] ?? 1) !== 0 || !isset($data['values']['qrBase64'])) {
            Log::error('PagoFacil QR Generation Error', $data);
            throw new \Exception('No se pudo generar el código QR: ' . ($data['message'] ?? 'Error desconocido'));
        }

        return [
            'transaction_id' => $data['values']['transactionId'],
            'qr_base64' => $data['values']['qrBase64'],
        ];
    }

    /**
     * Query transaction information from PagoFacil.
     *
     * @throws \Exception
     */
    public function queryTransaction(string $transactionId): array
    {
        $token = $this->login();
        $baseUrl = config('services.pagofacil.base_url');

        $response = Http::withToken($token)
            ->post("{$baseUrl}/query-transaction", [
                'pagofacilTransactionId' => $transactionId,
            ]);

        if ($response->failed()) {
            Log::error('PagoFacil query-transaction Request Failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Error de comunicación al consultar la transacción en PagoFacil.');
        }

        $data = $response->json();

        if (($data['error'] ?? 1) !== 0 || !isset($data['values'])) {
            Log::error('PagoFacil Query Transaction Error', $data);
            throw new \Exception('No se pudo obtener información de la transacción: ' . ($data['message'] ?? 'Error desconocido'));
        }

        return $data['values'];
    }

    /**
     * Query status and update the payment in database if status changes.
     *
     * @return bool True if payment was completed in this call.
     */
    public function verificarYActualizarPago(Pago $pago): bool
    {
        try {
            $values = $this->queryTransaction($pago->transaction_id);

            $paymentStatus = (int) ($values['paymentStatus'] ?? 1);


            if ($paymentStatus === 3 && $pago->estado_pago !== 'completado') {
                // Transaction successful, complete payment
                $totalPagado = Pago::where('id_pedido', $pago->id_pedido)
                    ->where('estado_pago', 'completado')
                    ->where('id', '!=', $pago->id)
                    ->sum('monto');

                $saldoPendiente = max(0, $pago->pedido->total - $totalPagado);
                $newSaldoPendiente = max(0, $saldoPendiente - $pago->monto);

                $pago->update([
                    'estado_pago' => 'completado',
                    'saldo_pendiente' => $newSaldoPendiente,
                ]);

                // Audit Log
                Bitacora::create([
                    'id_usuario' => $pago->pedido->id_cliente,
                    'evento' => 'callback_pagofacil',
                    'ip' => request()->ip() ?? '127.0.0.1',
                    'recurso' => 'pagos/' . $pago->id,
                    'detalle' => json_encode([
                        'id' => $pago->id,
                        'gateway' => 'pagofacil_real',
                        'estado' => 'completado',
                        'transactionId' => $pago->transaction_id,
                        'saldo_pendiente' => $pago->saldo_pendiente,
                    ], JSON_UNESCAPED_UNICODE),
                    'user_agent' => request()->userAgent() ?? 'System',
                ]);

                return true;
            } elseif ($paymentStatus === 4 && $pago->estado_pago === 'pendiente') {
                // Transaction cancelled/annulled
                $pago->update([
                    'estado_pago' => 'fallido',
                ]);

                Bitacora::create([
                    'id_usuario' => $pago->pedido->id_cliente,
                    'evento' => 'callback_pagofacil',
                    'ip' => request()->ip() ?? '127.0.0.1',
                    'recurso' => 'pagos/' . $pago->id,
                    'detalle' => json_encode([
                        'id' => $pago->id,
                        'gateway' => 'pagofacil_real',
                        'estado' => 'fallido',
                        'transactionId' => $pago->transaction_id,
                    ], JSON_UNESCAPED_UNICODE),
                    'user_agent' => request()->userAgent() ?? 'System',
                ]);
            }

            if ($paymentStatus !== 1 && $pago->estado_pago === 'pendiente') {
                // Transaction successful, complete payment
                $totalPagado = Pago::where('id_pedido', $pago->id_pedido)
                    ->where('estado_pago', 'completado')
                    ->where('id', '!=', $pago->id)
                    ->sum('monto');

                $saldoPendiente = max(0, $pago->pedido->total - $totalPagado);
                $newSaldoPendiente = max(0, $saldoPendiente - $pago->monto);

                $pago->update([
                    'estado_pago' => 'completado',
                    'saldo_pendiente' => $newSaldoPendiente,
                ]);

                // Audit Log
                Bitacora::create([
                    'id_usuario' => $pago->pedido->id_cliente,
                    'evento' => 'callback_pagofacil',
                    'ip' => request()->ip() ?? '127.0.0.1',
                    'recurso' => 'pagos/' . $pago->id,
                    'detalle' => json_encode([
                        'id' => $pago->id,
                        'gateway' => 'pagofacil_real',
                        'estado' => 'completado',
                        'transactionId' => $pago->transaction_id,
                        'saldo_pendiente' => $pago->saldo_pendiente,
                    ], JSON_UNESCAPED_UNICODE),
                    'user_agent' => request()->userAgent() ?? 'System',
                ]);
                return true;
            }

        } catch (\Exception $e) {
            Log::warning("Error verificando estado del pago QR #{$pago->id}: " . $e->getMessage());
        }

        return false;
    }
}
