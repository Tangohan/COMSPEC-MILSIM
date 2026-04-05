<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;

final class PortalSearchController
{
    public function index(Request $request, array $params = []): Response
    {
        $q = trim((string) $request->query('q', ''));

        return Response::view('layout.main', [
            'title' => 'Recherche portail',
            'content' => 'portal.search',
            'query' => $q,
        ]);
    }
}
