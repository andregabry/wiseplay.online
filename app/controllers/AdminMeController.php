<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\Validator;
use App\models\User;
use App\services\UploadService;

final class AdminMeController extends AdminBaseController
{
    public function index(): void
    {
        $this->guard();

        $id = (int) (Auth::user()['id'] ?? 0);
        $user = $id > 0 ? (new User())->findById($id) : null;
        if (!$user) {
            flash('error', 'Usuario autenticado nao encontrado.');
            Response::redirect('/admin');
        }

        $this->render('admin/me', [
            'title' => 'My Profile | WisePlay Admin',
            'pageTitle' => 'My Profile',
            'user' => $user,
        ]);
    }

    public function update(): void
    {
        $this->guard();
        $this->requireCsrf();

        $id = (int) (Auth::user()['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'Sessao invalida.'], 401);
        }

        $model = new User();
        $existing = $model->findById($id);
        if (!$existing) {
            Response::json(['ok' => false, 'message' => 'Usuario nao encontrado.'], 404);
        }

        $payload = $this->readPayload();
        $errors = $this->validatePayload($payload, $model, $id);
        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos.', 'errors' => $errors], 422);
        }

        $avatar = $payload['account_avatar'] !== '' ? $payload['account_avatar'] : (string) ($existing['account_avatar'] ?? '');
        if (isset($_FILES['account_avatar_file']) && is_array($_FILES['account_avatar_file']) && (int) ($_FILES['account_avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = (new UploadService())->storeImage($_FILES['account_avatar_file'], 'account_avatar');
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload do avatar.')], 422);
            }
            $avatar = (string) ($upload['path'] ?? '');
        }

        $passwordHash = $payload['password'] !== '' ? password_hash($payload['password'], PASSWORD_DEFAULT) : '';

        try {
            $model->update($id, [
                'name' => $payload['name'],
                'email' => $payload['email'],
                'account_avatar' => $avatar,
                'role' => (string) ($existing['role'] ?? 'ADMIN'),
                'status' => (string) ($existing['status'] ?? 'ACTIVE'),
                'password_hash' => $passwordHash,
            ]);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao atualizar perfil.'], 500);
        }

        $freshUser = $model->findById($id);
        if ($freshUser) {
            Auth::login($freshUser);
        }

        $this->audit('admin.me.update', 'user', (string) $id, [
            'email' => $payload['email'],
            'password_updated' => $payload['password'] !== '',
            'avatar_updated' => $avatar !== (string) ($existing['account_avatar'] ?? ''),
        ]);

        Response::json(['ok' => true, 'message' => 'Perfil atualizado com sucesso.']);
    }

    private function readPayload(): array
    {
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'account_avatar' => trim((string) ($_POST['account_avatar'] ?? '')),
        ];
    }

    private function validatePayload(array $payload, User $model, int $id): array
    {
        $errors = [];

        if (!Validator::required($payload['name']) || !Validator::minLength($payload['name'], 2)) {
            $errors['name'] = 'Nome invalido.';
        }

        if (!Validator::email($payload['email'])) {
            $errors['email'] = 'Email invalido.';
        } elseif ($model->emailExists($payload['email'], $id)) {
            $errors['email'] = 'Este email ja esta em uso.';
        }

        if ($payload['password'] !== '' && !Validator::minLength($payload['password'], 6)) {
            $errors['password'] = 'Nova senha deve ter ao menos 6 caracteres.';
        }

        if ($payload['account_avatar'] !== '' && !Validator::url($payload['account_avatar']) && !str_starts_with($payload['account_avatar'], '/')) {
            $errors['account_avatar'] = 'Avatar invalido.';
        }

        return $errors;
    }
}
