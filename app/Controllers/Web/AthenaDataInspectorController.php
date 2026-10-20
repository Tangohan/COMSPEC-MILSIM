<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Api\AthenaInspectorMetricsController;
use App\Core\Request;
use App\Core\Response;

/**
 * Data Inspector — back-office ATAK (/back-office/atak/data-inspector).
 * En-tête, fil d'Ariane et feuille de style : config/back_office_pages.php.
 */
final class AthenaDataInspectorController
{
    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'title' => 'Data Inspector',
            'content' => 'atak.data_inspector',
            'isBackOfficeShell' => true,
            'inspectorCanPlatform' => AthenaInspectorMetricsController::canSeePlatform(),
        ]);
    }

    public function legacyRedirect(Request $request, array $params = []): Response
    {
        return Response::redirect(url('back-office/atak/data-inspector'));
    }
}
