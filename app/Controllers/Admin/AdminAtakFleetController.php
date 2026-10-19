<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakAdminControlRepository as Fleet;
use App\Services\Auth\AuthService;

/**
 * Parc ATAK des joueurs, toutes communautés (/admin/parc-atak) : liste, fiche par ATAK,
 * blocage (appliqué par l'API), retrait, déconnexion forcée, messages imposés en jeu, compromissions
 * (historique, déclaration, annulation), certificats et notes internes.
 * La gestion d'une communauté en a une version limitée à ses membres : BackOfficeAtakFleetController.
 */
class AdminAtakFleetController
{
    protected const BASE = 'admin/parc-atak';

    /** Fiches affichées dans la liste (les filtres et tris portent sur au plus 2 000 fiches). */
    private const PAGE = 300;

    public function __construct(
        protected Fleet $fleet,
        protected AuthService $authService,
    ) {}

    /** Communauté à laquelle la page est limitée (0 : toutes, administration de la plateforme). */
    protected function scopeTenantId(): int
    {
        return 0;
    }

    /** Ce que la vue affiche selon l'espace (administration de la plateforme ou gestion de la communauté). */
    protected function viewScope(): array
    {
        return [
            'tenant' => false,
            'base' => static::BASE,
            'home' => 'admin',
            'home_label' => 'Administration du site',
            'by' => 'l’administration du site',
        ];
    }

    /** @return array<string, mixed> */
    protected function shell(): array
    {
        return ['isPlatformAdminShell' => true, 'backOfficePageCss' => ['admin-atak-fleet.css']];
    }

    /** Source inscrite dans l'historique des compromissions. */
    protected function compromiseSource(): string
    {
        return 'admin';
    }

    /** Fiche ATAK, seulement si elle est dans le périmètre de la page. */
    protected function terminal(int $id): ?array
    {
        $t = $this->fleet->findTerminal($id);
        $scope = $this->scopeTenantId();

        return $t !== null && ($scope === 0 || (int) $t['tenant_id'] === $scope) ? $t : null;
    }

    public function index(Request $request, array $params = []): Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $scope = $this->scopeTenantId();
        $tenantId = $scope > 0 ? $scope : max(0, (int) $request->query('communaute', 0));
        $state = (string) $request->query('etat', '');
        $state = isset(Fleet::STATES[$state]) ? $state : '';
        $sort = (string) $request->query('tri', 'recent');
        $sort = isset(Fleet::SORTS[$sort]) ? $sort : 'recent';

        $this->fleet->ready();
        $terminals = $this->fleet->listTerminals($search, $tenantId, 2000);
        $metrics = $this->fleet->metricsForUsers(array_map(static fn (array $t): int => (int) ($t['user_id'] ?? 0), $terminals));
        $blocks = $this->fleet->activeBlocks();
        $pending = $this->fleet->pendingCountByUser();

        $now = time();
        $rows = [];
        $counts = array_fill_keys(array_keys(Fleet::STATES), 0);
        foreach ($terminals as $t) {
            $key = (int) $t['tenant_id'] . ':' . (int) ($t['user_id'] ?? 0);
            $t['metrics'] = $metrics[$key] ?? null;
            $t['block'] = Fleet::blockMatching($t, $blocks);
            $t['pending_messages'] = (int) ($pending[$key] ?? 0);
            $seen = \App\Support\UtcDateTime::timestamp($t['last_seen_at'] ?? null);
            $t['seen_ago'] = $seen !== null ? max(0, $now - $seen) : null;
            $tags = [''];
            if ($t['seen_ago'] !== null && $t['seen_ago'] <= 7 * 86400) {
                $tags[] = 'actifs';
            }
            if ($t['seen_ago'] === null || $t['seen_ago'] > 30 * 86400) {
                $tags[] = 'dormants';
            }
            if ($t['block'] !== null) {
                $tags[] = 'bloques';
            }
            if ($t['pending_messages'] > 0) {
                $tags[] = 'messages';
            }
            if (!in_array((string) ($t['compromise_state'] ?? 'none'), ['', 'none'], true)) {
                $tags[] = 'compromis';
            }
            foreach ($tags as $tag) {
                $counts[$tag]++;
            }
            if (in_array($state, $tags, true)) {
                $rows[] = $t;
            }
        }
        $rows = self::sortRows($rows, $sort);

        $totals = ['play' => 0, 'total_m' => 0, 'reports' => 0];
        $seenUsers = [];
        foreach ($rows as $r) {
            $key = (int) $r['tenant_id'] . ':' . (int) ($r['user_id'] ?? 0);
            if ($r['metrics'] === null || isset($seenUsers[$key])) {
                continue;
            }
            $seenUsers[$key] = true;
            foreach ($totals as $k => $_) {
                $totals[$k] += (int) $r['metrics'][$k];
            }
        }

        return Response::view('layout.main', $this->shell() + [
            'title' => $scope > 0 ? 'Parc ATAK des membres' : 'Parc ATAK des joueurs',
            'content' => 'admin.atak-fleet.index',
            'fleetScope' => $this->viewScope(),
            'rows' => array_slice($rows, 0, self::PAGE),
            'matching' => count($rows),
            'counts' => $counts,
            'totals' => $totals,
            'players' => count($seenUsers),
            'tenants' => $scope > 0 ? [] : $this->fleet->tenantsWithTerminals(),
            'search' => $search,
            'tenantFilter' => $tenantId,
            'stateFilter' => $state,
            'sort' => $sort,
            'actions' => $this->fleet->recentActions(12, null, null, $scope),
            'tablesReady' => $this->fleet->ready(),
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $terminal = $this->terminal((int) $request->query('id', 0));
        if ($terminal === null) {
            Session::flash('error', 'ATAK introuvable (déjà retiré ?).');

            return Response::redirect(url(static::BASE));
        }
        $tenantId = (int) $terminal['tenant_id'];
        $userId = (int) ($terminal['user_id'] ?? 0);
        $steam = trim((string) ($terminal['steam_id'] ?? ''));
        $uid = (string) $terminal['terminal_uid'];
        $metrics = $userId > 0 ? ($this->fleet->metricsForUsers([$userId])[$tenantId . ':' . $userId] ?? null) : null;

        $scope = $this->scopeTenantId();

        return Response::view('layout.main', $this->shell() + [
            'title' => 'ATAK · ' . Fleet::playerLabel($terminal),
            'content' => 'admin.atak-fleet.show',
            'fleetScope' => $this->viewScope(),
            'terminal' => $terminal,
            'metrics' => $metrics,
            'roles' => $userId > 0 ? $this->fleet->rolesFor($tenantId, $userId) : [],
            'pilot' => $userId > 0 ? $this->fleet->pilotFor($tenantId, $userId) : [],
            'certificates' => $this->fleet->certificatesFor((int) $terminal['id']),
            'activeBlock' => Fleet::blockMatching($terminal, $this->fleet->activeBlocks()),
            'blocks' => $this->fleet->blocksFor($userId ?: null, $steam ?: null, $uid, $scope),
            'messages' => $this->fleet->messagesFor($userId ?: null, $steam ?: null, $uid, $scope),
            'actions' => $this->fleet->recentActions(30, $userId ?: null, $uid, $scope),
            'compromises' => $this->compromiseHistory($terminal),
        ]);
    }

    /**
     * Historique des compromissions de l'ATAK : table dédiée, complétée par les entrées plus anciennes du journal
     * d'activité (antérieures à l'historique, non annulables une par une).
     *
     * @param array<string, mixed> $terminal
     * @return list<array<string, mixed>>
     */
    private function compromiseHistory(array $terminal): array
    {
        $tenantId = (int) $terminal['tenant_id'];
        $uid = (string) $terminal['terminal_uid'];
        $events = [];
        $oldest = null;
        foreach ($this->fleet->compromiseEventsFor($tenantId, $uid) as $e) {
            $e['ts'] = \App\Support\UtcDateTime::timestamp($e['created_at'] ?? null) ?? 0;
            $oldest = $oldest === null ? $e['ts'] : min($oldest, $e['ts']);
            $events[] = $e;
        }
        try {
            $legacy = (new \App\Services\Tactical\AtakActivityLogService())->listCompromiseForTerminal($tenantId, $uid);
        } catch (\Throwable) {
            $legacy = [];
        }
        foreach ($legacy as $l) {
            if ($oldest !== null && $l['ts'] >= $oldest - 5) {
                continue;
            }
            $events[] = [
                'id' => 0,
                'state' => isset(Fleet::COMPROMISE_STATES[$l['state']]) ? $l['state'] : 'captured',
                'reason' => $l['label'],
                'source' => $l['source'],
                'actor_label' => $l['actor'],
                'ts' => $l['ts'],
                'legacy' => true,
            ];
        }

        return self::sortCompromises($events);
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     */
    public static function sortCompromises(array $events): array
    {
        usort($events, static fn (array $a, array $b): int => [(int) $b['ts'], (int) $b['id']] <=> [(int) $a['ts'], (int) $a['id']]);

        return $events;
    }

    /** Déclare l'ATAK capturé ou compromis (« compromis » révoque ses certificats valides). */
    public function compromise(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }
        $terminal = $this->terminal((int) $request->input('terminal_id', 0));
        if ($terminal === null) {
            return $this->back($request, 'error', 'ATAK introuvable.');
        }
        $state = $request->input('etat') === 'compromised' ? 'compromised' : 'captured';
        $reason = mb_substr(trim((string) $request->input('motif', '')), 0, 255);
        [$actorId, $actorLabel] = $this->actor();
        $done = $this->fleet->markCompromised($terminal, $state, $reason, $state === 'compromised', $actorId, $actorLabel, $this->compromiseSource());
        $this->fleet->log($terminal, 'compromise', implode(' · ', array_filter([
            Fleet::COMPROMISE_STATES[$state],
            $reason !== '' ? 'Motif : ' . $reason : '',
            $done['revoked'] > 0 ? $done['revoked'] . ' certificat(s) révoqué(s)' : '',
        ])), $actorId, $actorLabel);
        $by = $this->viewScope()['by'];
        $this->journal($terminal, $state === 'compromised'
            ? 'Appareil compromis — déclaré par ' . $by
            : 'Appareil capturé — déclaré par ' . $by, $state);
        $this->fleet->notifyPlayer($terminal, 'compromise', $state === 'compromised' ? 'ATAK compromis' : 'ATAK capturé',
            $reason !== '' ? $reason : 'Déclarée par l’administration ATHENA', $actorId);

        return $this->back($request, 'success', ($state === 'compromised' ? 'ATAK déclaré compromis' : 'ATAK déclaré capturé')
            . ($done['revoked'] > 0 ? ' : ' . $done['revoked'] . ' certificat(s) révoqué(s).' : '.')
            . ' Vous pouvez l’annuler depuis l’historique des compromissions.');
    }

    /** Annule une compromission (l'ATAK repasse sous contrôle, certificats rétablis au choix). */
    public function undoCompromise(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }
        $terminal = $this->terminal((int) $request->input('terminal_id', 0));
        if ($terminal === null) {
            return $this->back($request, 'error', 'ATAK introuvable.');
        }
        $event = null;
        $eventId = (int) $request->input('evenement_id', 0);
        if ($eventId > 0) {
            $event = $this->fleet->findCompromiseEvent($eventId);
            if ($event === null || (string) $event['terminal_uid'] !== (string) $terminal['terminal_uid']
                || (int) $event['tenant_id'] !== (int) $terminal['tenant_id'] || !empty($event['undone_at']) || (string) $event['state'] === 'none') {
                return $this->back($request, 'error', 'Compromission introuvable ou déjà annulée.');
            }
        } elseif (in_array((string) ($terminal['compromise_state'] ?? 'none'), ['', 'none'], true)) {
            return $this->back($request, 'error', 'Cet ATAK n’est pas compromis.');
        }
        $note = mb_substr(trim((string) $request->input('note', '')), 0, 255);
        [$actorId, $actorLabel] = $this->actor();
        $done = $this->fleet->undoCompromise($terminal, $event, $request->input('certificats') !== '0', $note, $actorId, $actorLabel, $this->compromiseSource());
        $this->fleet->log($terminal, 'compromise_undo', implode(' · ', array_filter([
            $event !== null ? (Fleet::COMPROMISE_STATES[(string) $event['state']] ?? '') . ' du ' . $this->date($event['created_at'] ?? null) : 'Compromission en cours',
            $done['restored'] > 0 ? $done['restored'] . ' certificat(s) rétabli(s)' : '',
            $note,
        ])), $actorId, $actorLabel);
        if ($done['cleared']) {
            $this->journal($terminal, 'Contrôle appareil rétabli par ' . $this->viewScope()['by'], 'none');
        }
        if ($done['cleared'] || $done['restored'] > 0) {
            $this->fleet->notifyPlayer($terminal, 'compromise_cleared', 'Compromission levée', implode(' · ', array_filter([
                $done['restored'] > 0 ? $done['restored'] . ' certificat(s) rétabli(s)' : '',
                $note,
            ])), $actorId);
        }

        return $this->back($request, 'success', implode(' ', array_filter([
            'Compromission annulée.',
            $done['cleared'] ? 'L’ATAK est de nouveau sous contrôle.' : '',
            $done['restored'] > 0 ? $done['restored'] . ' certificat(s) rétabli(s).' : '',
        ])));
    }

    /** Révoque ou rétablit un certificat de l'ATAK. */
    public function certificate(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }
        $terminal = $this->terminal((int) $request->input('terminal_id', 0));
        if ($terminal === null) {
            return $this->back($request, 'error', 'ATAK introuvable.');
        }
        $certId = (int) $request->input('certificat_id', 0);
        $cert = null;
        foreach ($this->fleet->certificatesFor((int) $terminal['id']) as $c) {
            $cert = (int) $c['id'] === $certId ? $c : $cert;
        }
        if ($cert === null) {
            return $this->back($request, 'error', 'Certificat introuvable.');
        }
        $revoke = $request->input('operation') !== 'retablir';
        [$actorId, $actorLabel] = $this->actor();
        if (!$this->fleet->setCertificateRevoked($terminal, $certId, $revoke, 'Révoqué par ' . $this->viewScope()['by'])) {
            return $this->back($request, 'error', $revoke ? 'Certificat déjà révoqué.' : 'Certificat non rétabli (déjà valide ou expiré).');
        }
        $this->fleet->log($terminal, $revoke ? 'cert_revoke' : 'cert_restore', (string) $cert['certificate_ref'], $actorId, $actorLabel);
        $this->fleet->notifyPlayer($terminal, 'certificate', $revoke ? 'Certificat révoqué' : 'Certificat rétabli',
            'Référence : ' . (string) $cert['certificate_ref'], $actorId);

        return $this->back($request, 'success', $revoke
            ? 'Certificat révoqué : l’ATAK n’est plus reconnu sur le réseau avec ce certificat.'
            : 'Certificat rétabli.');
    }

    /** Note interne au journal de l'ATAK (celles de l'administration ne sont pas montrées à la gestion). */
    public function note(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }
        $terminal = $this->terminal((int) $request->input('terminal_id', 0));
        $text = mb_substr(trim((string) $request->input('note', '')), 0, 1000);
        if ($terminal === null || $text === '') {
            return $this->back($request, 'error', 'Note vide.');
        }
        [$actorId, $actorLabel] = $this->actor();
        $this->fleet->log($terminal, $this->scopeTenantId() > 0 ? 'note_gestion' : 'note', $text, $actorId, $actorLabel);

        return $this->back($request, 'success', 'Note ajoutée au journal.');
    }

    /** Ligne dans le journal d'activité ATAK de la communauté (le poste de commandement voit le changement). */
    private function journal(array $terminal, string $label, string $state): void
    {
        try {
            (new \App\Services\Tactical\AtakActivityLogService())->record(
                (int) $terminal['tenant_id'],
                \App\Services\Tactical\AtakActivityLogService::AUTH_MAP_ID,
                \App\Services\Tactical\AtakActivityLogService::TYPE_INTEL,
                $label,
                Fleet::playerLabel($terminal),
                ['terminal_uid' => (string) $terminal['terminal_uid'], 'compromise_state' => $state, 'source' => $this->compromiseSource()]
            );
        } catch (\Throwable) {
        }
    }

    private function date(mixed $raw): string
    {
        $ts = \App\Support\UtcDateTime::timestamp($raw);

        return $ts !== null ? date('d/m/Y H:i', $ts) : '—';
    }

    /** Bloque un ou plusieurs ATAK (et leur joueur) : l'API refuse la connexion et les envois du jeu. */
    public function block(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }
        if (!$this->fleet->ready()) {
            return $this->back($request, 'error', 'Tables du parc ATAK absentes : lancez run-migrations.php.');
        }
        $terminals = $this->targets($request);
        if ($terminals === []) {
            return $this->back($request, 'error', 'Aucun ATAK sélectionné.');
        }
        // La gestion d'une communauté ne bloque que dans sa communauté.
        $allTenants = $this->scopeTenantId() === 0 && $request->input('portee') === 'toutes';
        $days = max(0, min(3650, (int) $request->input('duree_jours', 0)));
        $expires = $days > 0 ? gmdate('Y-m-d H:i:s', time() + $days * 86400) : null;
        $reason = mb_substr(trim((string) $request->input('motif', '')), 0, 500);
        $playerMessage = mb_substr(trim((string) $request->input('message_joueur', '')), 0, 500);
        [$actorId, $actorLabel] = $this->actor();

        $done = 0;
        foreach ($terminals as $t) {
            $id = $this->fleet->createBlock([
                'tenant_id' => $allTenants ? null : (int) $t['tenant_id'],
                'user_id' => ((int) ($t['user_id'] ?? 0)) ?: null,
                'steam_uid' => trim((string) ($t['steam_id'] ?? '')) ?: null,
                'terminal_uid' => (string) $t['terminal_uid'],
                'label' => Fleet::playerLabel($t),
                'reason' => $reason,
                'player_message' => $playerMessage,
                'expires_at' => $expires,
                'actor_id' => $actorId,
            ]);
            if ($id < 1) {
                continue;
            }
            $done++;
            // Ferme les sessions ouvertes : le jeu doit se reconnecter et l'API le refuse.
            $closed = $this->fleet->revokeGameSessions($allTenants ? 0 : (int) $t['tenant_id'], (int) ($t['user_id'] ?? 0));
            $this->fleet->log($t, 'block', implode(' · ', array_filter([
                $allTenants ? 'Toutes les communautés' : 'Sa communauté',
                $days > 0 ? $days . ' jour(s)' : 'Sans fin',
                $reason !== '' ? 'Motif : ' . $reason : '',
                $closed > 0 ? $closed . ' session(s) de jeu fermée(s)' : '',
            ])), $actorId, $actorLabel);
        }

        return $this->back(
            $request,
            $done > 0 ? 'success' : 'error',
            $done > 0 ? $done . ' ATAK bloqué(s). Le jeu est refusé à la prochaine connexion ou au prochain envoi.' : 'Blocage impossible.'
        );
    }

    public function unblock(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }
        $block = $this->fleet->findBlock((int) $request->input('blocage_id', 0));
        $scope = $this->scopeTenantId();
        if ($block !== null && $scope > 0 && (int) ($block['tenant_id'] ?? 0) !== $scope) {
            return $this->back($request, 'error', 'Ce blocage a été posé par l’administration de la plateforme : seule elle peut le lever.');
        }
        [$actorId, $actorLabel] = $this->actor();
        if ($block === null || !$this->fleet->liftBlock((int) $block['id'], $actorId)) {
            return $this->back($request, 'error', 'Blocage introuvable ou déjà levé.');
        }
        $this->fleet->log($block, 'unblock', (string) ($block['reason'] ?? ''), $actorId, $actorLabel);

        return $this->back($request, 'success', 'Blocage levé : le joueur peut se reconnecter.');
    }

    /** Retire un ATAK : certificats et appareil révoqués, sessions fermées, fiche supprimée. */
    public function retire(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }
        if ($this->scopeTenantId() > 0) {
            return $this->back($request, 'error', 'Le retrait définitif d’un ATAK est réservé à l’administration de la plateforme.');
        }
        $terminals = $this->targets($request);
        if ($terminals === []) {
            return $this->back($request, 'error', 'Aucun ATAK sélectionné.');
        }
        $reason = mb_substr(trim((string) $request->input('motif', '')), 0, 255);
        [$actorId, $actorLabel] = $this->actor();
        $done = 0;
        foreach ($terminals as $t) {
            if ($this->fleet->retireTerminal($t, $reason)) {
                $done++;
                // Avis au joueur : ses données ATAK sont réinitialisées, l'ATAK se réenregistre à la prochaine liaison.
                $this->fleet->notifyPlayer($t, 'reset', 'Données ATAK réinitialisées', $reason, $actorId);
                $this->fleet->log($t, 'retire', trim((string) $t['terminal_uid'] . ($reason !== '' ? ' · ' . $reason : '')), $actorId, $actorLabel);
            }
        }
        if ($done === 0) {
            return $this->back($request, 'error', 'Retrait impossible.');
        }
        Session::flash('success', $done . ' ATAK retiré(s). Le joueur devra refaire la liaison (bloquez-le pour l’en empêcher).');

        return Response::redirect(url(static::BASE));
    }

    /** Ferme les sessions de jeu du joueur sans le bloquer. */
    public function disconnect(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }
        [$actorId, $actorLabel] = $this->actor();
        $closed = 0;
        foreach ($this->targets($request) as $t) {
            $n = $this->fleet->revokeGameSessions((int) $t['tenant_id'], (int) ($t['user_id'] ?? 0));
            $closed += $n;
            $this->fleet->log($t, 'sessions', $n . ' session(s) fermée(s)', $actorId, $actorLabel);
        }

        return $this->back($request, 'success', $closed . ' session(s) de jeu fermée(s). Le jeu se reconnectera tout seul s’il n’est pas bloqué.');
    }

    /** Message imposé au joueur en jeu (un ou plusieurs ATAK). */
    public function message(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }
        if (!$this->fleet->ready()) {
            return $this->back($request, 'error', 'Tables du parc ATAK absentes : lancez run-migrations.php.');
        }
        $title = mb_substr(trim((string) $request->input('titre', '')), 0, 120);
        $body = mb_substr(trim((string) $request->input('texte', '')), 0, 2000);
        if ($title === '' || $body === '') {
            return $this->back($request, 'error', 'Le titre et le texte du message sont obligatoires.');
        }
        $terminals = $this->targets($request);
        if ($terminals === []) {
            return $this->back($request, 'error', 'Aucun ATAK sélectionné.');
        }
        $level = (string) $request->input('niveau', 'info');
        $hours = max(0, min(24 * 90, (int) $request->input('validite_heures', 72)));
        [$actorId, $actorLabel] = $this->actor();
        $done = 0;
        $seen = [];
        foreach ($terminals as $t) {
            // Un message par joueur, même s'il a plusieurs ATAK cochés.
            $who = (int) $t['tenant_id'] . ':' . ((int) ($t['user_id'] ?? 0) ?: (string) $t['terminal_uid']);
            if (isset($seen[$who])) {
                continue;
            }
            $seen[$who] = true;
            $id = $this->fleet->createMessage([
                'tenant_id' => (int) $t['tenant_id'],
                'user_id' => ((int) ($t['user_id'] ?? 0)) ?: null,
                'steam_uid' => trim((string) ($t['steam_id'] ?? '')) ?: null,
                'terminal_uid' => (string) $t['terminal_uid'],
                'title' => $title,
                'body' => $body,
                'level' => $level,
                'require_ack' => $request->input('validation') !== '0',
                'expires_at' => $hours > 0 ? gmdate('Y-m-d H:i:s', time() + $hours * 3600) : null,
                'actor_id' => $actorId,
            ]);
            if ($id > 0) {
                $done++;
                $this->fleet->log($t, 'message', $title, $actorId, $actorLabel);
            }
        }

        return $this->back(
            $request,
            $done > 0 ? 'success' : 'error',
            $done > 0
                ? $done . ' message(s) enregistré(s). Ils s’afficheront en jeu dès que le mod saura les récupérer (prochaine version de l’ATAK).'
                : 'Message non enregistré.'
        );
    }

    public function cancelMessage(Request $request, array $params = []): Response
    {
        if (($guard = $this->guard($request)) !== null) {
            return $guard;
        }
        $message = $this->fleet->findMessage((int) $request->input('message_id', 0));
        $scope = $this->scopeTenantId();
        if ($message !== null && $scope > 0 && (int) $message['tenant_id'] !== $scope) {
            $message = null;
        }
        if ($message === null || !$this->fleet->cancelMessage((int) $message['id'])) {
            return $this->back($request, 'error', 'Message introuvable, déjà lu ou déjà annulé.');
        }
        [$actorId, $actorLabel] = $this->actor();
        $this->fleet->log($message, 'message_cancel', (string) $message['title'], $actorId, $actorLabel);

        return $this->back($request, 'success', 'Message annulé : il ne sera pas affiché.');
    }

    /**
     * Tri de la liste.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function sortRows(array $rows, string $sort): array
    {
        $metric = static fn (array $r, string $k): int => (int) ($r['metrics'][$k] ?? 0);
        usort($rows, static function (array $a, array $b) use ($sort, $metric): int {
            return match ($sort) {
                'jeu' => [$metric($b, 'play'), $metric($b, 'total_m')] <=> [$metric($a, 'play'), $metric($a, 'total_m')],
                'distance' => [$metric($b, 'total_m'), $metric($b, 'play')] <=> [$metric($a, 'total_m'), $metric($a, 'play')],
                'nom' => strnatcasecmp(Fleet::playerLabel($a), Fleet::playerLabel($b)),
                default => [$a['seen_ago'] === null, $a['seen_ago'] ?? 0] <=> [$b['seen_ago'] === null, $b['seen_ago'] ?? 0],
            };
        });

        return $rows;
    }

    /**
     * ATAK visés par le formulaire (terminal_id ou terminal_ids[]).
     *
     * @return list<array<string, mixed>>
     */
    private function targets(Request $request): array
    {
        $ids = $request->input('terminal_ids', []);
        $ids = is_array($ids) ? $ids : [];
        $ids[] = $request->input('terminal_id', 0);
        $out = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id > 0 && count($out) < 500 && ($t = $this->terminal($id)) !== null) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /** @return array{0: ?int, 1: string} */
    private function actor(): array
    {
        $user = $this->authService->user() ?? [];
        $name = trim((string) ($user['callsign'] ?? '')) ?: trim((string) ($user['display_name'] ?? ''));

        return [((int) ($user['id'] ?? 0)) ?: null, $name !== '' ? $name : trim((string) ($user['email'] ?? 'Administration'))];
    }

    private function guard(Request $request): ?Response
    {
        if (!Csrf::validate($request->input('_csrf'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(static::BASE));
        }
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }

        return null;
    }

    /** Retour à la fiche si l'action vient de la fiche, sinon à la liste avec ses filtres. */
    private function back(Request $request, string $kind, string $message): Response
    {
        Session::flash($kind, $message);
        $return = (string) $request->input('retour', '');
        if (preg_match('#^fiche:(\d+)$#', $return, $m) === 1) {
            return Response::redirect(url(static::BASE . '/fiche') . '?id=' . (int) $m[1]);
        }
        $qs = [];
        foreach (['q', 'communaute', 'etat', 'tri'] as $k) {
            $v = trim((string) $request->input('retour_' . $k, ''));
            if ($v !== '') {
                $qs[$k] = $v;
            }
        }

        return Response::redirect(url(static::BASE) . ($qs !== [] ? '?' . http_build_query($qs) : ''));
    }
}
