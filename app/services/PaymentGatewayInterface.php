<?php

declare(strict_types=1);

namespace App\services;

interface PaymentGatewayInterface
{
    public function providerCode(): string;

    public function isConfigured(): bool;

    /**
     * @param array{
     *   amount: float,
     *   currency: string,
     *   description?: string,
     *   metadata?: array<string,mixed>,
     *   card?: array<string,mixed>
     * } $payload
     * @return array{
     *   ok: bool,
     *   status: string,
     *   provider_txn_id?: string,
     *   message?: string,
     *   metadata?: array<string,mixed>
     * }
     */
    public function createCharge(array $payload): array;
}
