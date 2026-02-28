<?php

declare(strict_types=1);

namespace App\services;

use App\models\Setting;

final class MockPaymentGatewayService implements PaymentGatewayInterface
{
    private Setting $settings;

    public function __construct(?Setting $settings = null)
    {
        $this->settings = $settings ?? new Setting();
    }

    public function providerCode(): string
    {
        return 'mock';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function createCharge(array $payload): array
    {
        $autoApprove = $this->isTruthy((string) $this->settings->value('payment.mock_auto_approve', '1'));
        $providerTxnId = 'mock_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));

        if (!$autoApprove) {
            return [
                'ok' => true,
                'status' => 'PENDING',
                'provider_txn_id' => $providerTxnId,
                'message' => 'Pagamento enviado para analise no gateway mock.',
                'metadata' => [
                    'mode' => (string) $this->settings->value('payment.mode', 'sandbox'),
                ],
            ];
        }

        return [
            'ok' => true,
            'status' => 'PAID',
            'provider_txn_id' => $providerTxnId,
            'message' => 'Pagamento aprovado no gateway mock.',
            'metadata' => [
                'mode' => (string) $this->settings->value('payment.mode', 'sandbox'),
            ],
        ];
    }

    private function isTruthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
