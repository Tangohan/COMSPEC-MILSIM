<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\BoSheetRepository;
use App\Repositories\BoTableDefRepository;
use App\Services\BackOffice\Home\SheetData;
use App\Services\BackOffice\Tables\DataTableQuery;
use App\Services\BackOffice\Tables\DataTableSources;
use App\Support\EffectifsLmsAccess;

/**
 * Tableaux de données : la personne choisit une source, des colonnes, des filtres, un tri et un regroupement, voit le
 * résultat, l'exporte en CSV, l'enregistre (et le partage) ou en fait un tableur modifiable.
 *
 * Droits : chaque source a son droit (DataTableSources::allowed) ; une définition enregistrée ou partagée est toujours
 * recalculée avec les droits de la personne qui l'ouvre. Le résultat ne dépend que de la communauté de la session.
 * La page elle-même est servie par EffectifsWorkspaceController::tableaux (via pageData()).
 */
final class EffectifsTablesController
{
    public function __construct(
        private ?BoTableDefRepository $defs = null,
        private ?BoSheetRepository $sheets = null,
        private ?DataTableSources $sources = null,
    ) {
        $this->defs ??= new BoTableDefRepository();
        $this->sheets ??= Container::get(BoSheetRepository::class);
        $this->sources ??= new DataTableSources();
    }

    /**
     * Données de la page (aucun SQL saisi : uniquement des clés du catalogue).
     *
     * @return array<string, mixed>
     */
    public function pageData(Request $request): array
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $gate = Gate::getInstance();
        $available = DataTableSources::availableFor($gate);
        $saved = null;
        $input = $this->inputOf($request);
        $notice = '';
        $savedId = (int) $request->query('t', 0);
        if ($savedId > 0) {
            $saved = $this->defs->find($tenantId, $savedId);
            if ($saved === null || (!$saved['shared'] && $saved['owner_user_id'] !== $userId)) {
                $saved = null;
                $notice = 'Ce tableau est introuvable ou n’est pas partagé avec vous.';
            } elseif (!isset($available[$saved['source']])) {
                $notice = 'Vous n’avez pas accès à la source de ce tableau (' . (DataTableSources::catalog()[$saved['source']]['label'] ?? $saved['source']) . ').';
                $saved = null;
            } else {
                $input = $saved['def'] + ['source' => $saved['source']];
            }
        }
        $source = (string) ($input['source'] ?? '');
        if (!isset($available[$source])) {
            $source = $available === [] ? '' : (string) array_key_first($available);
        }
        $result = null;
        $def = null;
        $truncated = false;
        if ($source !== '') {
            $columns = $available[$source]['columns'];
            $def = DataTableQuery::normalize($input, $source, $columns);
            if ($request->query('run', '') === '1' || $saved !== null) {
                $loaded = $this->sources->load($source, $tenantId);
                $result = DataTableQuery::run($loaded['rows'], $def, $columns);
                $truncated = $loaded['truncated'];
            }
        }

        return [
            'dtAvailable' => $available,
            'dtSource' => $source,
            'dtDef' => $def,
            'dtResult' => $result,
            'dtTruncated' => $truncated,
            'dtSaved' => $saved,
            'dtNotice' => $notice,
            'dtList' => $this->defs->listReadable($tenantId, $userId),
            'dtUserId' => $userId,
            'dtOps' => DataTableQuery::OPS,
            'dtAggs' => DataTableQuery::AGGS,
            'dtMaxRows' => DataTableSources::MAX_ROWS,
            'csrfToken' => Csrf::token(),
        ];
    }

    /** GET /back-office/ressources/effectifs/tableaux/export.csv */
    public function export(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardRead()) !== null) {
            return $denied;
        }
        [$def, $columns, $source] = $this->definition($this->inputOf($request));
        if ($def === null) {
            return $this->back(false, 'Vous n’avez pas accès à cette source de données.');
        }
        $loaded = $this->sources->load($source, (int) Session::get('tenant_id'));
        $res = DataTableQuery::run($loaded['rows'], $def, $columns);

        return (new Response())
            ->setBody(DataTableQuery::toCsv($res['cols'], $res['rows']))
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="tableau-' . $source . '.csv"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, no-store');
    }

    /** POST /back-office/ressources/effectifs/tableaux/enregistrer */
    public function save(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardWrite($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        [$def, , $source] = $this->definition($this->inputOf($request));
        if ($def === null) {
            return $this->back(false, 'Vous n’avez pas accès à cette source de données.');
        }
        $title = (string) $request->input('title', '');
        $shared = (string) $request->input('shared', '') === '1';
        $id = (int) $request->input('id', 0);
        try {
            if ($id > 0) {
                $existing = $this->defs->find($tenantId, $id);
                if ($existing === null || $existing['owner_user_id'] !== $userId) {
                    return $this->back(false, 'Seul l’auteur peut modifier ce tableau.');
                }
                $this->defs->update($tenantId, $id, $userId, $title, $source, $def, $shared);
            } else {
                if ($this->defs->countOwned($tenantId, $userId) >= BoTableDefRepository::MAX_PER_OWNER) {
                    return $this->back(false, sprintf('%d tableaux enregistrés au plus. Supprimez-en un avant d’en créer.', BoTableDefRepository::MAX_PER_OWNER));
                }
                $id = $this->defs->create($tenantId, $userId, $title, $source, $def, $shared);
            }
        } catch (\Throwable $e) {
            error_log('[tables] enregistrement : ' . $e->getMessage());

            return $this->back(false, 'Impossible d’enregistrer pour le moment (la mise à jour de la base est-elle faite ?).');
        }

        return $this->back(true, $shared ? 'Tableau enregistré et partagé avec la communauté.' : 'Tableau enregistré.', ['t' => $id]);
    }

    /** POST /back-office/ressources/effectifs/tableaux/{id}/supprimer */
    public function delete(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardWrite($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        $this->defs->delete($tenantId, (int) ($params['id'] ?? 0), $userId);

        return $this->back(true, 'Tableau supprimé.');
    }

    /** POST /back-office/ressources/effectifs/tableaux/tableur : copie modifiable (instantané) dans les tableaux de l'accueil. */
    public function toSheet(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardWrite($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        [$def, $columns, $source] = $this->definition($this->inputOf($request));
        if ($def === null) {
            return $this->back(false, 'Vous n’avez pas accès à cette source de données.');
        }
        if ($this->sheets->countOwned($tenantId, $userId) >= BoSheetRepository::MAX_PER_OWNER) {
            return $this->back(false, sprintf('%d tableurs au plus. Supprimez-en un avant d’en créer.', BoSheetRepository::MAX_PER_OWNER));
        }
        $loaded = $this->sources->load($source, $tenantId);
        $res = DataTableQuery::run($loaded['rows'], $def, $columns);
        $sheet = DataTableQuery::toSheet($res['cols'], $res['rows']);
        $title = trim((string) $request->input('title', '')) ?: ('Tableau · ' . (DataTableSources::catalog()[$source]['label'] ?? $source));
        try {
            $id = $this->sheets->create($tenantId, $userId, $title, $sheet['data']);
        } catch (\Throwable $e) {
            error_log('[tables] tableur : ' . $e->getMessage());

            return $this->back(false, 'Impossible de créer le tableur pour le moment.');
        }
        Session::flash('success', 'Tableur créé à partir d’un instantané des données' . ($sheet['truncated'] ? ' (limité à ' . SheetData::MAX_ROWS . ' lignes et ' . SheetData::MAX_COLS . ' colonnes).' : '.') . ' Il est privé tant que vous ne le partagez pas.');

        return Response::redirect(url('back-office/tableaux/' . $id));
    }

    /**
     * Définition normalisée, ou null si la source n'est pas permise à la personne connectée.
     *
     * @param array<string, mixed> $input
     * @return array{0: ?array<string, mixed>, 1: array<string, array{label: string, type: string}>, 2: string}
     */
    private function definition(array $input): array
    {
        $source = (string) ($input['source'] ?? '');
        if (!DataTableSources::allowed($source, Gate::getInstance())) {
            return [null, [], $source];
        }
        $columns = DataTableSources::catalog()[$source]['columns'];

        return [DataTableQuery::normalize($input, $source, $columns), $columns, $source];
    }

    /**
     * Définition lue dans la requête (GET ou POST) : source, cols[], f_col[]/f_op[]/f_val[], sort, dir, group, agg_col, agg_fn.
     *
     * @return array<string, mixed>
     */
    private function inputOf(Request $request): array
    {
        $get = static fn (string $k, mixed $d = ''): mixed => $request->input($k, $request->query($k, $d));
        $filters = [];
        $fc = (array) $get('f_col', []);
        $fo = (array) $get('f_op', []);
        $fv = (array) $get('f_val', []);
        foreach ($fc as $i => $col) {
            if ((string) $col === '') {
                continue;
            }
            $filters[] = ['col' => (string) $col, 'op' => (string) ($fo[$i] ?? 'contains'), 'value' => is_scalar($fv[$i] ?? null) ? (string) $fv[$i] : ''];
        }

        return [
            'source' => (string) $get('source'),
            'columns' => array_values(array_filter((array) $get('cols', []), 'is_string')),
            'filters' => $filters,
            'sort' => ['col' => (string) $get('sort'), 'dir' => (string) $get('dir')],
            'group' => ['col' => (string) $get('group'), 'agg_col' => (string) $get('agg_col'), 'agg_fn' => (string) $get('agg_fn')],
        ];
    }

    private function guardRead(): ?Response
    {
        if (!(int) Session::get('user_id') || !(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) {
            return $this->back(false, 'Accès réservé au pilotage des effectifs.');
        }

        return null;
    }

    private function guardWrite(Request $request): ?Response
    {
        if (($denied = $this->guardRead()) !== null) {
            return $denied;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            return $this->back(false, 'Session expirée. Rechargez la page.');
        }

        return null;
    }

    /** @param array<string, scalar> $query */
    private function back(bool $ok, string $message, array $query = []): Response
    {
        Session::flash($ok ? 'success' : 'error', $message);
        $qs = $query === [] ? '' : '?' . http_build_query($query);

        return Response::redirect(effectifs_workspace_url('tableaux') . $qs);
    }
}
