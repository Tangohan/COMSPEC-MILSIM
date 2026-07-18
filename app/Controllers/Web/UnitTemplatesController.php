<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;

final class UnitTemplatesController
{
    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'title' => 'Modèles d’unité',
            'content' => 'portal.unit_templates',
        ]);
    }
}
