<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Rank\RankCatalogService;
use App\Services\Rank\RankReferenceValidator;

/**
 * Audit / catalogue canonique des grades (OTAN non dérivé).
 */
final class RankCatalogAdminController
{
    public function __construct(
        private ?RankCatalogService $catalog = null,
        private ?RankReferenceValidator $validator = null,
    ) {
        $this->catalog ??= Container::get(RankCatalogService::class);
        $this->validator ??= Container::get(RankReferenceValidator::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        $branch = strtoupper(trim((string) $request->query('branch', 'ARMY')));
        if (!in_array($branch, ['ARMY', 'GENDARMERIE', 'ALL'], true)) {
            $branch = 'ARMY';
        }
        $rows = $branch === 'ALL'
            ? $this->catalog->listCatalog('FR', null)
            : $this->catalog->listCatalog('FR', $branch);

        $matrix = [];
        foreach (RankReferenceValidator::expectedFrArmy() as $name => $meta) {
            $matrix[] = [
                'canonical_name' => $name,
                'expected_nato' => $meta['nato_code'],
                'category' => $meta['category'],
                'hierarchy_order' => $meta['hierarchy_order'],
            ];
        }

        return Response::view('layout.main', [
            'title' => 'Catalogue des grades (OTAN)',
            'content' => 'admin.organization.referentiels.grades.catalog_audit',
            'catalogRows' => $rows,
            'expectedMatrix' => $matrix,
            'branchFilter' => $branch,
            'validator' => $this->validator,
        ]);
    }

    public function runAudit(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('back-office/referentiels/grades/catalogue'));
        }
        $repair = (string) $request->input('repair', '0') === '1';
        $stats = $this->catalog->bootstrapAndAudit($repair);
        Session::flash(
            'success',
            sprintf(
                'Catalogue : %d seed(s), %d audité(s), %d réparé(s), %d INVALID restant(s).',
                $stats['seeded'],
                $stats['audited'],
                $stats['repaired'],
                $stats['invalid']
            )
        );

        return Response::redirect(url('back-office/referentiels/grades/catalogue'));
    }
}
