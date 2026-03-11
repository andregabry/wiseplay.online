<?php

declare(strict_types=1);

namespace App\controllers;

use App\models\Title;

final class AdminProducerTitlesController extends AdminBaseController
{
    public function index(): void
    {
        $producer = $this->requireProducerContext();
        $titleModel = new Title();

        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;

        $result = $titleModel->paginateForProducer((int) $producer['id'], $filters, $page, $perPage);
        $total = (int) ($result['total'] ?? 0);
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            $page = $lastPage;
            $result = $titleModel->paginateForProducer((int) $producer['id'], $filters, $page, $perPage);
        }

        $this->render('admin/producer_titles', [
            'title' => 'Producer Titles | WisePlay Admin',
            'pageTitle' => 'Producer Titles',
            'pageDescription' => 'Catalogo isolado por tenant. Este painel lista apenas os titulos vinculados ao produtor autenticado.',
            'pageEyebrow' => 'Marketplace',
            'pageIcon' => 'ri-store-3-line',
            'producer' => $producer,
            'items' => $result['items'] ?? [],
            'filters' => $filters,
            'types' => $titleModel->types(),
            'statuses' => $titleModel->statuses(),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
        ]);
    }
}
