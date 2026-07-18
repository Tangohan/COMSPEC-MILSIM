<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Support\Api\ApiResponder;

/**
 * Version applicative courante — public (sondage client pour notification de mise à jour).
 */
final class SystemVersionController
{
    public function index(Request $request, array $params = []): Response
    {
        return ApiResponder::success([
            'version' => platform_app_version(),
        ]);
    }
}
