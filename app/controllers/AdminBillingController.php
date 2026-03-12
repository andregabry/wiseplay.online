<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\Validator;
use App\models\BillingPlan;
use App\models\Profile;
use App\models\Setting;
use App\models\Subscription;
use App\models\User;

final class AdminBillingController extends AdminBaseController
{
    private const MANAGE_ROLES = ['SUPER_ADMIN', 'ADMIN', 'BILLING_MANAGER'];

    public function index(): void
    {
        $this->guard();

        $planModel = new BillingPlan();
        $subscriptionModel = new Subscription();
        $userModel = new User();

        $planFilters = [
            'q' => trim((string) ($_GET['plan_q'] ?? '')),
            'active' => trim((string) ($_GET['plan_active'] ?? '')),
            'billing_cycle' => trim((string) ($_GET['plan_cycle'] ?? '')),
        ];

        $subFilters = [
            'q' => trim((string) ($_GET['sub_q'] ?? '')),
            'status' => trim((string) ($_GET['sub_status'] ?? '')),
            'plan_id' => (int) ($_GET['sub_plan_id'] ?? 0),
        ];

        $planPage = max(1, (int) ($_GET['plan_page'] ?? 1));
        $subPage = max(1, (int) ($_GET['sub_page'] ?? 1));

        try {
            $plans = $planModel->paginate($planFilters, $planPage, 8);
        } catch (\Throwable) {
            $plans = ['items' => [], 'total' => 0];
        }

        try {
            $subs = $subscriptionModel->paginate($subFilters, $subPage, 10);
        } catch (\Throwable) {
            $subs = ['items' => [], 'total' => 0];
        }

        $this->render('admin/billing', [
            'title' => 'Billing | WisePlay Admin',
            'pageTitle' => 'Billing',
            'planItems' => $plans['items'],
            'planFilters' => $planFilters,
            'planPagination' => [
                'page' => $planPage,
                'lastPage' => max(1, (int) ceil(((int) $plans['total']) / 8)),
                'total' => (int) $plans['total'],
            ],
            'subItems' => $subs['items'],
            'subFilters' => $subFilters,
            'subPagination' => [
                'page' => $subPage,
                'lastPage' => max(1, (int) ceil(((int) $subs['total']) / 10)),
                'total' => (int) $subs['total'],
            ],
            'cycleOptions' => $planModel->cycles(),
            'statusOptions' => $subscriptionModel->statuses(),
            'planOptions' => $planModel->listActiveSubscriptionPlans(),
            'userOptions' => $userModel->listForSelect(true),
            'canManageBilling' => in_array((string) (Auth::user()['role'] ?? ''), self::MANAGE_ROLES, true),
            'paymentGatewayEnabled' => in_array(strtolower((string) (new Setting())->value('payment.gateway_enabled', '0')), ['1', 'true', 'yes', 'on'], true),
            'paymentProvider' => strtoupper((string) (new Setting())->value('payment.provider', 'mock')),
        ]);
    }

    public function createPlan(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $payload = $this->readPlanPayload();
        $model = new BillingPlan();

        $errors = $this->validatePlanPayload($payload, $model, true);
        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos.', 'errors' => $errors], 422);
        }

        try {
            $id = $model->create($payload);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao criar plano.'], 500);
        }

        $this->audit('billing.plan.create', 'billing_plan', (string) $id, [
            'plan_name' => $payload['plan_name'],
            'billing_cycle' => $payload['billing_cycle'],
        ]);

        Response::json(['ok' => true, 'message' => 'Plano criado com sucesso.', 'plan_id' => $id]);
    }

    public function updatePlan(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID invalido.'], 422);
        }

        $model = new BillingPlan();
        if (!$model->findById($id)) {
            Response::json(['ok' => false, 'message' => 'Plano nao encontrado.'], 404);
        }

        $payload = $this->readPlanPayload();
        $payload['id'] = $id;
        $errors = $this->validatePlanPayload($payload, $model, false);
        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos.', 'errors' => $errors], 422);
        }

        try {
            $model->update($id, $payload);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao atualizar plano.'], 500);
        }

        $this->audit('billing.plan.update', 'billing_plan', (string) $id, [
            'plan_name' => $payload['plan_name'],
            'billing_cycle' => $payload['billing_cycle'],
        ]);

        Response::json(['ok' => true, 'message' => 'Plano atualizado com sucesso.']);
    }

    public function deletePlan(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID invalido.'], 422);
        }

        $model = new BillingPlan();
        $plan = $model->findById($id);
        if (!$plan) {
            Response::json(['ok' => false, 'message' => 'Plano nao encontrado.'], 404);
        }

        try {
            $model->delete($id);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Nao foi possivel excluir plano com assinaturas vinculadas.'], 500);
        }

        $this->audit('billing.plan.delete', 'billing_plan', (string) $id, ['plan_name' => $plan['plan_name']]);

        Response::json(['ok' => true, 'message' => 'Plano excluido com sucesso.']);
    }

    public function assignSubscription(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $status = strtoupper(trim((string) ($_POST['status'] ?? 'ACTIVE')));
        $endAt = trim((string) ($_POST['end_at'] ?? ''));
        $autoRenew = (int) ($_POST['auto_renew'] ?? 1) === 1;

        $errors = [];

        if ($userId <= 0 || !(new User())->findById($userId)) {
            $errors['user_id'] = 'Usuario invalido.';
        }
        $selectedPlan = $planId > 0 ? (new BillingPlan())->findById($planId) : null;
        if (!$selectedPlan) {
            $errors['plan_id'] = 'Plano invalido.';
        } elseif ((int) ($selectedPlan['active'] ?? 0) !== 1) {
            $errors['plan_id'] = 'Plano inativo. Selecione um plano ativo.';
        } elseif (!in_array((string) ($selectedPlan['billing_cycle'] ?? ''), ['MONTHLY', 'YEARLY'], true)) {
            $errors['plan_id'] = 'Apenas planos recorrentes podem ser usados em assinaturas.';
        } elseif ($userId > 0) {
            $profileCount = (new Profile())->countByUser($userId);
            $profileLimit = (int) ($selectedPlan['profile_limit'] ?? 0);
            if ($profileCount > $profileLimit) {
                $errors['plan_id'] = 'Usuario possui ' . $profileCount . ' perfis e excede o limite do plano selecionado.';
            }
        }
        if (!Validator::inArray($status, (new Subscription())->statuses())) {
            $errors['status'] = 'Status de assinatura invalido.';
        }
        if ($endAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endAt)) {
            $errors['end_at'] = 'Data final invalida (use YYYY-MM-DD).';
        }

        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos.', 'errors' => $errors], 422);
        }

        $endAtFull = $endAt !== '' ? ($endAt . ' 23:59:59') : null;

        $requiresPayment = ((int) ($selectedPlan['requires_payment_method'] ?? 0) === 1);
        $paymentStatus = 'NOT_REQUIRED';
        if ($requiresPayment) {
            if (in_array($status, ['ACTIVE', 'TRIALING'], true)) {
                $paymentStatus = 'PAID';
            } elseif ($status === 'PAYMENT_FAILED') {
                $paymentStatus = 'FAILED';
            } else {
                $paymentStatus = 'PENDING';
            }
        }

        try {
            $subscriptionId = (new Subscription())->assignOrUpdate(
                $userId,
                $planId,
                $status,
                $endAtFull,
                $autoRenew,
                $requiresPayment,
                $paymentStatus,
                $requiresPayment ? 'admin' : null,
                null
            );
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao salvar assinatura.'], 500);
        }

        $this->audit('billing.subscription.assign', 'subscription', (string) $subscriptionId, [
            'user_id' => $userId,
            'plan_id' => $planId,
            'status' => $status,
        ]);

        Response::json(['ok' => true, 'message' => 'Assinatura atualizada com sucesso.', 'subscription_id' => $subscriptionId]);
    }

    private function readPlanPayload(): array
    {
        return [
            'plan_name' => trim((string) ($_POST['plan_name'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'price' => (float) ($_POST['price'] ?? 0),
            'currency' => strtoupper(trim((string) ($_POST['currency'] ?? 'BRL'))),
            'billing_cycle' => strtoupper(trim((string) ($_POST['billing_cycle'] ?? 'MONTHLY'))),
            'profile_limit' => (int) ($_POST['profile_limit'] ?? 1),
            'max_streams' => (int) ($_POST['max_streams'] ?? 1),
            'quality_level' => trim((string) ($_POST['quality_level'] ?? '1080p')),
            'active' => (int) ($_POST['active'] ?? 1) === 1 ? 1 : 0,
            'is_selectable' => (int) ($_POST['is_selectable'] ?? 1) === 1 ? 1 : 0,
            'requires_payment_method' => (int) ($_POST['requires_payment_method'] ?? 0) === 1 ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 100),
        ];
    }

    private function validatePlanPayload(array $payload, BillingPlan $model, bool $isCreate): array
    {
        $errors = [];

        if (!Validator::required($payload['plan_name']) || !Validator::minLength($payload['plan_name'], 2)) {
            $errors['plan_name'] = 'Nome do plano e obrigatorio.';
        }

        if (!Validator::required($payload['slug']) || !Validator::slug($payload['slug'])) {
            $errors['slug'] = 'Slug invalido.';
        } elseif ($model->slugExists($payload['slug'], $isCreate ? null : (int) ($payload['id'] ?? 0))) {
            $errors['slug'] = 'Slug ja utilizado.';
        }

        if ($payload['price'] < 0) {
            $errors['price'] = 'Preco nao pode ser negativo.';
        }

        if (!Validator::required($payload['currency']) || !Validator::maxLength($payload['currency'], 10)) {
            $errors['currency'] = 'Moeda invalida.';
        }

        if (!Validator::inArray($payload['billing_cycle'], $model->cycles())) {
            $errors['billing_cycle'] = 'Ciclo de cobranca invalido.';
        }

        if (!Validator::integer($payload['profile_limit'], 1, 5)) {
            $errors['profile_limit'] = 'Limite de perfis invalido.';
        }

        if (!Validator::integer($payload['max_streams'], 1, 20)) {
            $errors['max_streams'] = 'Maximo de streams invalido.';
        }

        if (!Validator::required($payload['quality_level'])) {
            $errors['quality_level'] = 'Qualidade e obrigatoria.';
        }

        if (!Validator::integer($payload['sort_order'], 1, 9999)) {
            $errors['sort_order'] = 'Ordem de exibicao invalida.';
        }

        return $errors;
    }
}
