<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SseAccessMemberRights;
use App\Support\SseAccessCatalog as C;

/**
 * Bloc « Accès SSE » de la fiche effectif d'un membre : donner un accès ou retirer un accès en un clic, avec aperçu de
 * l'effet avant enregistrement. Tout passe par SseAccessMemberRights, donc par SseAccessRuleService (mêmes garde-fous que
 * l'onglet « Droits d'accès » du bureau SSE : droit de gestion SSE seulement, pas d'escalade, motif, dernier gestionnaire
 * protégé, historique et journal d'audit).
 *
 * La communauté est celle de la session, jamais celle de la requête ; le membre doit en faire partie (sinon « introuvable »,
 * comme pour un identifiant d'une autre communauté). Toute écriture : POST + jeton CSRF + limite de changements par heure.
 */
final class MemberSseAccessController
{
    public function __construct(private ?SseAccessMemberRights $rights = null)
    {
        $this->rights ??= new SseAccessMemberRights();
    }

    /** POST …/membres/{id}/acces-sse : donner ou retirer un accès (règle d'identité datée, avec motif). */
    public function save(Request $request, array $params = []): Response
    {
        $memberId = (int) ($params['id'] ?? 0);
        $back = effectifs_member_url($memberId, 'acces-sse');
        if (($denied = $this->guard($request, $memberId, $back, true)) !== null) {
            return $denied;
        }
        $res = $this->rights->save($this->tenantId(), $this->userId(), $this->actorLabel(), $memberId, $this->input($request));
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect($back);
    }

    /** POST …/membres/{id}/acces-sse/habilitation : accorde, modifie ou retire l'habilitation inscrite sur la fiche du membre. */
    public function clearance(Request $request, array $params = []): Response
    {
        $memberId = (int) ($params['id'] ?? 0);
        $back = effectifs_member_url($memberId, 'acces-sse');
        if (($denied = $this->guard($request, $memberId, $back, true)) !== null) {
            return $denied;
        }
        $level = $request->inputString('clearance_level');
        $res = $this->rights->changeClearance($this->tenantId(), $this->userId(), $this->actorLabel(), $memberId, $level !== '' ? $level : null, $request->inputString('reason'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect($back);
    }

    /** POST …/membres/{id}/acces-sse/apercu : l'effet en une phrase, sans rien écrire (réponse JSON). */
    public function preview(Request $request, array $params = []): Response
    {
        $memberId = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();
        if (!$request->isPost() || !Csrf::validate((string) $request->input('_csrf_token', '')) || !$this->rights->visibleTo($tenantId, $this->userId())) {
            return Response::json(['ok' => false, 'line' => '', 'errors' => []], 403);
        }
        if ($this->tooMany(false)) {
            return Response::json(['ok' => false, 'line' => '', 'errors' => ['Trop de demandes en peu de temps : réessayez plus tard.']], 429);
        }
        $input = $this->input($request);
        // L'aperçu se montre avant que le motif soit saisi : il ne sert qu'à compter les personnes concernées.
        if (mb_strlen(trim((string) $input['reason'])) < C::REASON_MIN) {
            $input['reason'] = 'Aperçu';
        }
        $plan = $this->rights->preview($tenantId, $this->userId(), $memberId, $input);
        $rules = [];
        foreach ($plan['rules'] as $r) {
            $rules[] = ['section' => C::sectionLabel((string) $r['section']), 'level' => C::levelLabel((string) $r['level'])];
        }

        return Response::json([
            'ok' => $plan['errors'] === [] && $plan['rules'] !== [],
            'line' => $plan['line'],
            'errors' => $plan['errors'],
            'skipped' => $plan['skipped'],
            'rules' => $rules,
        ]);
    }

    /** POST …/membres/{id}/acces-sse/{ruleId}/retirer : retire une règle d'identité de ce membre. */
    public function revoke(Request $request, array $params = []): Response
    {
        $memberId = (int) ($params['id'] ?? 0);
        $back = effectifs_member_url($memberId, 'acces-sse');
        if (($denied = $this->guard($request, $memberId, $back, true)) !== null) {
            return $denied;
        }
        $res = $this->rights->revoke($this->tenantId(), $this->userId(), $this->actorLabel(), $memberId, (int) ($params['ruleId'] ?? 0), $request->inputString('reason'));
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);

        return Response::redirect($back);
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        $sections = $request->input('sections');

        return [
            'sections' => is_array($sections) ? array_values(array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $sections)) : [],
            'access' => $request->inputString('access'),
            'effect' => $request->inputString('effect') === C::EFFECT_DENY ? C::EFFECT_DENY : C::EFFECT_ALLOW,
            'ends_at' => $request->inputString('ends_at'),
            'reason' => $request->inputString('reason'),
        ];
    }

    private function guard(Request $request, int $memberId, string $back, bool $write): ?Response
    {
        $tenantId = $this->tenantId();
        if ($memberId < 1 || !$this->rights->visibleTo($tenantId, $this->userId())) {
            Session::flash('error', 'Action non autorisée.');

            return Response::redirect(effectifs_workspace_url());
        }
        if (!$request->isPost() || !Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if ($write && $this->tooMany(true)) {
            Session::flash('error', 'Trop de changements de droits en peu de temps : réessayez plus tard.');

            return Response::redirect($back);
        }

        return null;
    }

    private function tooMany(bool $save): bool
    {
        // Même compteur que l'onglet « Droits d'accès » du bureau SSE : un seul plafond de changements par heure et par personne.
        return (new FileRateLimiter())->tooManyAttempts(
            'sse_access_rules|' . ($save ? 'w' : 'p') . '|' . $this->tenantId() . '|' . $this->userId(),
            $save ? C::CHANGES_PER_HOUR : 200,
            3600
        );
    }

    private function tenantId(): int
    {
        return (int) Session::get('tenant_id');
    }

    private function userId(): int
    {
        return (int) Session::get('user_id');
    }

    private function actorLabel(): string
    {
        foreach (['callsign', 'display_name'] as $k) {
            $v = trim((string) (Session::get($k) ?? ''));
            if ($v !== '') {
                return mb_substr($v, 0, 120);
            }
        }

        return 'Membre #' . $this->userId();
    }
}
