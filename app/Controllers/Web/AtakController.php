<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\Tactical\AtakTokenService;

class AtakController
{
    public function __construct(
        private AtakTokenService $atakTokenService
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $token = $this->atakTokenService->generate();
        $nodeUrl = rtrim(env('NODE_ATAK_URL', 'http://localhost:3000'), '/');
        return Response::view('layout.main', [
            'content' => 'atak',
            'title' => 'Carte tactique ATAK',
            'atakToken' => $token,
            'nodeAtakUrl' => $nodeUrl,
        ]);
    }
}
