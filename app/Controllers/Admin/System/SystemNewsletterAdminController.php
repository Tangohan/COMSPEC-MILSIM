<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\NewsletterSubscriberRepository;

final class SystemNewsletterAdminController
{
    public function __construct(
        private NewsletterSubscriberRepository $newsletterRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $schemaReady = $this->newsletterRepository->schemaReady();
        $counts = $this->newsletterRepository->adminCountsByStatus();

        $statusFilter = (string) $request->query('statut', 'all');
        if (!in_array($statusFilter, ['all', 'pending', 'subscribed', 'unsubscribed'], true)) {
            $statusFilter = 'all';
        }
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) > 120) {
            $q = mb_substr($q, 0, 120);
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 35;

        $list = ['rows' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
        if ($schemaReady) {
            $list = $this->newsletterRepository->adminListSubscribers($statusFilter, $q, $page, $perPage);
        }

        return Response::view('layout.main', [
            'title' => 'Lettre d’information du site',
            'content' => 'admin.system.newsletter',
            'isPlatformAdminShell' => true,
            'newsletterSchemaReady' => $schemaReady,
            'newsletterCounts' => $counts,
            'newsletterRows' => $list['rows'],
            'newsletterTotal' => (int) ($list['total'] ?? 0),
            'newsletterStatut' => $statusFilter,
            'newsletterQuery' => $q,
            'newsletterPage' => (int) ($list['page'] ?? $page),
            'newsletterPerPage' => $perPage,
            'newsletterTotalPages' => (int) ($list['total_pages'] ?? 1),
        ]);
    }
}
