<?php

declare(strict_types=1);

namespace App\services;

use App\models\Setting;

final class PaymentGatewayManager
{
    private Setting $settings;

    public function __construct(?Setting $settings = null)
    {
        $this->settings = $settings ?? new Setting();
    }

    public function gateway(): PaymentGatewayInterface
    {
        $provider = strtolower(trim((string) $this->settings->value('payment.provider', 'mock')));
        if ($provider === 'mock') {
            return new MockPaymentGatewayService($this->settings);
        }

        return new MockPaymentGatewayService($this->settings);
    }

    public function isGatewayEnabled(): bool
    {
        $enabled = $this->isTruthy((string) $this->settings->value('payment.gateway_enabled', '0'));
        if (!$enabled) {
            return false;
        }

        return $this->gateway()->isConfigured();
    }

    public function providerLabel(): string
    {
        return strtoupper($this->gateway()->providerCode());
    }

    public function paidPlansVisible(): bool
    {
        return $this->isTruthy((string) $this->settings->value('payment.public_paid_visible', '1'));
    }

    private function isTruthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
