<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakBetaRegistrationRepository;

/**
 * Journal des accès anticipés (bêta) remontés par le pack Overwatch.
 */
final class AdminAtakBetaRegistrationsController
{
    public function __construct(
        private AtakBetaRegistrationRepository $betaRegistrationRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $rows = $this->betaRegistrationRepository->listRecent(250);
        $total = $this->betaRegistrationRepository->countAll();

        return Response::view('layout.main', [
            'title' => 'Accès anticipé Overwatch',
            'content' => 'admin.atak-beta-registrations.index',
            'rows' => $rows,
            'total' => $total,
        ]);
    }
}
