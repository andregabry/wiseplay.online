<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\models\Offer;
use App\models\Title;

final class AdminProducerOffersController extends AdminBaseController
{
    public function index(): void
    {
        $producer = $this->requireProducerContext();
        $offerModel = new Offer();
        $titleModel = new Title();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'offer_type' => trim((string) ($_GET['offer_type'] ?? '')),
            'title_id' => (int) ($_GET['title_id'] ?? 0),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 12;
        $result = $offerModel->paginateForProducer((int) $producer['id'], $filters, $page, $perPage);
        $total = (int) ($result['total'] ?? 0);
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            $page = $lastPage;
            $result = $offerModel->paginateForProducer((int) $producer['id'], $filters, $page, $perPage);
        }

        $this->render('admin/producer_offers', [
            'title' => 'Producer Offers | WisePlay Admin',
            'pageTitle' => 'Producer Offers',
            'pageDescription' => 'Crie e gerencie ofertas pagas por titulo, mantendo o catalogo isolado por produtor.',
            'pageEyebrow' => 'Marketplace',
            'pageIcon' => 'ri-price-tag-3-line',
            'producer' => $producer,
            'items' => $result['items'] ?? [],
            'filters' => $filters,
            'types' => $offerModel->types(),
            'intervals' => $offerModel->intervals(),
            'statuses' => $offerModel->statuses(),
            'titleOptions' => $offerModel->titleOptionsForProducer((int) $producer['id']),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
        ]);
    }

    public function create(): void
    {
        $producer = $this->requireProducerContext();
        $this->requireCsrf();

        $offerModel = new Offer();
        $titleModel = new Title();
        $payload = $this->normalizePayload($_POST);
        $errors = $this->validatePayload($payload, $producer, $titleModel);

        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos da oferta.', 'errors' => $errors], 422);
        }

        $id = $offerModel->create($payload);
        $this->audit('producer.offer.create', 'offer', (string) $id, [
            'producer_id' => (int) $producer['id'],
            'title_id' => (int) $payload['title_id'],
            'offer_type' => (string) $payload['offer_type'],
        ]);

        Response::json(['ok' => true, 'message' => 'Oferta criada com sucesso.', 'id' => $id]);
    }

    public function update(): void
    {
        $producer = $this->requireProducerContext();
        $this->requireCsrf();

        $offerId = (int) ($_POST['id'] ?? 0);
        if ($offerId <= 0) {
            Response::json(['ok' => false, 'message' => 'Oferta invalida.'], 422);
        }

        $offerModel = new Offer();
        $existing = $offerModel->findByIdForProducer($offerId, (int) $producer['id']);
        if (!$existing) {
            Response::json(['ok' => false, 'message' => 'Oferta nao encontrada para este produtor.'], 404);
        }

        $titleModel = new Title();
        $payload = $this->normalizePayload($_POST);
        $errors = $this->validatePayload($payload, $producer, $titleModel);

        if ($errors !== []) {
            Response::json(['ok' => false, 'message' => 'Verifique os campos da oferta.', 'errors' => $errors], 422);
        }

        $offerModel->update($offerId, (int) $producer['id'], $payload);
        $this->audit('producer.offer.update', 'offer', (string) $offerId, [
            'producer_id' => (int) $producer['id'],
            'title_id' => (int) $payload['title_id'],
            'offer_type' => (string) $payload['offer_type'],
        ]);

        Response::json(['ok' => true, 'message' => 'Oferta atualizada com sucesso.']);
    }

    public function delete(): void
    {
        $producer = $this->requireProducerContext();
        $this->requireCsrf();

        $offerId = (int) ($_POST['id'] ?? 0);
        if ($offerId <= 0) {
            Response::json(['ok' => false, 'message' => 'Oferta invalida.'], 422);
        }

        $offerModel = new Offer();
        $existing = $offerModel->findByIdForProducer($offerId, (int) $producer['id']);
        if (!$existing) {
            Response::json(['ok' => false, 'message' => 'Oferta nao encontrada para este produtor.'], 404);
        }

        $offerModel->delete($offerId, (int) $producer['id']);
        $this->audit('producer.offer.delete', 'offer', (string) $offerId, [
            'producer_id' => (int) $producer['id'],
            'title_id' => (int) ($existing['title_id'] ?? 0),
        ]);

        Response::json(['ok' => true, 'message' => 'Oferta removida com sucesso.']);
    }

    private function normalizePayload(array $input): array
    {
        $offerType = strtoupper(trim((string) ($input['offer_type'] ?? 'ONE_TIME')));
        $billingInterval = strtoupper(trim((string) ($input['billing_interval'] ?? '')));
        if ($offerType === 'ONE_TIME') {
            $billingInterval = '';
        }

        return [
            'producer_id' => (int) (Auth::producerId() ?? 0),
            'title_id' => (int) ($input['title_id'] ?? 0),
            'offer_type' => $offerType,
            'billing_interval' => $billingInterval,
            'currency' => strtoupper(trim((string) ($input['currency'] ?? 'BRL'))),
            'price' => (float) ($input['price'] ?? 0),
            'status' => strtoupper(trim((string) ($input['status'] ?? 'ACTIVE'))),
            'is_selectable' => (int) (($input['is_selectable'] ?? 1) ? 1 : 0),
        ];
    }

    private function validatePayload(array $payload, array $producer, Title $titleModel): array
    {
        $errors = [];

        if ((int) $payload['title_id'] <= 0) {
            $errors['title_id'] = 'Titulo obrigatorio.';
        } elseif (!$titleModel->findByIdForProducer((int) $payload['title_id'], (int) $producer['id'])) {
            $errors['title_id'] = 'O titulo precisa pertencer ao produtor autenticado.';
        }

        if (!in_array((string) $payload['offer_type'], Offer::TYPES, true)) {
            $errors['offer_type'] = 'Tipo de oferta invalido.';
        }

        if ((string) $payload['offer_type'] === 'SUBSCRIPTION' && !in_array((string) $payload['billing_interval'], Offer::INTERVALS, true)) {
            $errors['billing_interval'] = 'Periodicidade obrigatoria para assinatura.';
        }

        if ((string) $payload['currency'] === '' || strlen((string) $payload['currency']) !== 3) {
            $errors['currency'] = 'Moeda invalida.';
        }

        if ((float) $payload['price'] <= 0) {
            $errors['price'] = 'Preco deve ser maior que zero.';
        }

        if (!in_array((string) $payload['status'], Offer::STATUSES, true)) {
            $errors['status'] = 'Status invalido.';
        }

        return $errors;
    }
}
