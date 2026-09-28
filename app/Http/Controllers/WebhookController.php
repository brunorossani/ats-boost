<?php

namespace App\Http\Controllers;

use App\Actions\MercadoPago\SyncSubscription;
use App\Actions\MercadoPago\VerifyWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request, VerifyWebhookSignature $verify): JsonResponse
    {
        if (! $verify->handle($request)) {
            Log::warning('Mercado Pago webhook: firma inválida o ausente', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['ok' => false], 401);
        }

        Log::info('Mercado Pago webhook payload', $request->all());

        $topic = $request->input('type')
            ?? $request->input('topic');

        // Caso 1: data.id
        $resourceId = $request->input('data.id');

        // Caso 2: resource = URL completa
        if (! $resourceId && $request->filled('resource')) {
            $resourceId = basename($request->input('resource'));
        }

        if (! $topic || ! $resourceId) {
            Log::warning('Mercado Pago: Missing topic or resource ID', [
                'payload' => $request->all(),
            ]);

            return response()->json(['ok' => false], 400);
        }

        Log::info('Mercado Pago webhook received', [
            'topic' => $topic,
            'resource_id' => $resourceId,
        ]);

        // Resuelto acá (no como parámetro tipado del método) para que un
        // MercadoPagoService mal configurado no impida ni siquiera llegar a
        // este punto (la verificación de firma de arriba debe poder correr
        // siempre, sin depender de que el servicio de MercadoPago construya).
        try {
            $sync = app(SyncSubscription::class);
        } catch (\Throwable $e) {
            Log::error('Mercado Pago webhook: no se pudo sincronizar, servicio no disponible', [
                'error' => $e->getMessage(),
            ]);

            // 503: le señala a Mercado Pago que reintente más tarde, en vez
            // de descartar el evento silenciosamente.
            return response()->json(['ok' => false], 503);
        }

        match ($topic) {
            'subscription_preapproval' => $sync->handle([
                'id' => $resourceId,
                'type' => 'subscription_preapproval',
            ]),
            'subscription_authorized_payment' => $sync->handle([
                'id' => $resourceId,
                'type' => 'subscription_authorized_payment',
            ]),
            default => Log::info('Mercado Pago: Unhandled webhook topic', [
                'topic' => $topic,
            ]),
        };

        return response()->json(['ok' => true]);
    }
}
