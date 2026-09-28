<?php

namespace App\Actions\MercadoPago;

use Illuminate\Http\Request;

/**
 * Valida la firma de un webhook de Mercado Pago.
 *
 * Mercado Pago manda los headers `x-signature` ("ts=...,v1=...") y
 * `x-request-id`. El manifiesto firmado es `id:{data.id};request-id:{x-request-id};ts:{ts};`
 * y `v1` es su HMAC-SHA256 con la clave secreta del webhook.
 *
 * Falla cerrado: sin clave configurada o con cualquier dato faltante, la
 * notificación se rechaza.
 */
class VerifyWebhookSignature
{
    public function handle(Request $request): bool
    {
        $secret = (string) config('services.mercadopago.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');
        $dataId = $this->dataId($request);

        if ($signature === '' || $requestId === '' || $dataId === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signature) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, null);
            $parts[$key] = $value;
        }

        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;

        if (! $ts || ! $v1) {
            return false;
        }

        $manifest = 'id:'.strtolower($dataId).";request-id:{$requestId};ts:{$ts};";

        return hash_equals(hash_hmac('sha256', $manifest, $secret), $v1);
    }

    /**
     * Mercado Pago firma el `data.id` de la URL de notificación. PHP convierte
     * el punto de `data.id` en guion bajo al parsear la query string, así que
     * se lee como `data_id`; el cuerpo JSON trae el mismo valor anidado.
     */
    private function dataId(Request $request): string
    {
        return (string) ($request->query('data_id') ?? $request->input('data.id') ?? '');
    }
}
