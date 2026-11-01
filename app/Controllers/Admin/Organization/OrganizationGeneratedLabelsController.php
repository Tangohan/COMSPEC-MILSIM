<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantGeneratedLabelRepository;
use App\Services\Auth\AuthService;
use App\Services\I18n\GeneratedLabels;

/**
 * Back-office > Catalogue de l’organisation > Libellés : la communauté remplace, langue par langue, les libellés
 * générés par Athena (unités et emplois des modèles, postes types, types d’unité). Mêmes droits que le catalogue.
 */
final class OrganizationGeneratedLabelsController
{
    private const MAX_LENGTH = 150;

    public function __construct(
        private ?AuthService $authService = null,
        private ?TenantGeneratedLabelRepository $labels = null,
        private ?Gate $gate = null,
    ) {
        $this->authService ??= Container::get(AuthService::class);
        $this->labels ??= new TenantGeneratedLabelRepository();
        $this->gate ??= Gate::getInstance();
    }

    public function index(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');

        return Response::view('layout.main', [
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Personnel',
            'title' => 'Libellés des unités et postes',
            'boPageTitle' => 'Libellés des unités et postes',
            'boPageKicker' => 'PERSONNEL · STRUCTURE',
            'boPageSubtitle' => 'Les noms créés par Athena s’affichent dans la langue de chaque joueur. Remplacez-les si vous le souhaitez.',
            'backOfficePageCss' => ['back-office-catalog.css'],
            'showPortalFooter' => false,
            'content' => 'admin.organization.catalog.labels',
            'labelRows' => $this->rows($tenantId),
            'labelLangs' => GeneratedLabels::LANGS,
            'labelKinds' => GeneratedLabels::KINDS,
            'labelsReady' => $this->labels->tableExists(),
            'flashOk' => Session::getFlash('success'),
            'flashErr' => Session::getFlash('error'),
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }
        $back = Response::redirect(url('back-office/organisation/catalogue/libelles'));
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'La page a expiré : rechargez-la puis enregistrez de nouveau.');

            return $back;
        }
        if (!$this->labels->tableExists()) {
            Session::flash('error', 'La personnalisation des libellés n’est pas encore disponible : la mise à jour de la base doit d’abord être passée.');

            return $back;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id') ?: null;
        $now = date('Y-m-d H:i:s');
        $current = GeneratedLabels::overrides($tenantId);
        $changed = 0;

        $reset = trim((string) $request->input('reset', ''));
        if ($reset !== '' && GeneratedLabels::isKey($reset)) {
            $this->labels->delete($tenantId, $reset);
            GeneratedLabels::forget($tenantId);
            Session::flash('success', 'Libellé par défaut rétabli : « ' . GeneratedLabels::defaultLabel($reset, 'fr') . ' ».');

            return $back;
        }

        $submitted = $request->input('labels');
        foreach (is_array($submitted) ? $submitted : [] as $key => $byLang) {
            $key = (string) $key;
            if (!is_array($byLang) || !GeneratedLabels::isKey($key)) {
                continue;
            }
            foreach (array_keys(GeneratedLabels::LANGS) as $lang) {
                if (!array_key_exists($lang, $byLang)) {
                    continue;
                }
                $text = trim((string) preg_replace('/\s+/u', ' ', (string) $byLang[$lang]));
                $text = mb_substr($text, 0, self::MAX_LENGTH);
                // Identique au libellé du site : rien à personnaliser.
                if ($text === GeneratedLabels::defaultLabel($key, $lang)) {
                    $text = '';
                }
                if ($text === (string) ($current[$key][$lang] ?? '')) {
                    continue;
                }
                $this->labels->put($tenantId, $key, $lang, $text, $userId, $now);
                ++$changed;
            }
        }
        GeneratedLabels::forget($tenantId);
        Session::flash('success', $changed === 0
            ? 'Aucun changement à enregistrer.'
            : ($changed === 1 ? '1 libellé enregistré.' : $changed . ' libellés enregistrés.'));

        return $back;
    }

    /**
     * @return list<array{key: string, kind: string, defaults: array<string, string>, own: array<string, string>, used: bool}>
     */
    private function rows(int $tenantId): array
    {
        $own = GeneratedLabels::overrides($tenantId);
        $used = $this->usedKeys($tenantId);
        $rows = [];
        foreach (GeneratedLabels::keys() as $key) {
            $defaults = [];
            foreach (array_keys(GeneratedLabels::LANGS) as $lang) {
                $defaults[$lang] = GeneratedLabels::defaultLabel($key, $lang);
            }
            $rows[] = [
                'key' => $key,
                'kind' => substr($key, 0, (int) strpos($key, '.')),
                'defaults' => $defaults,
                'own' => array_intersect_key($own[$key] ?? [], GeneratedLabels::LANGS),
                'used' => isset($used[$key]),
            ];
        }
        // Libellés présents dans la communauté d’abord, puis ordre du catalogue.
        usort($rows, static fn (array $a, array $b): int => (int) $b['used'] <=> (int) $a['used']);

        return $rows;
    }

    /**
     * Clés des libellés générés présents dans la communauté (unités, emplois, postes, types encore à leur nom d’origine).
     *
     * @return array<string, true>
     */
    private function usedKeys(int $tenantId): array
    {
        $queries = [
            'unit' => 'SELECT slug, name FROM units WHERE tenant_id = ?',
            'category' => 'SELECT slug, name FROM personnel_job_role_categories WHERE tenant_id = ?',
            'job' => 'SELECT slug, name FROM personnel_job_roles WHERE tenant_id = ?',
            'post' => 'SELECT NULL AS slug, title AS name FROM orbat_billets WHERE tenant_id = ?',
            'unit_type' => 'SELECT code AS slug, label AS name FROM unit_type_definitions WHERE tenant_id = ?',
        ];
        $out = [];
        try {
            $pdo = Database::getPdo();
        } catch (\Throwable) {
            return [];
        }
        foreach ($queries as $kind => $sql) {
            try {
                $st = $pdo->prepare($sql);
                $st->execute([$tenantId]);
                while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                    $slug = $row['slug'] !== null ? (string) $row['slug'] : null;
                    foreach (GeneratedLabels::keysFor($kind, (string) ($row['name'] ?? ''), $slug) as $key) {
                        $out[$key] = true;
                    }
                }
            } catch (\Throwable) {
                // Table absente sur cette installation : rien d’utilisé pour ce type.
            }
        }

        return $out;
    }

    private function guard(): ?Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $allowed = $this->gate->allows('organization.catalog.manage')
            || $this->gate->allows('organization.orbat.manage')
            || $this->gate->allows('admin.organization')
            || $this->gate->allows('admin.access');
        if (!$allowed) {
            Session::flash('error', 'Vous n’avez pas l’habilitation pour administrer le catalogue de l’organisation.');

            return Response::redirect(url('back-office/organisation-effectifs'));
        }
        if ((int) Session::get('tenant_id') < 1) {
            return Response::redirect(url('dashboard'));
        }

        return null;
    }
}
