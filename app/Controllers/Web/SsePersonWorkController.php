<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SsePersonViewer;
use App\Services\Sse\SsePersonWorkService;
use App\Services\Sse\SseWantedAccess;
use App\Support\SsePersonWork;

/**
 * Page « Personnes » : tiroir d'aperçu et actions (classer, verrouiller, déverrouiller, clore, rouvrir, réglage de verrouillage
 * automatique, actions groupées, export CSV, rattachement à un dossier). Chaque écriture exige un jeton CSRF, la communauté de
 * la session et les droits de SsePersonWorkService ; le navigateur reçoit du JSON (fetch) ou une redirection (formulaire).
 */
final class SsePersonWorkController
{
    public const TOKEN_EXPIRED = 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.';

    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SsePersonWorkService $service = null,
        private ?SseCaseRepository $cases = null,
    ) {
        $this->access ??= new SseAccessCodeService();
        $this->service ??= new SsePersonWorkService();
    }

    private function viewer(): SsePersonViewer
    {
        return SsePersonViewer::fromSession($this->access);
    }

    private function wantsJson(Request $request): bool
    {
        return str_contains((string) $request->header('Accept'), 'application/json') || $request->header('X-Requested-With') === 'fetch';
    }

    /** @param array<string, mixed> $payload */
    private function answer(Request $request, array $payload, string $back, ?string $success = null): Response
    {
        $ok = (bool) ($payload['ok'] ?? false);
        if ($this->wantsJson($request)) {
            if ($ok && $success !== null) {
                Session::flash('success', $success);
            }

            return Response::json($payload, $ok ? 200 : 422);
        }
        Session::flash($ok ? 'success' : 'error', $ok ? (string) $success : (string) ($payload['error'] ?? 'Action refusée.'));

        return Response::redirect($back);
    }

    private function back(Request $request, int $personId = 0): string
    {
        $return = trim($request->inputString('return', ''));
        // Retour interne seulement (jamais une adresse extérieure).
        if ($return !== '' && str_starts_with($return, '/') && !str_starts_with($return, '//') && !str_contains($return, "\n")) {
            return $return;
        }

        return url('atak/sse/identites' . ($personId > 0 ? '/' . $personId : ''));
    }

    // ------------------------------------------------------------------ Tiroir

    /** Aperçu d'une fiche (fragment HTML) : enregistre l'ouverture de ce compte. */
    public function drawer(Request $request, array $params = []): Response
    {
        $viewer = $this->viewer();
        $id = (int) ($params['id'] ?? 0);
        $detail = $this->service->detail($viewer, $id);
        if ($detail === null) {
            return $this->fragment('<div class="pe-drawer__missing" role="alert"><p>Cette personne est introuvable ou restreinte.</p></div>', 404);
        }
        $this->service->recordOpen($viewer, $id);

        return $this->fragment($this->render('persons/_drawer', [
            'd' => $detail,
            'viewer' => $viewer,
            'csrf' => Csrf::token(),
            'wantedHref' => $this->wantedHref(...),
        ]));
    }

    /** Adresse de la fiche du registre pour placer un avis ou en voir un. */
    private function wantedHref(?int $identityId): string
    {
        return $identityId === null ? '' : url('atak/sse/papiers/identites/' . $identityId . '/avis/nouveau');
    }

    // ------------------------------------------------------------------ Actions sur une fiche

    public function classify(Request $request, array $params = []): Response
    {
        return $this->single($request, $params, function (SsePersonViewer $v, int $id) use ($request): array {
            return $this->service->classify($v, $id, $this->nullable($request->inputString('level', '')), $request->inputString('reason', ''));
        }, 'Classification enregistrée.');
    }

    public function lock(Request $request, array $params = []): Response
    {
        return $this->single($request, $params, function (SsePersonViewer $v, int $id) use ($request): array {
            return $this->service->lock(
                $v,
                $id,
                $request->inputString('reason', ''),
                $this->nullable($request->inputString('until', '')),
                $this->nullable($request->inputString('level', ''))
            );
        }, 'Fiche verrouillée.');
    }

    public function unlock(Request $request, array $params = []): Response
    {
        return $this->single($request, $params, function (SsePersonViewer $v, int $id) use ($request): array {
            return $this->service->unlock($v, $id, $request->inputString('reason', ''));
        }, 'Fiche déverrouillée.');
    }

    public function close(Request $request, array $params = []): Response
    {
        return $this->single($request, $params, function (SsePersonViewer $v, int $id) use ($request): array {
            return $this->service->close(
                $v,
                $id,
                $request->inputString('reason_code', ''),
                $request->inputString('note', ''),
                $request->inputString('lift', '') === '1',
                SseWantedAccess::fromSession($this->access)
            );
        }, 'Dossier clos.');
    }

    public function reopen(Request $request, array $params = []): Response
    {
        return $this->single($request, $params, function (SsePersonViewer $v, int $id) use ($request): array {
            return $this->service->reopen($v, $id, $request->inputString('reason', ''));
        }, 'Dossier rouvert.');
    }

    /**
     * @param callable(SsePersonViewer, int): array<string, mixed> $do
     */
    private function single(Request $request, array $params, callable $do, string $success): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = $this->back($request, $id);
        if (!Csrf::verify($request)) {
            return $this->answer($request, ['ok' => false, 'error' => self::TOKEN_EXPIRED, 'code' => 'csrf'], $back);
        }

        return $this->answer($request, $do($this->viewer(), $id), $back, $success);
    }

    // ------------------------------------------------------------------ Actions groupées

    public function batch(Request $request, array $params = []): Response
    {
        $back = $this->back($request);
        if (!Csrf::verify($request)) {
            return $this->answer($request, ['ok' => false, 'error' => self::TOKEN_EXPIRED, 'code' => 'csrf'], $back);
        }
        $viewer = $this->viewer();
        $action = $request->inputString('action', '');
        $ids = $this->ids($request);
        if ($ids === []) {
            return $this->answer($request, ['ok' => false, 'error' => 'Cochez au moins une personne.', 'code' => 'empty'], $back);
        }
        if ($action === 'export') {
            return $this->export($viewer, $ids);
        }
        if ($action === 'link') {
            return $this->link($request, $viewer, $ids, $back);
        }
        $args = [
            'level' => $request->inputString('level', ''),
            'reason' => $request->inputString('reason', ''),
            'until' => $request->inputString('until', ''),
            'reason_code' => $request->inputString('reason_code', ''),
            'note' => $request->inputString('note', ''),
            'lift' => $request->inputString('lift', '') === '1',
        ];
        if ($action === 'reopen' && trim($args['reason']) === '') {
            $args['reason'] = 'Annulation de la clôture';
        }
        $res = $this->service->batch($viewer, $action, $ids, $args, SseWantedAccess::fromSession($this->access));
        $done = count($res['done']);
        $payload = [
            'ok' => $done > 0,
            'done' => $res['done'],
            'failed' => $res['failed'],
            'results' => $res['results'],
            'error' => $done === 0 ? (string) ($res['results'][0]['error'] ?? 'Aucune fiche n’a été modifiée.') : null,
        ];
        $message = $res['failed'] > 0 ? 'Fiches traitées : ' . $done . ' · refusées : ' . $res['failed'] : 'Fiches traitées : ' . $done;
        if ($res['failed'] > 0 && $done > 0 && !$this->wantsJson($request)) {
            Session::flash('error', (string) ($this->firstError($res['results']) ?? ''));
        }

        return $this->answer($request, $payload, $back, $message);
    }

    /** @param list<array<string, mixed>> $results */
    private function firstError(array $results): ?string
    {
        foreach ($results as $r) {
            if (empty($r['ok'])) {
                return (string) ($r['error'] ?? '');
            }
        }

        return null;
    }

    /** @return list<int> */
    private function ids(Request $request): array
    {
        $raw = $request->input('ids', []);
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        return is_array($raw) ? array_slice(array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $i): bool => $i > 0))), 0, SsePersonWork::BATCH_MAX) : [];
    }

    /**
     * Export CSV de la sélection : seules les fiches lisibles par ce compte, sans identifiant d'une fiche restreinte.
     *
     * @param list<int> $ids
     */
    private function export(SsePersonViewer $viewer, array $ids): Response
    {
        if (!$viewer->canExport || $viewer->guest) {
            return Response::json(['ok' => false, 'error' => 'Droit manquant : exporter depuis « Fichiers et sujets ».'], 403);
        }
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return Response::json(['ok' => false, 'error' => 'Export impossible.'], 500);
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['IDN', 'Nom', 'Alias', 'Statut', 'Classification', 'Nationalité', 'Affiliation', 'Grille', 'Signé par', 'Signé le', 'Clos', 'Dernière modification'], ';', '"', '');
        foreach ($this->service->readable($viewer, $ids) as $p) {
            $sig = is_array($p['signature'] ?? null) ? $p['signature'] : null;
            fputcsv($out, array_map([$this, 'cell'], [
                'IDN-' . str_pad((string) $p['id'], 5, '0', STR_PAD_LEFT),
                trim((string) $p['first_name'] . ' ' . (string) $p['last_name']),
                (string) ($p['alias'] ?? ''),
                (string) $p['status_label'],
                $p['classification'] !== null ? SsePersonWork::classificationBadge((string) $p['classification']) : '',
                (string) ($p['nationality'] ?? ''),
                (string) ($p['affiliation'] ?? ''),
                (string) ($p['grid_reference'] ?? ''),
                $sig !== null ? (string) ($sig['callsign'] ?? '') : '',
                $sig !== null ? (string) ($sig['signed_at'] ?? '') : '',
                SsePersonWork::isClosed($p) ? SsePersonWork::closeReasonLabel((string) $p['close_reason']) : '',
                (string) ($p['updated_at'] ?? ''),
            ]), ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return (new Response())->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="personnes-' . gmdate('Ymd-His') . '.csv"')
            ->setBody($csv);
    }

    /** Neutralise l'injection de formules d'un tableur (=, +, -, @ en tête de cellule). */
    public function cell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    /**
     * @param list<int> $ids
     */
    private function link(Request $request, SsePersonViewer $viewer, array $ids, string $back): Response
    {
        if (!$viewer->canCases) {
            return $this->answer($request, ['ok' => false, 'error' => 'Droit manquant : écrire dans « Dossiers d’enquête ».'], $back);
        }
        $caseId = (int) $request->input('case_id', 0);
        $this->cases ??= new SseCaseRepository();
        $allowed = $this->cases->listForTenant($viewer->tenantId, $this->access->caseScope(), ['is_folder' => 0]);
        $case = null;
        foreach ($allowed as $c) {
            if ((int) ($c['id'] ?? 0) === $caseId) {
                $case = $c;
                break;
            }
        }
        if ($case === null) {
            return $this->answer($request, ['ok' => false, 'error' => 'Dossier introuvable ou hors de votre périmètre.'], $back);
        }
        $linked = 0;
        foreach ($this->service->readable($viewer, $ids) as $p) {
            if ($this->cases->linkPerson($caseId, (int) $p['id'], $viewer->tenantId, $viewer->userId, 'Rattachement depuis la page Personnes')) {
                $linked++;
            }
        }

        return $this->answer(
            $request,
            ['ok' => $linked > 0, 'done' => $linked, 'error' => $linked > 0 ? null : 'Aucune fiche n’a pu être rattachée (déjà rattachées ou restreintes).'],
            $back,
            'Fiches rattachées au dossier : ' . $linked
        );
    }

    // ------------------------------------------------------------------ Réglage

    public function settings(Request $request, array $params = []): Response
    {
        $back = $this->back($request);
        if (!Csrf::verify($request)) {
            return $this->answer($request, ['ok' => false, 'error' => self::TOKEN_EXPIRED, 'code' => 'csrf'], $back);
        }

        return $this->answer(
            $request,
            $this->service->setAutoLockLevel($this->viewer(), $this->nullable($request->inputString('level', ''))),
            $back,
            'Règle de verrouillage automatique enregistrée.'
        );
    }

    // ------------------------------------------------------------------ Outils

    private function nullable(string $v): ?string
    {
        $v = trim($v);

        return $v === '' ? null : $v;
    }

    private function fragment(string $html, int $status = 200): Response
    {
        return (new Response())->setStatusCode($status)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'no-store')
            ->setBody($html);
    }

    /**
     * @param array<string, mixed> $vars
     */
    public function render(string $view, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require base_path('views/atak/sse/' . $view . '.php');

        return (string) ob_get_clean();
    }
}
