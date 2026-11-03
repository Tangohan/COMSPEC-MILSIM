<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Audit\AuditService;
use App\Services\Billing\QualificationCatalogQuota;
use App\Services\Personnel\QualificationCatalogImportWizard;
use App\Services\Personnel\QualificationPrerequisiteSuggester;
use App\Services\Personnel\QualificationReferenceWriter;
use App\Services\Platform\FeatureGateService;
use App\Services\Tenant\TenantContext;
use App\Support\QualificationCatalog\QualificationCatalog;
use Throwable;

/**
 * Assistant d'importation du catalogue de référence (Academy › Catalogue, onglets Qualifications et Brevets) :
 * données de la portée (GET), aperçu, démarrage puis lots d'import (POST, jeton CSRF). Mêmes droits que l'import
 * unitaire (admin.organization, admin.access, site.support), communauté de la session uniquement.
 *
 * Le plan d'import est calculé et gardé côté serveur (session) : chaque lot reprend là où le précédent s'est arrêté,
 * un lot renvoyé deux fois n'est pas retraité, et l'import (QualificationCatalogImporter) ne crée jamais de doublon.
 * Le plafond de la formule est relu à chaque lot : import partiel jusqu'au plafond, le reste est signalé.
 */
final class AcademyCatalogImportController
{
    private const JOBS = 'academy_catalog_import_jobs';

    /** Données de l'assistant : lignes du catalogue (déjà dans le référentiel ou non), choix de portée, formule. */
    public function data(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $lang = self::lang();
        $wizard = $this->wizard();
        $rows = QualificationCatalogImportWizard::rows($lang, $wizard->importer()->importedCodes($tenantId));
        $quota = $this->quota($tenantId);

        return self::json([
            'ok' => true,
            'rows' => $rows,
            'options' => QualificationCatalogImportWizard::options($rows, $lang),
            'packs' => self::packCounts(),
            'quota' => $quota->status(QualificationCatalogQuota::IMPORTS),
            'bulk' => $quota->bulk(),
            'bulk_offer' => QualificationCatalogQuota::upgradeOffer(QualificationCatalogQuota::BULK),
            'batch' => QualificationCatalogImportWizard::BATCH,
        ]);
    }

    /** Aperçu : éléments, préalables manquants, émetteurs, prérequis détectés, cours liés, places de la formule. */
    public function preview(Request $request, array $params = []): Response
    {
        $tenantId = $this->guardPost($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $choice = self::choice($request);
        if ($choice['ids'] === []) {
            return self::json(['ok' => false, 'code' => 'empty'], 422);
        }
        $lang = self::lang();
        $plan = $this->wizard()->plan($tenantId, $choice['ids'], $choice['deps'], $lang);
        $quota = $this->quota($tenantId);
        $new = array_values(array_filter($plan['order'], static fn (string $id): bool => !$plan['items'][$id]['already']));
        $prereqs = [];
        try {
            $planned = array_fill_keys($plan['order'], true);
            $prereqs = Container::get(QualificationPrerequisiteSuggester::class)->forCatalogEntries($tenantId, $new, $planned, $lang);
        } catch (Throwable $e) {
            error_log('Assistant d’importation (aperçu des prérequis) : ' . $e->getMessage());
        }
        $items = [];
        foreach ($plan['order'] as $id) {
            $it = $plan['items'][$id];
            $items[] = $it + ['prereqs_sure' => $prereqs[$id]['sure'] ?? [], 'prereqs_other' => (int) ($prereqs[$id]['other'] ?? 0)];
        }

        return self::json([
            'ok' => true,
            'items' => $items,
            'missing' => $plan['missing'],
            'issuers' => $plan['issuers'],
            'courses' => array_map(static fn (array $c): array => $c + ['url' => url('academy/catalogue') . '?' . http_build_query(['onglet' => 'formations', 'fiche' => 'a-' . $c['key']])], $plan['courses']),
            'counts' => ['to_create' => $plan['to_create'], 'already' => $plan['already'], 'dependencies' => $plan['dependencies'], 'unknown' => $plan['unknown'],
                'prereqs_sure' => array_sum(array_map(static fn (array $p): int => count($p['sure']), $prereqs))],
            'quota' => self::quotaView($quota, $plan['to_create']),
            'bulk' => self::bulkView($quota, $choice['ids']),
        ]);
    }

    /** Démarrage : plan recalculé côté serveur, contrôles de la formule, puis gardé en session pour les lots. */
    public function start(Request $request, array $params = []): Response
    {
        $tenantId = $this->guardPost($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $choice = self::choice($request);
        if ($choice['ids'] === []) {
            return self::json(['ok' => false, 'code' => 'empty'], 422);
        }
        $quota = $this->quota($tenantId);
        $bulk = self::bulkView($quota, $choice['ids']);
        if ($bulk['locked']) {
            return self::json(['ok' => false, 'code' => 'bulk', 'bulk' => $bulk], 403);
        }
        $plan = $this->wizard()->plan($tenantId, $choice['ids'], $choice['deps'], self::lang());
        $view = self::quotaView($quota, $plan['to_create']);
        if ($plan['to_create'] > 0 && $view['will_create'] === 0) {
            return self::json(['ok' => false, 'code' => 'quota', 'quota' => $view], 403);
        }
        $job = bin2hex(random_bytes(8));
        $jobs = Session::get(self::JOBS, []);
        $jobs = is_array($jobs) ? array_slice($jobs, -2, null, true) : [];
        $jobs[$job] = [
            'tenant' => $tenantId,
            'user' => (int) Session::get('user_id'),
            'order' => $plan['order'],
            'pos' => 0,
            'prereqs' => $choice['prereqs'],
            'started' => time(),
            'report' => ['created' => [], 'skipped' => [], 'blocked' => [], 'errors' => [], 'prereqs_added' => 0, 'issuers' => []],
        ];
        Session::set(self::JOBS, $jobs);
        $this->audit('academy.catalog_import_start', $tenantId, ['job' => $job, 'total' => count($plan['order']), 'to_create' => $plan['to_create'], 'dependencies' => $choice['deps'], 'prereqs' => $choice['prereqs']]);

        return self::json(['ok' => true, 'job' => $job, 'total' => count($plan['order']), 'batch' => QualificationCatalogImportWizard::BATCH, 'quota' => $view]);
    }

    /** Un lot : les éléments suivants du plan. Un « from » déjà traité renvoie l'état sans rien refaire. */
    public function batch(Request $request, array $params = []): Response
    {
        $tenantId = $this->guardPost($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $jobId = $request->inputString('job');
        $jobs = Session::get(self::JOBS, []);
        $job = is_array($jobs) ? ($jobs[$jobId] ?? null) : null;
        if (!is_array($job) || (int) $job['tenant'] !== $tenantId || (int) $job['user'] !== (int) Session::get('user_id')) {
            return self::json(['ok' => false, 'code' => 'job'], 404);
        }
        $from = (int) $request->inputString('from', '0');
        $total = count($job['order']);
        $lang = self::lang();
        $lot = null;
        if ($from === (int) $job['pos'] && $job['pos'] < $total) {
            $remaining = $this->quota($tenantId)->remaining(QualificationCatalogQuota::IMPORTS);
            $ids = array_slice($job['order'], (int) $job['pos'], QualificationCatalogImportWizard::BATCH);
            try {
                $lot = $this->wizard()->batch($tenantId, $job['order'], (int) $job['pos'], $lang, (int) Session::get('user_id') ?: null, $remaining);
                $job['report']['created'] += $lot['created'];
                $job['report']['skipped'] += $lot['skipped'];
                $job['report']['blocked'] = array_values(array_unique(array_merge($job['report']['blocked'], $lot['blocked'])));
                $job['report']['errors'] = array_values(array_unique(array_merge($job['report']['errors'], $lot['unknown'])));
                $job['report']['issuers'] = array_values(array_unique(array_merge($job['report']['issuers'], $lot['issuer_ids'])));
                if ($job['prereqs'] === 'sure' && $lot['created'] !== []) {
                    try {
                        $job['report']['prereqs_added'] += Container::get(QualificationPrerequisiteSuggester::class)->applySure($tenantId, array_values($lot['created']), (int) Session::get('user_id') ?: null, $lang)['added'];
                    } catch (Throwable $e) {
                        error_log('Assistant d’importation (prérequis sûrs) : ' . $e->getMessage());
                    }
                }
                $job['pos'] = $lot['next'];
            } catch (Throwable $e) {
                error_log('Assistant d’importation (lot) : ' . $e->getMessage());
                // Lot en erreur : signalé, l'import continue ; une relance de l'assistant le reprend sans doublon.
                $job['report']['errors'] = array_values(array_unique(array_merge($job['report']['errors'], $ids)));
                $job['pos'] = (int) $job['pos'] + count($ids);
            }
            $this->audit('academy.catalog_import_batch', $tenantId, ['job' => $jobId, 'from' => $from, 'created' => array_keys($lot['created'] ?? []),
                'skipped' => count($lot['skipped'] ?? []), 'blocked' => count($lot['blocked'] ?? []), 'errors' => $lot === null ? count($ids) : count($lot['unknown'])]);
        }
        $done = (int) $job['pos'] >= $total;
        if ($done && empty($job['finished'])) {
            $job['finished'] = time();
            $this->audit('academy.catalog_import_done', $tenantId, ['job' => $jobId, 'total' => $total, 'created' => count($job['report']['created']),
                'skipped' => count($job['report']['skipped']), 'blocked' => count($job['report']['blocked']), 'errors' => count($job['report']['errors']), 'prereqs_added' => $job['report']['prereqs_added']]);
        }
        $jobs[$jobId] = $job;
        Session::set(self::JOBS, $jobs);

        return self::json([
            'ok' => true,
            'pos' => (int) $job['pos'],
            'total' => $total,
            'done' => $done,
            'lot' => $lot === null ? null : ['created' => count($lot['created']), 'skipped' => count($lot['skipped']), 'blocked' => count($lot['blocked']), 'errors' => count($lot['unknown'])],
            'report' => $done ? $this->report($job, $tenantId, $lang) : null,
        ]);
    }

    /**
     * Rapport final : importés (avec le lien vers leur fiche), ignorés, bloqués par la formule, erreurs.
     *
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private function report(array $job, int $tenantId, string $lang): array
    {
        $name = static fn (string $id): string => QualificationCatalog::pick(QualificationCatalog::findRaw($id)['name'] ?? $id, $lang);
        $link = static fn (string $id, int $def): array => ['id' => $id, 'name' => $name($id),
            'url' => $def > 0 ? url('academy/catalogue') . '?fiche=' . rawurlencode('q-' . $def) : '',
            'admin_url' => $def > 0 ? url('back-office/referentiels/qualifications/' . $def) : ''];
        $r = $job['report'];
        $created = array_map('intval', array_values($r['created']));
        $quota = $this->quota($tenantId);

        return [
            'created' => array_map($link, array_keys($r['created']), array_values($r['created'])),
            'skipped' => array_map($link, array_keys($r['skipped']), array_values($r['skipped'])),
            'blocked' => array_map(static fn (string $id): array => ['id' => $id, 'name' => $name($id), 'url' => url('academy/catalogue') . '?fiche=' . rawurlencode('r-' . $id)], $r['blocked']),
            'errors' => array_map(static fn (string $id): array => ['id' => $id, 'name' => $name($id)], $r['errors']),
            'prereqs_added' => (int) $r['prereqs_added'],
            'prerequisites_url' => $created !== [] ? url('back-office/referentiels/qualifications/prerequis-detectes') . '?ids=' . implode(',', array_slice($created, 0, 150)) : '',
            'referentiel_url' => url('back-office/referentiels/qualifications') . (count($r['issuers']) === 1 ? '?emetteur=' . (int) $r['issuers'][0] : ''),
            'catalogue_url' => url('academy/catalogue') . '?onglet=qualifications&origine=referentiel',
            'quota' => $quota->status(QualificationCatalogQuota::IMPORTS),
        ];
    }

    /**
     * Choix envoyé par la page : éléments retenus (portée appliquée dans la page, revérifiés ici), qualifications
     * préalables à importer aussi, prérequis sûrs à ajouter.
     *
     * @return array{ids: list<string>, deps: bool, prereqs: string}
     */
    private static function choice(Request $request): array
    {
        $scope = QualificationCatalogImportWizard::scope(['ids' => $request->input('ids', [])]);

        return [
            'ids' => array_slice($scope['ids'], 0, 2000),
            'deps' => $request->inputString('deps') === '1',
            'prereqs' => $request->inputString('prereqs') === 'sure' ? 'sure' : 'none',
        ];
    }

    /** @return array<string, mixed> */
    private static function quotaView(QualificationCatalogQuota $quota, int $toCreate): array
    {
        $remaining = $quota->remaining(QualificationCatalogQuota::IMPORTS);
        $will = $remaining === null ? $toCreate : min($toCreate, $remaining);

        return $quota->status(QualificationCatalogQuota::IMPORTS) + ['to_create' => $toCreate, 'will_create' => $will, 'over' => $toCreate - $will];
    }

    /**
     * « Importer un pays entier en un clic » : la sélection couvre tout un pays (ou l'OTAN) ; réservé aux formules qui
     * l'incluent.
     *
     * @param list<string> $ids
     * @return array{packs: list<array{pack: string, label: string}>, allowed: bool, locked: bool, offer: array<string, mixed>|null, upgrade_url: string}
     */
    private static function bulkView(QualificationCatalogQuota $quota, array $ids): array
    {
        $packs = [];
        foreach (QualificationCatalogImportWizard::wholePacks($ids) as $pack) {
            $country = (string) (QualificationCatalog::pack($pack)['country'] ?? '');
            $packs[] = ['pack' => $pack, 'label' => QualificationCatalog::countryLabel($country, self::lang())];
        }

        return ['packs' => $packs, 'allowed' => $quota->bulk(), 'locked' => $packs !== [] && !$quota->bulk(),
            'offer' => QualificationCatalogQuota::upgradeOffer(QualificationCatalogQuota::BULK), 'upgrade_url' => QualificationCatalogQuota::upgradeUrl()];
    }

    /** @return array<string, int> pays => nombre d'éléments du catalogue */
    private static function packCounts(): array
    {
        $out = [];
        foreach (QualificationCatalog::packs() as $data) {
            $cc = (string) ($data['country'] ?? '');
            if ($cc !== '') {
                $out[$cc] = count($data['entries'] ?? []);
            }
        }

        return $out;
    }

    /** Mêmes droits que l'import unitaire depuis la fiche du catalogue Academy. */
    public static function allowed(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');
    }

    private function guard(): int|Response
    {
        $tenantId = TenantContext::id();
        if ($tenantId < 1 || (int) Session::get('user_id') < 1) {
            return self::json(['ok' => false, 'code' => 'session'], 401);
        }
        if (!self::allowed()) {
            return self::json(['ok' => false, 'code' => 'forbidden'], 403);
        }

        return $tenantId;
    }

    private function guardPost(Request $request): int|Response
    {
        if (!Csrf::verify($request)) {
            return self::json(['ok' => false, 'code' => 'session'], 419);
        }

        return $this->guard();
    }

    private function wizard(): QualificationCatalogImportWizard
    {
        return new QualificationCatalogImportWizard(new QualificationReferenceWriter(Database::getPdo()));
    }

    private function quota(int $tenantId): QualificationCatalogQuota
    {
        try {
            return QualificationCatalogQuota::forTenant($tenantId, Database::getPdo(), Container::get(FeatureGateService::class), Gate::getInstance()->isPlatformAdmin());
        } catch (Throwable) {
            return QualificationCatalogQuota::unlimited();
        }
    }

    /** @param array<string, mixed> $details */
    private function audit(string $action, int $tenantId, array $details): void
    {
        try {
            Container::get(AuditService::class)->logChange($action, $tenantId, (int) Session::get('user_id') ?: null, 'qualification_catalog_import', null, [], $details);
        } catch (Throwable $e) {
            // Le journal ne bloque jamais l'import lui-même.
            error_log('[audit] ' . $action . ' : ' . $e->getMessage());
        }
    }

    private static function lang(): string
    {
        return QualificationCatalog::lang(function_exists('locale') ? (string) locale() : 'fr');
    }

    /** @param array<string, mixed> $data */
    private static function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status)->header('Cache-Control', 'private, no-store')->header('Vary', 'Cookie');
    }
}
