<?php

namespace App\Actions\MercadoPago;

use Illuminate\Http\Request;

class VerifyWebhookSignature
{
    /**
     * Valida la firma de un webhook de Mercado Pago.
     *
     * MercadoPago envía los headers `x-signature` (formato "ts=...,v1=...")
     * y `x-request-id`. El manifiesto a firmar es:
     *   id:{data.id};request-id:{x-request-id};ts:{ts};
     * y se compara el HMAC-SHA256 (con el webhook secret) contra el valor v1.
     *
     * Fail-closed: si el secret no está configurado o cualquier header/dato
     * requerido falta, se considera inválido.
     */
    public function handle(Request $request): bool
    {
        $secret = (string) config('services.mercadopago.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $signatureHeader = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');
        $dataId = (string) $request->input('data.id', '');

        if ($signatureHeader === '' || $requestId === '' || $dataId === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signatureHeader) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, null);

            if ($key !== null) {
                $parts[$key] = $value;
            }
        }

        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;

        if (! $ts || ! $v1) {
            return false;
        }

        $manifest = 'id:'.strtolower($dataId).";request-id:{$requestId};ts:{$ts};";

        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $v1);
    }
}
