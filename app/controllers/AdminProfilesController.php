<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\Validator;
use App\models\Profile;
use App\models\User;
use App\services\UploadService;

final class AdminProfilesController extends AdminBaseController
{
    private const MANAGE_ROLES = ['SUPER_ADMIN', 'ADMIN', 'SUPPORT'];

    public function index(): void
    {
        $this->guard();

        $model = new Profile();
        $userModel = new User();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'user_id' => (int) ($_GET['user_id'] ?? 0),
            'is_kids' => trim((string) ($_GET['is_kids'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 12;

        try {
            $result = $model->paginate($filters, $page, $perPage);
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel carregar perfis.');
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

        $canManageProfiles = in_array((string) (Auth::user()['role'] ?? ''), self::MANAGE_ROLES, true);

        $this->render('admin/profiles', [
            'title' => 'Profiles | WisePlay Admin',
            'pageTitle' => 'Profiles',
            'items' => $result['items'],
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
            'userOptions' => $userModel->listForSelect(true),
            'maturityOptions' => $model->maturityOptions(),
            'canManageProfiles' => $canManageProfiles,
            'canCreateProfiles' => $canManageProfiles && $this->maintenanceModeEnabled(),
        ]);
    }

    public function create(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        if (!$this->maintenanceModeEnabled()) {
            Response::json([
                'ok' => false,
                'message' => 'Criacao manual de perfis desabilitada. Perfis devem ser criados no app publico.',
            ], 403);
        }

        $payload = $this->readPayload();
        $userModel = new User();
        $profileModel = new Profile();

        $errors = $this->validatePayload($payload, $userModel);
        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos obrigatorios.', 'errors' => $errors], 422);
        }

        $planLimit = $profileModel->profileLimitByUser($payload['user_id']);
        $currentCount = $profileModel->countByUser($payload['user_id']);
        if ($planLimit <= 0) {
            Response::json(['ok' => false, 'message' => 'Usuario sem plano ativo para criacao de perfis.'], 422);
        }
        if ($currentCount >= $planLimit) {
            Response::json(['ok' => false, 'message' => 'Limite de perfis do plano atingido para este usuario.'], 422);
        }

        $avatarPath = $payload['avatar_path'];
        if (isset($_FILES['avatar_file']) && is_array($_FILES['avatar_file']) && (int) ($_FILES['avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = (new UploadService())->storeImage($_FILES['avatar_file'], 'profile');
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload de avatar.')], 422);
            }
            $avatarPath = (string) ($upload['path'] ?? '');
        }

        try {
            $id = $profileModel->create([
                'user_id' => $payload['user_id'],
                'profile_name' => $payload['profile_name'],
                'avatar_path' => $avatarPath,
                'is_kids' => $payload['is_kids'],
                'maturity_rating' => $payload['is_kids'] === 1 ? '12' : $payload['maturity_rating'],
                'language' => $payload['language'],
                'autoplay' => $payload['autoplay'],
            ]);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao criar perfil.'], 500);
        }

        $this->audit('profile.create', 'profile', (string) $id, [
            'user_id' => $payload['user_id'],
            'profile_name' => $payload['profile_name'],
            'is_kids' => $payload['is_kids'],
        ]);

        Response::json(['ok' => true, 'message' => 'Perfil criado com sucesso.', 'profile_id' => $id]);
    }

    public function update(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID de perfil invalido.'], 422);
        }

        $profileModel = new Profile();
        $existing = $profileModel->findById($id);
        if (!$existing) {
            Response::json(['ok' => false, 'message' => 'Perfil nao encontrado.'], 404);
        }

        $payload = $this->readPayload();
        $payload['id'] = $id;
        $payload['user_id'] = (int) ($existing['user_id'] ?? 0);

        $userModel = new User();
        $errors = $this->validatePayload($payload, $userModel);
        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos obrigatorios.', 'errors' => $errors], 422);
        }

        $avatarPath = $payload['avatar_path'] !== '' ? $payload['avatar_path'] : (string) ($existing['avatar_path'] ?? '');
        if (isset($_FILES['avatar_file']) && is_array($_FILES['avatar_file']) && (int) ($_FILES['avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = (new UploadService())->storeImage($_FILES['avatar_file'], 'profile');
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload de avatar.')], 422);
            }
            $avatarPath = (string) ($upload['path'] ?? '');
        }

        try {
            $profileModel->update($id, [
                'user_id' => $payload['user_id'],
                'profile_name' => $payload['profile_name'],
                'avatar_path' => $avatarPath,
                'is_kids' => $payload['is_kids'],
                'maturity_rating' => $payload['is_kids'] === 1 ? '12' : $payload['maturity_rating'],
                'language' => $payload['language'],
                'autoplay' => $payload['autoplay'],
            ]);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao atualizar perfil.'], 500);
        }

        $this->audit('profile.update', 'profile', (string) $id, [
            'user_id' => $payload['user_id'],
            'profile_name' => $payload['profile_name'],
            'is_kids' => $payload['is_kids'],
        ]);

        Response::json(['ok' => true, 'message' => 'Perfil atualizado com sucesso.']);
    }

    public function delete(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID de perfil invalido.'], 422);
        }

        $model = new Profile();
        $profile = $model->findById($id);
        if (!$profile) {
            Response::json(['ok' => false, 'message' => 'Perfil nao encontrado.'], 404);
        }

        try {
            $model->delete($id);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao excluir perfil.'], 500);
        }

        $this->audit('profile.delete', 'profile', (string) $id, [
            'profile_name' => $profile['profile_name'],
            'user_id' => $profile['user_id'],
        ]);

        Response::json(['ok' => true, 'message' => 'Perfil excluido com sucesso.']);
    }

    private function readPayload(): array
    {
        return [
            'user_id' => (int) ($_POST['user_id'] ?? 0),
            'profile_name' => trim((string) ($_POST['profile_name'] ?? '')),
            'avatar_path' => trim((string) ($_POST['avatar_path'] ?? '')),
            'is_kids' => (int) ($_POST['is_kids'] ?? 0) === 1 ? 1 : 0,
            'maturity_rating' => trim((string) ($_POST['maturity_rating'] ?? '16')),
            'language' => trim((string) ($_POST['language'] ?? 'pt-BR')),
            'autoplay' => (int) ($_POST['autoplay'] ?? 1) === 1 ? 1 : 0,
        ];
    }

    private function validatePayload(array $payload, User $userModel): array
    {
        $errors = [];

        if ($payload['user_id'] <= 0 || !$userModel->findById($payload['user_id'])) {
            $errors['user_id'] = 'Usuario invalido.';
        }

        if (!Validator::required($payload['profile_name']) || !Validator::minLength($payload['profile_name'], 2)) {
            $errors['profile_name'] = 'Nome do perfil e obrigatorio (minimo 2 caracteres).';
        }

        if (!Validator::inArray($payload['maturity_rating'], (new Profile())->maturityOptions())) {
            $errors['maturity_rating'] = 'Classificacao indicativa invalida.';
        }

        if (!Validator::required($payload['language'])) {
            $errors['language'] = 'Idioma e obrigatorio.';
        }

        if ($payload['avatar_path'] !== '' && !Validator::url($payload['avatar_path']) && !str_starts_with($payload['avatar_path'], '/')) {
            $errors['avatar_path'] = 'Avatar invalido.';
        }

        return $errors;
    }

    private function maintenanceModeEnabled(): bool
    {
        return filter_var(env('ADMIN_PROFILE_MAINTENANCE_MODE', 'false'), FILTER_VALIDATE_BOOL);
    }
}
