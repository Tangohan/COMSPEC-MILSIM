<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;

/** Compatibilité des anciens favoris : toute édition RH converge vers Effectifs. */
final class LegacyPersonnelRedirectController
{
    public function roster(Request $request, array $params = []): Response
    {
        $query = $_SERVER['QUERY_STRING'] ?? '';
        $target = effectifs_workspace_url();

        return Response::redirect($target . ($query !== '' ? '?' . $query : ''));
    }

    public function member(Request $request, array $params = []): Response
    {
        return Response::redirect(effectifs_workspace_url('membres/' . (int) ($params['id'] ?? 0)));
    }

    public function edit(Request $request, array $params = []): Response
    {
        return Response::redirect(effectifs_workspace_url('membres/' . (int) ($params['id'] ?? 0) . '/modifier'));
    }

    public function create(Request $request, array $params = []): Response
    {
        return Response::redirect(effectifs_workspace_url('nouveau'));
    }
}
