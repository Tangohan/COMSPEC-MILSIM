<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Support\AtakReleaseInfo;

/**
 * Dernières versions de l'ATAK natif et de COMSPEC Link, lues par le mod au démarrage puis toutes les 2 h
 * (DLL LatestVersions, fn_integrityCheck) pour signaler un mod ou une DLL périmés. Public, sans base de données.
 */
final class AtakVersionController
{
    public function latest(Request $request, array $params = []): Response
    {
        $info = AtakReleaseInfo::latest();
        // Ce qui change pour le joueur, montré dans la notification « mise à jour disponible » (COMSPEC Link 2.0.85+).
        $summary = AtakReleaseInfo::playerSummary((string) ($info['atak_native'] ?? ''));

        return Response::json([
            'ok' => $info['atak_native'] !== null,
            'atak_native' => $info['atak_native'] ?? '',
            'link' => $info['link'] ?? '',
            'summary_fr' => $summary['fr'],
            'summary_en' => $summary['en'],
            'download' => rtrim(url(''), '/') . '/atak/mod',
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
