<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditService;
use App\Services\Personnel\RoleplayGameSessionSettings;
use App\Support\RoleplaySessionBoard;

/**
 * Espace d'administration ouvert par QR code depuis l'app Admin du téléphone en jeu (/m/admin).
 *
 * Il ne donne JAMAIS plus que les droits du compte sur le site :
 * - parc ATAK, messages, CR à envoyer : gestionnaire de la communauté ou administrateur de la plateforme
 *   (même règle que /back-office/atak/parc-atak et que l'app Admin du jeu, AtakGameAdminService::role) ;
 * - sessions de jeu : gestionnaire de la communauté (même règle que /back-office/roleplay/sessions).
 * Les droits sont relus à chaque action, la communauté vient du lien (jamais de la requête), et chaque action
 * (acceptée, refusée ou en échec) laisse une ligne dans le journal d'audit, avec le compte et le lien qui l'ont faite.
 */
final class AtakMobileAdminService
{
    public const FLEET_ACTIONS = ['block', 'unblock', 'disconnect', 'message', 'compromise', 'undo_compromise'];

    public const ACTIONS = [...self::FLEET_ACTIONS, 'session_create', 'cr_send'];

    public const SESSION_KINDS = ['officielle' => 'Officielle', 'entrainement' => 'Entraînement', 'libre' => 'Libre'];

    public function __construct(
        private ?AtakGameAdminService $game = null,
        private ?AtakDebriefService $debrief = null,
        private ?RoleplayGameSessionRepository $sessions = null,
        private ?AuditService $audit = null,
    ) {
    }

    /**
     * @param array<string, mixed> $link lien de la session d'administration
     * @param array<string, mixed> $in champs reçus
     * @return array{ok: bool, message: string, id?: int}
     */
    public function run(array $link, string $role, bool $canSessions, string $action, array $in): array
    {
        $tenantId = (int) $link['tenant_id'];
        $userId = (int) $link['user_id'];
        if (!in_array($action, self::ACTIONS, true)) {
            $this->journal($link, $action, 'refusée : action inconnue');

            return ['ok' => false, 'message' => 'Action inconnue.'];
        }
        if ($action === 'session_create' && !$canSessions) {
            $this->journal($link, $action, 'refusée : droit manquant');

            return ['ok' => false, 'message' => 'Créer une session est réservé à la gestion de la communauté.'];
        }
        // Dernière barrière : le rôle relu juste avant d'agir, pas celui du début de la session.
        $now = $this->game()->role($tenantId, $userId);
        if ($now === null) {
            $this->journal($link, $action, 'refusée : droits retirés');

            return ['ok' => false, 'message' => 'Vos droits ont changé : ouvrez à nouveau l’espace depuis le jeu.'];
        }
        try {
            $res = match (true) {
                in_array($action, self::FLEET_ACTIONS, true) => $this->game()->act($tenantId, $now, [$userId, $this->actorName($link)], $action, $in),
                $action === 'session_create' => $this->createSession($link, $in),
                default => $this->sendReport($link, $in),
            };
        } catch (\Throwable) {
            $this->journal($link, $action, 'échec : erreur du site');

            return ['ok' => false, 'message' => 'Le site n’a pas pu faire cette action, réessayez.'];
        }
        $target = $this->targetOf($action, $in);
        $this->journal($link, $action, ($res['ok'] ? 'faite' : 'refusée par le site') . ($target !== '' ? ' · ' . $target : ''));

        return $res;
    }

    /** @return array{ok: bool, message: string, id?: int} */
    private function createSession(array $link, array $in): array
    {
        $tenantId = (int) $link['tenant_id'];
        $repo = $this->sessions ??= new RoleplayGameSessionRepository();
        if (!$repo->schemaReady()) {
            return ['ok' => false, 'message' => 'Les sessions ne sont pas encore disponibles sur le site.'];
        }
        $cfg = RoleplayGameSessionSettings::forTenant($tenantId, new TenantRepository());
        $kind = (string) ($in['session_kind'] ?? 'officielle');
        $kind = isset(self::SESSION_KINDS[$kind]) ? $kind : 'officielle';
        $name = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($in['mission_name'] ?? ''))), 0, 191);
        $plannedStart = RoleplaySessionBoard::parseInput((string) ($in['planned_starts_at'] ?? ''));
        $startNow = (string) ($in['when'] ?? ($plannedStart === null ? 'now' : 'later')) === 'now' || $plannedStart === null;
        $category = trim((string) ($in['hour_category'] ?? ''));
        if ($category === '' || !in_array($category, $cfg['hour_categories'], true)) {
            $category = (string) ($cfg['hour_categories'][0] ?? 'Opération');
        }
        $id = $repo->create($tenantId, [
            'session_uid' => 'staff-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)),
            'mission_name' => $name !== '' ? $name : 'Session',
            'server_name' => null,
            'session_kind' => $kind,
            'hour_category' => $category,
            'community_event_id' => null,
            'is_official' => $kind === 'officielle' ? 1 : 0,
            'attendance_enabled' => $kind === 'officielle' ? 1 : 0,
            'status' => 'open',
            'planned_starts_at' => $plannedStart,
            'planned_ends_at' => null,
            'started_at' => $startNow ? date('Y-m-d H:i:s') : null,
            'min_minutes' => $cfg['min_minutes'],
            'min_percent' => $cfg['min_percent'],
            'late_tolerance_minutes' => $cfg['late_tolerance_minutes'],
            'created_by_user_id' => (int) $link['user_id'],
        ]);

        return $id > 0
            ? ['ok' => true, 'id' => $id, 'message' => $startNow ? 'Session créée et démarrée.' : 'Session prévue : démarrez-la au début de la soirée.']
            : ['ok' => false, 'message' => 'Session non enregistrée.'];
    }

    /**
     * CR de fin d'opération envoyé aux joueurs dont l'ATAK a parlé récemment (même envoi que le suivi des CR du site).
     *
     * @return array{ok: bool, message: string, id?: int}
     */
    private function sendReport(array $link, array $in): array
    {
        $tenantId = (int) $link['tenant_id'];
        $mode = (string) ($in['mode'] ?? 'fill') === 'read' ? 'read' : 'fill';
        $text = mb_substr(trim((string) ($in['texte'] ?? '')), 0, 6000);
        if ($mode === 'read' && $text === '') {
            return ['ok' => false, 'message' => 'Pour un CR à lire, écrivez le texte.'];
        }
        $label = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($in['titre'] ?? ''))), 0, 200);
        $label = $label !== '' ? $label : 'Fin d’opération du ' . date('d/m/Y');
        $minutes = max(5, min(24 * 60, (int) ($in['minutes'] ?? 120)));
        $repo = $this->debrief()->repository();
        $players = $repo->recentlySynced($tenantId, $minutes);
        if ($players === []) {
            return ['ok' => false, 'message' => 'Aucun joueur n’a eu son ATAK relié à Athena dans ce délai.'];
        }
        $res = $this->debrief()->sendFromSite($tenantId, [
            'mission_key' => '',
            'label' => $label,
            'mode' => $mode,
            'template_id' => (int) ($repo->settings($tenantId)['template_id'] ?? 0),
            'aar_report_id' => 0,
            'intro' => mb_substr(trim((string) ($in['intro'] ?? '')), 0, 600),
            'read_text' => $text,
            'hours' => max(1, min(24 * 30, (int) ($in['validite_heures'] ?? 48))),
        ], $players, (int) $link['user_id'], $this->actorName($link) . ' (espace admin du téléphone)');

        return $res['id'] > 0 && $res['sent'] > 0
            ? ['ok' => true, 'id' => $res['id'], 'message' => $res['sent'] . ' joueur(s) recevront le CR dans leur ATAK (30 secondes environ).']
            : ['ok' => false, 'message' => 'Le CR n’a pas pu être envoyé.'];
    }

    /** Nom affiché dans les journaux du parc : jamais d'adresse e-mail. */
    private function actorName(array $link): string
    {
        return mb_substr(trim((string) ($link['player_name'] ?? '')) ?: 'Gestion', 0, 60);
    }

    /** Cible de l'action pour le journal (numéros seulement : pas de texte libre ni de donnée personnelle). */
    private function targetOf(string $action, array $in): string
    {
        $parts = [];
        foreach (['terminal_id' => 'ATAK', 'block_id' => 'blocage'] as $k => $label) {
            if ((int) ($in[$k] ?? 0) > 0) {
                $parts[] = $label . ' #' . (int) $in[$k];
            }
        }
        if ($action === 'cr_send') {
            $parts[] = 'mode ' . ((string) ($in['mode'] ?? 'fill') === 'read' ? 'lecture' : 'remplissage');
        }

        return implode(' · ', $parts);
    }

    private function journal(array $link, string $action, string $what): void
    {
        try {
            ($this->audit ??= new AuditService())->log(
                'atak.mobile_admin',
                (int) $link['tenant_id'],
                (int) $link['user_id'],
                'atak_mobile_link',
                (int) $link['id'],
                null,
                $action . ' : ' . $what
            );
        } catch (\Throwable) {
            // Un journal indisponible ne rend pas l'action possible pour autant : voir run().
        }
    }

    private function game(): AtakGameAdminService
    {
        return $this->game ??= new AtakGameAdminService();
    }

    private function debrief(): AtakDebriefService
    {
        return $this->debrief ??= new AtakDebriefService();
    }
}
