<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\Validator;
use App\models\BillingPlan;
use App\models\Title;
use App\services\PublicNotificationService;
use App\services\UploadService;

final class AdminTitlesController extends AdminBaseController
{
    private const MANAGE_ROLES = ['SUPER_ADMIN', 'ADMIN', 'EDITOR'];

    public function index(): void
    {
        $this->guard();

        $model = new Title();
        $planModel = new BillingPlan();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'genre' => trim((string) ($_GET['genre'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 12;

        try {
            $result = $model->paginate($filters, $page, $perPage);
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel carregar titulos.');
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

        $this->render('admin/titles', [
            'title' => 'Titles | WisePlay Admin',
            'pageTitle' => 'Titles',
            'items' => $result['items'],
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
            'types' => $model->types(),
            'statuses' => $model->statuses(),
            'genres' => $model->listGenres(),
            'planOptions' => $planModel->listActiveTitlePlans(),
            'defaultPlanId' => (int) (($planModel->findDefaultFreePlan()['id'] ?? 0)),
            'canManageTitles' => in_array((string) (Auth::user()['role'] ?? ''), self::MANAGE_ROLES, true),
        ]);
    }

    public function create(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $model = new Title();
        $planModel = new BillingPlan();
        $payload = $this->readPayload();
        $payload['billing_plan_id'] = $this->resolveBillingPlanId((int) ($payload['billing_plan_id'] ?? 0), $planModel);
        $errors = $this->validatePayload($payload, $model, $planModel, true);

        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos obrigatorios.', 'errors' => $errors], 422);
        }

        $coverImage = $payload['cover_image'];
        $backdropImage = $payload['backdrop_image'];

        if (isset($_FILES['cover_file']) && is_array($_FILES['cover_file']) && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = (new UploadService())->storeImage($_FILES['cover_file'], 'cover');
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload da capa.')], 422);
            }
            $coverImage = (string) ($upload['path'] ?? '');
        }

        if (isset($_FILES['backdrop_file']) && is_array($_FILES['backdrop_file']) && (int) ($_FILES['backdrop_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = (new UploadService())->storeImage($_FILES['backdrop_file'], 'backdrop');
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload do backdrop.')], 422);
            }
            $backdropImage = (string) ($upload['path'] ?? '');
        }

        try {
            $id = $model->create([
                'title_name' => $payload['title_name'],
                'slug' => $payload['slug'],
                'type' => $payload['type'],
                'billing_plan_id' => $payload['billing_plan_id'],
                'description' => $payload['description'],
                'genre' => $payload['genre'],
                'year' => $payload['year'],
                'maturity_rating' => $payload['maturity_rating'],
                'featured' => $payload['featured'],
                'status' => $payload['status'],
                'cover_image' => $coverImage,
                'backdrop_image' => $backdropImage,
                'producer_id' => Auth::producerId(),
                'created_by_user_id' => (int) (Auth::user()['id'] ?? 0),
            ]);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao criar titulo.'], 500);
        }

        try {
            $createdTitle = $model->findById($id);
            if (is_array($createdTitle)) {
                (new PublicNotificationService())->notifyTitleCreated($createdTitle);
            }
        } catch (\Throwable) {
            // notification must not block admin create flow
        }

        $this->audit('title.create', 'title', (string) $id, [
            'title_name' => $payload['title_name'],
            'slug' => $payload['slug'],
            'type' => $payload['type'],
            'billing_plan_id' => $payload['billing_plan_id'],
            'status' => $payload['status'],
        ]);

        Response::json(['ok' => true, 'message' => 'Titulo criado com sucesso.', 'title_id' => $id]);
    }

    public function update(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID invalido.'], 422);
        }

        $model = new Title();
        $planModel = new BillingPlan();
        $existing = $model->findById($id);
        if (!$existing) {
            Response::json(['ok' => false, 'message' => 'Titulo nao encontrado.'], 404);
        }

        $payload = $this->readPayload();
        $payload['id'] = $id;
        $payload['billing_plan_id'] = $this->resolveBillingPlanId((int) ($payload['billing_plan_id'] ?? 0), $planModel);

        $errors = $this->validatePayload($payload, $model, $planModel, false);
        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos obrigatorios.', 'errors' => $errors], 422);
        }

        $coverImage = $payload['cover_image'] !== '' ? $payload['cover_image'] : (string) ($existing['cover_image'] ?? '');
        $backdropImage = $payload['backdrop_image'] !== '' ? $payload['backdrop_image'] : (string) ($existing['backdrop_image'] ?? '');

        if (isset($_FILES['cover_file']) && is_array($_FILES['cover_file']) && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = (new UploadService())->storeImage($_FILES['cover_file'], 'cover');
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload da capa.')], 422);
            }
            $coverImage = (string) ($upload['path'] ?? '');
        }

        if (isset($_FILES['backdrop_file']) && is_array($_FILES['backdrop_file']) && (int) ($_FILES['backdrop_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = (new UploadService())->storeImage($_FILES['backdrop_file'], 'backdrop');
            if (($upload['ok'] ?? false) !== true) {
                Response::json(['ok' => false, 'message' => (string) ($upload['message'] ?? 'Falha no upload do backdrop.')], 422);
            }
            $backdropImage = (string) ($upload['path'] ?? '');
        }

        try {
            $model->update($id, [
                'title_name' => $payload['title_name'],
                'slug' => $payload['slug'],
                'type' => $payload['type'],
                'billing_plan_id' => $payload['billing_plan_id'],
                'description' => $payload['description'],
                'genre' => $payload['genre'],
                'year' => $payload['year'],
                'maturity_rating' => $payload['maturity_rating'],
                'featured' => $payload['featured'],
                'status' => $payload['status'],
                'cover_image' => $coverImage,
                'backdrop_image' => $backdropImage,
            ]);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao atualizar titulo.'], 500);
        }

        $this->audit('title.update', 'title', (string) $id, [
            'title_name' => $payload['title_name'],
            'slug' => $payload['slug'],
            'type' => $payload['type'],
            'billing_plan_id' => $payload['billing_plan_id'],
            'status' => $payload['status'],
        ]);

        Response::json(['ok' => true, 'message' => 'Titulo atualizado com sucesso.']);
    }

    public function delete(): void
    {
        $this->authorize(self::MANAGE_ROLES);
        $this->requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::json(['ok' => false, 'message' => 'ID invalido.'], 422);
        }

        $model = new Title();
        $title = $model->findById($id);
        if (!$title) {
            Response::json(['ok' => false, 'message' => 'Titulo nao encontrado.'], 404);
        }

        try {
            $model->delete($id);
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao excluir titulo.'], 500);
        }

        $this->audit('title.delete', 'title', (string) $id, [
            'title_name' => $title['title_name'],
            'slug' => $title['slug'],
        ]);

        Response::json(['ok' => true, 'message' => 'Titulo excluido com sucesso.']);
    }

    private function readPayload(): array
    {
        return [
            'title_name' => trim((string) ($_POST['title_name'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'type' => strtoupper(trim((string) ($_POST['type'] ?? 'MOVIE'))),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'genre' => trim((string) ($_POST['genre'] ?? 'General')),
            'billing_plan_id' => (int) ($_POST['billing_plan_id'] ?? 0),
            'year' => (int) ($_POST['year'] ?? 0),
            'maturity_rating' => trim((string) ($_POST['maturity_rating'] ?? '16')),
            'featured' => (int) ($_POST['featured'] ?? 0) === 1 ? 1 : 0,
            'status' => strtoupper(trim((string) ($_POST['status'] ?? 'DRAFT'))),
            'cover_image' => trim((string) ($_POST['cover_image'] ?? '')),
            'backdrop_image' => trim((string) ($_POST['backdrop_image'] ?? '')),
        ];
    }

    private function validatePayload(array $payload, Title $model, BillingPlan $planModel, bool $isCreate): array
    {
        $errors = [];

        if (!Validator::required($payload['title_name']) || !Validator::minLength($payload['title_name'], 2)) {
            $errors['title_name'] = 'Nome do titulo e obrigatorio.';
        }

        if (!Validator::required($payload['slug']) || !Validator::slug($payload['slug'])) {
            $errors['slug'] = 'Slug invalido. Use letras minusculas, numeros e hifen.';
        } elseif ($model->slugExists($payload['slug'], $isCreate ? null : (int) ($payload['id'] ?? 0))) {
            $errors['slug'] = 'Este slug ja esta em uso.';
        }

        if (!Validator::inArray($payload['type'], $model->types())) {
            $errors['type'] = 'Tipo invalido.';
        }

        if (!Validator::inArray($payload['status'], $model->statuses())) {
            $errors['status'] = 'Status invalido.';
        }

        if ($payload['year'] !== 0 && !Validator::integer($payload['year'], 1900, 2100)) {
            $errors['year'] = 'Ano invalido.';
        }

        if (!Validator::required($payload['genre'])) {
            $errors['genre'] = 'Genero e obrigatorio.';
        }

        if (!Validator::required($payload['maturity_rating'])) {
            $errors['maturity_rating'] = 'Classificacao indicativa e obrigatoria.';
        }

        if ((int) ($payload['billing_plan_id'] ?? 0) <= 0 || !$planModel->findById((int) $payload['billing_plan_id'])) {
            $errors['billing_plan_id'] = 'Plano invalido.';
        }

        return $errors;
    }

    private function resolveBillingPlanId(int $billingPlanId, BillingPlan $planModel): int
    {
        if ($billingPlanId > 0 && $planModel->findById($billingPlanId)) {
            return $billingPlanId;
        }

        $defaultPlan = $planModel->findDefaultFreePlan();
        return (int) ($defaultPlan['id'] ?? 0);
    }
}
