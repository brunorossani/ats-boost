<?php

namespace App\Http\Controllers;

use App\Actions\MercadoPago\SyncSubscription;
use App\Actions\MercadoPago\VerifyWebhookSignature;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    public function handle(Request $request, VerifyWebhookSignature $verify, SyncSubscription $sync): JsonResponse
    {
        if (! $verify->handle($request)) {
            Log::warning('Mercado Pago webhook: firma inválida o ausente', ['ip' => $request->ip()]);

            return response()->json(['ok' => false], 401);
        }

        $topic = $request->input('type') ?? $request->input('topic');
        $resourceId = $request->input('data.id') ?? $request->query('data_id');

        if (! $topic || ! $resourceId) {
            Log::warning('Mercado Pago webhook: falta el tópico o el id del recurso', ['topic' => $topic]);

            return response()->json(['ok' => false], 400);
        }

        Log::info('Mercado Pago webhook recibido', ['topic' => $topic, 'resource_id' => $resourceId]);

        match ($topic) {
            'subscription_preapproval' => $sync->handle([
                'id' => (string) $resourceId,
                'type' => 'subscription_preapproval',
            ]),
            'subscription_authorized_payment' => $this->syncFromPayment((string) $resourceId, $sync),
            default => Log::info('Mercado Pago webhook: tópico sin manejar', ['topic' => $topic]),
        };

        return response()->json(['ok' => true]);
    }

    /**
     * Un cobro recurrente llega con el id del pago, no de la suscripción: hay
     * que consultar el pago para saber qué suscripción renovar.
     */
    private function syncFromPayment(string $paymentId, SyncSubscription $sync): void
    {
        try {
            $preapprovalId = app(MercadoPagoService::class)->getAuthorizedPayment($paymentId)['preapproval_id'] ?? null;
        } catch (Throwable $e) {
            Log::error('Mercado Pago webhook: no se pudo leer el pago autorizado', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! $preapprovalId) {
            Log::warning('Mercado Pago webhook: el pago no tiene suscripción asociada', ['payment_id' => $paymentId]);

            return;
        }

        $sync->handle(['id' => (string) $preapprovalId, 'type' => 'subscription_preapproval']);
    }
}
