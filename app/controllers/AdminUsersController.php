<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\Validator;
use App\models\BillingPlan;
use App\models\Profile;
use App\models\Subscription;
use App\models\User;
use App\services\UploadService;

final class AdminUsersController extends AdminBaseController
{
    private const MANAGE_ROLES = ['SUPER_ADMIN', 'ADMIN'];

    public function index(): void
    {
        $this->guard();

        $model = new User();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'role' => trim((string) ($_GET['role'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 9;

        try {
            $result = $model->paginate($filters, $page, $perPage);
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel carregar usuarios. Verifique o banco de dados.');
            $result = ['items' => [], 'total' => 0];
        }

        $total = (int) $result['total'];
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            $page = $lastPage;
            try {
                $result = $model->paginate($filters, $page, $perPage);
            } catch (\Throwable) {
                $result = ['items' => [], 'total' => $total];
            }
        }

        $this->render('admin/users', [
            'title' => 'Users | WisePlay Admin',
            'pageTitle' => 'Users',
            'items' => $result['items'],
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
            'roleOptions' => $model->roles(),
            'statusOptions' => $model->statuses(),
            'planOptions' => (new BillingPlan())->listActive(),
            'canManageUsers' => in_array((string) (Auth::user()['role'] ?? ''), self::MANAGE_ROLES, true),
        ]);
    }

    public function create(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $payload = $this->readPayload();

        $model = new User();
        $planModel = new BillingPlan();

        $errors = $this->validatePayload($model, $planModel, $payload, true);

        if ($errors !== []) {
            Response::json([
                'ok' => false,
                'message' => 'Verifique os campos obrigatorios.',
                'errors' => $errors,
            ], 422);
        }

        $accountAvatar = $payload['account_avatar'];
        if (isset($_FILES['account_avatar_file']) && is_array($_FILES['account_avatar_file']) && (int) ($_FILES['account_avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = (new UploadService())->storeImage($_FILES['account_avatar_file'], 'user_avatar');
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload de avatar.')], 422);
            }
            $accountAvatar = (string) ($upload['path'] ?? '');
        }

        try {
            $id = $model->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password_hash' => password_hash($payload['password'], PASSWORD_DEFAULT),
                'account_avatar' => $accountAvatar,
                'role' => $payload['role'],
                'status' => $payload['status'],
            ]);

            $plan = $planModel->findById($payload['plan_id']);
            $requiresPayment = ((int) ($plan['requires_payment_method'] ?? 0) === 1);
            (new Subscription())->assignOrUpdate(
                $id,
                $payload['plan_id'],
                'ACTIVE',
                null,
                true,
                $requiresPayment,
                $requiresPayment ? 'PAID' : 'NOT_REQUIRED',
                $requiresPayment ? 'admin' : null,
                null
            );
            (new Profile())->ensureDefaultAdultProfile($id);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao criar usuario.'], 500);
        }

        $this->audit('user.create', 'user', (string) $id, [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'role' => $payload['role'],
            'status' => $payload['status'],
            'plan_id' => $payload['plan_id'],
        ]);

        Response::json(['ok' => true, 'message' => 'Usuario criado com sucesso.', 'user_id' => $id]);
    }

    public function update(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $payload = $this->readPayload();
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID de usuario invalido.'], 422);
        }

        $model = new User();
        $planModel = new BillingPlan();

        $existing = $model->findById($id);
        if (!$existing) {
            Response::json(['ok' => false, 'message' => 'Usuario nao encontrado.'], 404);
        }

        $payload['id'] = $id;

        $errors = $this->validatePayload($model, $planModel, $payload, false);

        if ($errors !== []) {
            Response::json([
                'ok' => false,
                'message' => 'Verifique os campos obrigatorios.',
                'errors' => $errors,
            ], 422);
        }

        $accountAvatar = $payload['account_avatar'] !== '' ? $payload['account_avatar'] : (string) ($existing['account_avatar'] ?? '');
        if (isset($_FILES['account_avatar_file']) && is_array($_FILES['account_avatar_file']) && (int) ($_FILES['account_avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = (new UploadService())->storeImage($_FILES['account_avatar_file'], 'user_avatar');
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload de avatar.')], 422);
            }
            $accountAvatar = (string) ($upload['path'] ?? '');
        }

        $passwordHash = $payload['password'] !== '' ? password_hash($payload['password'], PASSWORD_DEFAULT) : '';

        try {
            $model->update($id, [
                'name' => $payload['name'],
                'email' => $payload['email'],
                'account_avatar' => $accountAvatar,
                'role' => $payload['role'],
                'status' => $payload['status'],
                'password_hash' => $passwordHash,
            ]);

            $plan = $planModel->findById($payload['plan_id']);
            $requiresPayment = ((int) ($plan['requires_payment_method'] ?? 0) === 1);
            (new Subscription())->assignOrUpdate(
                $id,
                $payload['plan_id'],
                'ACTIVE',
                null,
                true,
                $requiresPayment,
                $requiresPayment ? 'PAID' : 'NOT_REQUIRED',
                $requiresPayment ? 'admin' : null,
                null
            );
            (new Profile())->ensureDefaultAdultProfile($id);

            $actorId = (int) (Auth::user()['id'] ?? 0);
            if ($actorId === $id) {
                $fresh = $model->findById($id);
                if ($fresh) {
                    Auth::login($fresh);
                }
            }
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao atualizar usuario.'], 500);
        }

        $this->audit('user.update', 'user', (string) $id, [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'role' => $payload['role'],
            'status' => $payload['status'],
            'plan_id' => $payload['plan_id'],
            'password_reset' => $payload['password'] !== '',
        ]);

        Response::json(['ok' => true, 'message' => 'Usuario atualizado com sucesso.']);
    }

    public function toggleStatus(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID de usuario invalido.'], 422);
        }

        $model = new User();
        $user = $model->findById($id);
        if (!$user) {
            Response::json(['ok' => false, 'message' => 'Usuario nao encontrado.'], 404);
        }

        $actorId = (int) (Auth::user()['id'] ?? 0);
        if ($actorId > 0 && $actorId === $id) {
            Response::json(['ok' => false, 'message' => 'Nao e permitido desativar sua propria conta por esta acao.'], 422);
        }

        $nextStatus = ($user['status'] === 'ACTIVE') ? 'DISABLED' : 'ACTIVE';

        try {
            $model->setStatus($id, $nextStatus);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao atualizar status.'], 500);
        }

        $this->audit('user.toggle_status', 'user', (string) $id, [
            'from' => $user['status'],
            'to' => $nextStatus,
        ]);

        Response::json([
            'ok' => true,
            'message' => $nextStatus === 'ACTIVE' ? 'Usuario ativado com sucesso.' : 'Usuario desativado com sucesso.',
            'status' => $nextStatus,
        ]);
    }

    public function delete(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID de usuario invalido.'], 422);
        }

        $actorId = (int) (Auth::user()['id'] ?? 0);
        if ($actorId > 0 && $actorId === $id) {
            Response::json(['ok' => false, 'message' => 'Nao e permitido excluir sua propria conta.'], 422);
        }

        $model = new User();
        $user = $model->findById($id);
        if (!$user) {
            Response::json(['ok' => false, 'message' => 'Usuario nao encontrado.'], 404);
        }

        try {
            $model->delete($id);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao excluir usuario.'], 500);
        }

        $this->audit('user.delete', 'user', (string) $id, [
            'name' => $user['name'],
            'email' => $user['email'],
        ]);

        Response::json(['ok' => true, 'message' => 'Usuario excluido com sucesso.']);
    }

    private function readPayload(): array
    {
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'account_avatar' => trim((string) ($_POST['account_avatar'] ?? '')),
            'role' => trim((string) ($_POST['role'] ?? 'VIEWER')),
            'status' => trim((string) ($_POST['status'] ?? 'ACTIVE')),
            'plan_id' => (int) ($_POST['plan_id'] ?? 0),
        ];
    }

    private function validatePayload(User $model, BillingPlan $planModel, array $data, bool $isCreate): array
    {
        $errors = [];

        if (!Validator::required((string) $data['name']) || !Validator::minLength((string) $data['name'], 2)) {
            $errors['name'] = 'Nome e obrigatorio (minimo 2 caracteres).';
        }

        if (!Validator::email((string) $data['email'])) {
            $errors['email'] = 'Email invalido.';
        } elseif ($model->emailExists((string) $data['email'], $isCreate ? null : (int) ($data['id'] ?? 0))) {
            $errors['email'] = 'Este email ja esta em uso.';
        }

        $password = (string) ($data['password'] ?? '');
        if ($isCreate && !Validator::minLength($password, 6)) {
            $errors['password'] = 'Senha deve conter ao menos 6 caracteres.';
        }
        if (!$isCreate && $password !== '' && !Validator::minLength($password, 6)) {
            $errors['password'] = 'Nova senha deve conter ao menos 6 caracteres.';
        }

        if ((string) $data['account_avatar'] !== '' && !Validator::url((string) $data['account_avatar']) && !str_starts_with((string) $data['account_avatar'], '/')) {
            $errors['account_avatar'] = 'Avatar da conta invalido.';
        }

        if (!in_array((string) $data['role'], $model->roles(), true)) {
            $errors['role'] = 'Role invalida.';
        }

        if (!in_array((string) $data['status'], $model->statuses(), true)) {
            $errors['status'] = 'Status invalido.';
        }

        $planId = (int) ($data['plan_id'] ?? 0);
        $plan = $planId > 0 ? $planModel->findById($planId) : null;
        if (!$plan || (int) ($plan['active'] ?? 0) !== 1) {
            $errors['plan_id'] = 'Plano ativo obrigatorio.';
        } elseif (!$isCreate) {
            $profileCount = (new Profile())->countByUser((int) ($data['id'] ?? 0));
            if ($profileCount > (int) ($plan['profile_limit'] ?? 0)) {
                $errors['plan_id'] = 'Usuario possui ' . $profileCount . ' perfis e excede o limite do plano selecionado.';
            }
        }

        return $errors;
    }
}
