<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Personnel\QualificationAwardRuleService;
use App\Services\Personnel\QualificationPrerequisiteSuggester;
use Throwable;

/**
 * « Prérequis détectés » : aperçu sur tout le référentiel (ou les fiches qui viennent d'être importées), ajout en un
 * clic depuis l'aperçu ou la fiche d'une qualification, et attestation par un instructeur des conditions que le jeu ne
 * mesure pas (sauts en chute libre, épreuve de tir…).
 */
final class QualificationPrerequisiteController
{
    private const BASE = 'back-office/referentiels/qualifications';

    public function __construct(
        private QualificationPrerequisiteSuggester $suggester,
        private QualificationAwardRuleService $rules,
    ) {
    }

    /** Aperçu de masse : toutes les fiches qui ont des prérequis détectés, ou seulement celles de ?ids=. */
    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $ids = self::ids((string) $request->queryString('ids'));
        $rows = $this->suggester->forTenant($tenantId, self::lang(), $ids !== [] ? $ids : null);
        $back = (string) Session::get('qualif_prereq_back', '');

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.prerequisites_detected',
            'title' => 'Prérequis détectés',
            'backOfficePageCss' => ['referentiel-qualifications.css'],
            'boSkipPageHead' => true,
            'rows' => $rows,
            'filterIds' => $ids,
            'afterImport' => $request->queryString('apres_import') === '1',
            'backUrl' => $back !== '' && str_starts_with($back, url(self::BASE)) ? $back : url(self::BASE),
            'criterionNames' => $this->rules->names($tenantId),
            'ready' => $this->rules->schemaReady(),
        ]);
    }

    /** Ajout en masse depuis l'aperçu : pick[fiche][] = suggestion, value[fiche][suggestion] = valeur corrigée. */
    public function applyMany(Request $request, array $params = []): Response
    {
        $ids = self::ids((string) $request->input('ids', ''));
        $back = url(self::BASE . '/prerequis-detectes') . ($ids !== [] ? '?ids=' . implode(',', $ids) : '');

        return $this->post($request, $back, function (int $tenantId) use ($request): void {
            $picks = $request->input('pick', []);
            $values = $request->input('value', []);
            $added = 0;
            $defs = 0;
            $errors = [];
            foreach (is_array($picks) ? $picks : [] as $defId => $list) {
                $selected = self::selection($list, is_array($values) ? ($values[$defId] ?? []) : []);
                if ($selected === []) {
                    continue;
                }
                try {
                    $r = $this->suggester->apply($tenantId, (int) $defId, $selected, self::actor(), self::lang());
                    $added += $r['added'];
                    $defs += $r['added'] > 0 ? 1 : 0;
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
            if ($errors !== []) {
                Session::flash('error', implode(' ', array_unique($errors)));
            }
            Session::flash('success', $added > 0
                ? sprintf('%d prérequis ajouté(s) sur %d qualification(s).', $added, $defs)
                : 'Aucun prérequis ajouté : cochez ceux à garder.');
        });
    }

    /** Ajout depuis la fiche d'une qualification (formulaire du back-office ou fiche). */
    public function apply(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = (string) $request->input('return_to', '') === 'fiche'
            ? url(self::BASE . '/' . $id) : url(self::BASE . '/' . $id . '/edit') . '#prerequis-detectes';

        return $this->post($request, $back, function (int $tenantId) use ($request, $id): void {
            $values = $request->input('value', []);
            $r = $this->suggester->apply($tenantId, $id, self::selection($request->input('pick', []), is_array($values) ? $values : []), self::actor(), self::lang());
            Session::flash('success', $r['added'] > 0
                ? sprintf('%d prérequis ajouté(s) à la qualification.', $r['added'])
                : 'Aucun prérequis ajouté : cochez ceux à garder.');
        });
    }

    /** Un instructeur atteste (ou retire) une condition non mesurée pour un membre. */
    public function attest(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->post($request, url(self::BASE . '/' . $id) . '#attestations', function (int $tenantId) use ($request, $id): void {
            $raw = trim((string) $request->input('value', ''));
            Session::flash('success', $this->rules->attest(
                $tenantId,
                $id,
                (int) $request->input('user_id', 0),
                (string) $request->input('store_key', ''),
                $raw !== '' ? (float) str_replace(',', '.', $raw) : null,
                (string) $request->input('note', ''),
                self::actor() ?? 0,
                (string) $request->input('remove', '') === '1'
            ));
        });
    }

    /**
     * @param mixed $list suggestions cochées
     * @param array<mixed> $values valeurs saisies par suggestion
     * @return array<string, string|null>
     */
    private static function selection(mixed $list, array $values): array
    {
        $out = [];
        foreach (is_array($list) ? $list : [] as $sid) {
            if (is_string($sid) && preg_match('/\A[a-z]{1,2}(?::[A-Za-z0-9_.\-]*){0,5}\z/', $sid) === 1) {
                $v = $values[$sid] ?? null;
                $out[$sid] = is_scalar($v) ? (string) $v : null;
            }
        }

        return $out;
    }

    /** @return list<int> */
    private static function ids(string $raw): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)), static fn (int $i): bool => $i > 0)));

        return array_slice($ids, 0, 200);
    }

    private static function lang(): string
    {
        return function_exists('locale') && str_starts_with(strtolower((string) locale()), 'en') ? 'en' : 'fr';
    }

    private static function actor(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    /** @param callable(int): void $fn */
    private function post(Request $request, string $back, callable $fn): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        try {
            $fn($tenantId);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect($back);
    }
}
