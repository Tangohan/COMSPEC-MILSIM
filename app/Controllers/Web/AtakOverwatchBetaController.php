<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AtakOverwatchBetaController
{
    public function index(Request $request, array $params = []): Response
    {
        $operator = trim((string) Session::get('display_name', ''));

        return Response::view('atak-overwatch-beta', [
            'operatorName' => $operator !== '' ? $operator : 'OPÉRATEUR ATHENA',
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
