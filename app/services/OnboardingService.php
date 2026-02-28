<?php

declare(strict_types=1);

namespace App\services;

use App\models\BillingPlan;
use App\models\Profile;
use App\models\Subscription;
use App\models\User;

final class OnboardingService
{
    public const STEP_PLAN = 'plan';
    public const STEP_PAYMENT = 'payment';
    public const STEP_PROFILES = 'profiles';
    public const STEP_COMPLETE = 'complete';

    private User $users;
    private Profile $profiles;
    private Subscription $subscriptions;
    private BillingPlan $plans;
    private PaymentGatewayManager $payments;

    public function __construct()
    {
        $this->users = new User();
        $this->profiles = new Profile();
        $this->subscriptions = new Subscription();
        $this->plans = new BillingPlan();
        $this->payments = new PaymentGatewayManager();
    }

    public function contextForUser(int $userId): array
    {
        $user = $this->users->findById($userId);
        $subscription = $this->subscriptions->latestByUser($userId);
        $profilesCount = $this->profiles->countByUser($userId);
        $gatewayEnabled = $this->payments->isGatewayEnabled();
        $step = $this->resolveStep($subscription, $profilesCount);

        if ($user) {
            $this->syncCompletionFlag((int) $user['id'], $step);
        }

        return [
            'user' => $user,
            'subscription' => $subscription,
            'profiles_count' => $profilesCount,
            'gateway_enabled' => $gatewayEnabled,
            'gateway_provider' => $this->payments->providerLabel(),
            'step' => $step,
        ];
    }

    public function nextStep(int $userId): string
    {
        return (string) $this->contextForUser($userId)['step'];
    }

    public function isComplete(int $userId): bool
    {
        return $this->nextStep($userId) === self::STEP_COMPLETE;
    }

    public function plansForSelection(): array
    {
        $gatewayEnabled = $this->payments->isGatewayEnabled();
        $paidVisible = $this->payments->paidPlansVisible();
        $rows = $this->plans->listPublicPlans();

        $plans = [];
        foreach ($rows as $plan) {
            $requiresPayment = ((int) ($plan['requires_payment_method'] ?? 0) === 1);
            if ($requiresPayment && !$paidVisible) {
                continue;
            }

            $selectable = ((int) ($plan['active'] ?? 0) === 1) && ((int) ($plan['is_selectable'] ?? 0) === 1);
            $reason = '';

            if (!$selectable) {
                $reason = 'Plano temporariamente indisponivel.';
            } elseif ($requiresPayment && !$gatewayEnabled) {
                $selectable = false;
                $reason = 'Pagamento indisponivel no momento.';
            }

            $plan['public_selectable'] = $selectable ? 1 : 0;
            $plan['public_unavailable_reason'] = $reason;
            $plans[] = $plan;
        }

        return $plans;
    }

    public function canSelectPlan(array $plan): bool
    {
        if ((int) ($plan['active'] ?? 0) !== 1) {
            return false;
        }
        if ((int) ($plan['is_selectable'] ?? 0) !== 1) {
            return false;
        }
        if ((int) ($plan['requires_payment_method'] ?? 0) === 1 && !$this->payments->isGatewayEnabled()) {
            return false;
        }
        return true;
    }

    private function resolveStep(?array $subscription, int $profilesCount): string
    {
        if (!$subscription || (int) ($subscription['plan_id'] ?? 0) <= 0 || (int) ($subscription['plan_active'] ?? 0) !== 1) {
            return self::STEP_PLAN;
        }

        $requiresPayment = ((int) ($subscription['requires_payment_method'] ?? 0) === 1);
        $paymentStatus = strtoupper((string) ($subscription['payment_status'] ?? 'NOT_REQUIRED'));
        $subscriptionStatus = strtoupper((string) ($subscription['status'] ?? 'PENDING'));

        if (in_array($subscriptionStatus, ['CANCELED', 'EXPIRED'], true)) {
            return self::STEP_PLAN;
        }

        if ($requiresPayment) {
            $isPaid = $paymentStatus === 'PAID' && in_array($subscriptionStatus, ['ACTIVE', 'TRIALING'], true);
            if (!$isPaid) {
                return self::STEP_PAYMENT;
            }
        } elseif (!in_array($subscriptionStatus, ['ACTIVE', 'TRIALING'], true)) {
            return self::STEP_PLAN;
        }

        if ($profilesCount <= 0) {
            return self::STEP_PROFILES;
        }

        return self::STEP_COMPLETE;
    }

    private function syncCompletionFlag(int $userId, string $step): void
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            return;
        }

        $completedAt = (string) ($user['onboarding_completed_at'] ?? '');
        if ($step === self::STEP_COMPLETE && $completedAt === '') {
            $this->users->update($userId, [
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'account_avatar' => (string) ($user['account_avatar'] ?? ''),
                'role' => (string) $user['role'],
                'status' => (string) $user['status'],
                'email_verified_at' => $user['email_verified_at'] ?? null,
                'onboarding_completed_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        if ($step !== self::STEP_COMPLETE && $completedAt !== '') {
            $this->users->update($userId, [
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'account_avatar' => (string) ($user['account_avatar'] ?? ''),
                'role' => (string) $user['role'],
                'status' => (string) $user['status'],
                'email_verified_at' => $user['email_verified_at'] ?? null,
                'onboarding_completed_at' => null,
            ]);
        }
    }
}
