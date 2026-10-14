<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Repositories\AtakModReportRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\ComspecApiKeyAuth;
use App\Support\UtcDateTime;
use PDO;

/**
 * Rapports automatiques du mod « accès refusé par Athena » (ÉCHEC · … — unauthorized,
 * « Session refusée par le poste »).
 *
 * Le mod ne voit qu'un 401/403. Ici, à la réception du rapport, on regarde côté serveur la dernière
 * session jeu de ce Steam, le compte et la clé de communauté pour dire pourquoi l'accès a été refusé
 * (le diagnostic est ajouté au détail technique du rapport), puis on prévient le joueur par e-mail
 * quand son Steam est lié à un compte : au plus un e-mail par joueur, problème et jour.
 */
final class AtakAuthFailureNotifier
{
    public const PROBLEM = 'athena_access_refused';

    public const DIAG_NO_SESSION = 'no_game_session';
    public const DIAG_REVOKED = 'session_revoked';
    public const DIAG_REFRESH_EXPIRED = 'session_refresh_expired';
    public const DIAG_ACCESS_EXPIRED = 'access_token_expired';
    public const DIAG_SESSION_VALID = 'session_valid';
    public const DIAG_ACCOUNT_DISABLED = 'account_disabled';

    /** Fragments (en minuscules) des rapports automatiques qui signalent un accès refusé par Athena. */
    private const AUTH_NEEDLES = [
        'unauthorized',
        'session refusée par le poste',
        'c2_unauthorized',
        'clé d’accès refusée',
        "clé d'accès refusée",
    ];

    /** @var \Closure(string, array<string, mixed>, ?int, array<string, mixed>): bool */
    private \Closure $sendMail;

    /**
     * @param (\Closure(string, array<string, mixed>, ?int, array<string, mixed>): bool)|null $sendMail
     *        envoi de l'e-mail (destinataire, variables du modèle, communauté, résumé) ; EmailService par défaut
     */
    public function __construct(
        private PDO $pdo,
        private AtakModReportRepository $reports,
        ?\Closure $sendMail = null,
        private ?int $now = null
    ) {
        $this->sendMail = $sendMail ?? static function (string $to, array $vars, ?int $tenantId, array $summary): bool {
            return \App\Core\Container::get(EmailService::class)->sendTemplated(
                EmailEvents::ATAK_PLAYER_ACCESS_REFUSED,
                'atak_player_access_refused',
                $to,
                'COMSPEC ATAK : Athena a refusé la connexion de votre jeu',
                $vars,
                $tenantId,
                null,
                $summary
            );
        };
    }

    /** Rapport automatique (pas un signalement joueur) qui dit qu'Athena a refusé l'accès. */
    public static function isAuthFailure(string $source, string $message, string $detail): bool
    {
        if (str_starts_with(strtolower(trim($source)), 'player')) {
            return false;
        }
        $blob = mb_strtolower($message . ' ' . $detail);
        foreach (self::AUTH_NEEDLES as $needle) {
            if (str_contains($blob, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pourquoi Athena refuse ce Steam, vu du serveur.
     *
     * @return array{code:string, text:string, no_access_key:bool}
     */
    public function diagnose(string $steamUid, ?int $tenantId = null): array
    {
        $now = $this->now ?? time();
        $session = $this->latestSession($steamUid);
        $tenantId = $tenantId ?? ($session !== null ? (int) ($session['tenant_id'] ?? 0) : null);
        $noKey = $tenantId !== null && $tenantId > 0 && !$this->tenantHasAccessKey($tenantId);

        if ($this->accountDisabled($steamUid)) {
            return ['code' => self::DIAG_ACCOUNT_DISABLED, 'no_access_key' => $noKey,
                'text' => 'Compte Athena ou fiche membre désactivé pour ce Steam : toute connexion du jeu est refusée.'];
        }
        if ($session === null) {
            return ['code' => self::DIAG_NO_SESSION, 'no_access_key' => $noKey,
                'text' => 'Aucune session Athena ouverte depuis le jeu pour ce Steam : le mod n’envoyait qu’une clé de communauté, et elle a été refusée.'];
        }
        if (trim((string) ($session['revoked_at'] ?? '')) !== '') {
            return ['code' => self::DIAG_REVOKED, 'no_access_key' => $noKey,
                'text' => 'Dernière session jeu fermée le ' . self::fmt($session['revoked_at']) . ' UTC (déconnexion) : le mod utilisait encore son ancien jeton.'];
        }
        if (UtcDateTime::isPast($session['refresh_expires_at'] ?? null, $now)) {
            return ['code' => self::DIAG_REFRESH_EXPIRED, 'no_access_key' => $noKey,
                'text' => 'Session jeu arrivée à son terme le ' . self::fmt($session['refresh_expires_at']) . ' UTC (30 jours) : une nouvelle connexion à Athena est nécessaire.'];
        }
        if (UtcDateTime::isPast($session['expires_at'] ?? null, $now)) {
            return ['code' => self::DIAG_ACCESS_EXPIRED, 'no_access_key' => $noKey,
                'text' => 'Jeton d’accès expiré le ' . self::fmt($session['expires_at']) . ' UTC et pas renouvelé par le mod (session ouverte le '
                    . self::fmt($session['created_at'] ?? '') . ' UTC, mod ' . trim((string) ($session['mod_version'] ?? '?')) . ').'];
        }

        return ['code' => self::DIAG_SESSION_VALID, 'no_access_key' => $noKey,
            'text' => 'Session jeu valable jusqu’au ' . self::fmt($session['expires_at']) . ' UTC : le refus vient d’un jeton déjà remplacé (renouvellement) ou d’une clé de communauté changée depuis la connexion.'];
    }

    /**
     * Diagnostic + e-mail au joueur (si Steam lié et pas déjà prévenu aujourd'hui pour ce problème).
     *
     * @param array{id:int, steam_uid:?string, message:string, player_name?:?string, callsign?:?string, mod_version?:?string, extension_version?:?string} $report
     * @param array{code:string, text:string, no_access_key:bool}|null $diagnosis déjà calculé à la réception du rapport
     * @return array{diagnosis:array{code:string, text:string, no_access_key:bool}|null, mailed:bool}
     */
    public function handle(array $report, ?array $diagnosis = null): array
    {
        $steam = trim((string) ($report['steam_uid'] ?? ''));
        if ($steam === '') {
            return ['diagnosis' => null, 'mailed' => false];
        }
        $accounts = $this->reports->linkedAccountsBySteam([$steam]);
        $account = $accounts[$steam] ?? null;
        $diagnosis ??= $this->diagnose($steam, $account !== null && $account['tenant_id'] > 0 ? $account['tenant_id'] : null);
        if ($account === null || $account['email'] === '') {
            return ['diagnosis' => $diagnosis, 'mailed' => false];
        }
        if (!$this->claimDailySlot($steam, (int) ($report['id'] ?? 0), $diagnosis['code'])) {
            return ['diagnosis' => $diagnosis, 'mailed' => false];
        }
        $advice = self::adviceFor($diagnosis);
        $name = $account['name'] !== '' ? $account['name']
            : (trim((string) ($report['callsign'] ?? '')) ?: (trim((string) ($report['player_name'] ?? '')) ?: 'opérateur'));
        try {
            $mailed = ($this->sendMail)(
                $account['email'],
                [
                    'displayName' => $name,
                    'problemLabel' => self::clip((string) ($report['message'] ?? ''), 120),
                    'reasonText' => $advice['reason'],
                    'steps' => $advice['steps'],
                    'versions' => trim('Mod ' . trim((string) ($report['mod_version'] ?? '?'))
                        . ' · Link ' . trim((string) ($report['extension_version'] ?? '?'))),
                    'helpUrl' => url('atak/premiere-liaison'),
                ],
                $account['tenant_id'] > 0 ? $account['tenant_id'] : null,
                ['purpose' => 'atak_player_access_refused', 'report_id' => (int) ($report['id'] ?? 0), 'diagnosis' => $diagnosis['code']]
            );
        } catch (\Throwable) {
            $mailed = false;
        }
        if ($mailed) {
            $this->reports->saveReply(
                (int) ($report['id'] ?? 0),
                $advice['reason'] . "\n" . implode("\n", $advice['steps']),
                'E-mail automatique',
                ['email']
            );
        } else {
            $this->releaseDailySlot($steam);
        }

        return ['diagnosis' => $diagnosis, 'mailed' => $mailed];
    }

    /**
     * Ce qu'on dit au joueur, selon le diagnostic.
     *
     * @param array{code:string, text:string, no_access_key:bool} $diagnosis
     * @return array{reason:string, steps:list<string>}
     */
    public static function adviceFor(array $diagnosis): array
    {
        if ($diagnosis['code'] === self::DIAG_ACCOUNT_DISABLED) {
            return [
                'reason' => 'Votre compte est désactivé sur Athena, le jeu ne peut donc plus s’y connecter.',
                'steps' => [
                    'Contactez le gestionnaire de votre communauté pour réactiver votre compte.',
                ],
            ];
        }
        $reason = match ($diagnosis['code']) {
            self::DIAG_NO_SESSION => 'Votre jeu n’a pas de session Athena ouverte : il n’envoyait que la clé de votre communauté, et Athena l’a refusée.',
            self::DIAG_REVOKED, self::DIAG_REFRESH_EXPIRED => 'Votre session Athena en jeu est terminée : il faut vous reconnecter.',
            default => 'Votre jeu a gardé un ancien jeton de connexion, qu’Athena a refusé.',
        };
        $steps = [
            'Mettez à jour le mod COMSPEC depuis le Steam Workshop (Arma 3 Launcher, onglet Mods).',
            'Quittez complètement Arma 3, relancez-le, puis reconnectez-vous depuis le jeu : Échap > Connexion Athena.',
            'Si le refus revient, ouvrez Échap > Dépannage liaison et suivez la correction proposée.',
        ];
        if ($diagnosis['no_access_key'] || $diagnosis['code'] === self::DIAG_NO_SESSION) {
            $steps[] = 'Si cela ne suffit pas, demandez au gestionnaire de votre communauté la clé d’accès ATAK (Configuration ATAK sur Athena).';
        }

        return ['reason' => $reason, 'steps' => $steps];
    }

    /** @return array<string, mixed>|null */
    private function latestSession(string $steamUid): ?array
    {
        try {
            $st = $this->pdo->prepare('SELECT * FROM game_sessions WHERE steam_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$steamUid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function accountDisabled(string $steamUid): bool
    {
        try {
            $st = $this->pdo->prepare('SELECT status FROM users WHERE steam_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$steamUid]);
            $status = $st->fetchColumn();
            if (is_string($status) && $status !== '' && $status !== 'active') {
                return true;
            }
        } catch (\Throwable) {
        }
        try {
            $st = $this->pdo->prepare('SELECT status FROM athena_accounts WHERE steam_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$steamUid]);
            $status = $st->fetchColumn();

            return is_string($status) && $status !== '' && $status !== 'active';
        } catch (\Throwable) {
            return false;
        }
    }

    private function tenantHasAccessKey(int $tenantId): bool
    {
        if (ComspecApiKeyAuth::expectedSecret() !== '') {
            return true;
        }
        try {
            $st = $this->pdo->prepare('SELECT access_key FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $key = $st->fetchColumn();

            return is_string($key) && trim($key) !== '';
        } catch (\Throwable) {
            return true;
        }
    }

    /** Réserve l'envoi du jour pour ce joueur ; faux s'il a déjà été prévenu aujourd'hui. */
    private function claimDailySlot(string $steamUid, int $reportId, string $diagnosis): bool
    {
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO atak_player_auto_notices (steam_uid, problem, sent_on, report_id, diagnosis) VALUES (?, ?, ?, ?, ?)'
            );
            $st->execute([$steamUid, self::PROBLEM, $this->today(), $reportId > 0 ? $reportId : null, $diagnosis]);

            return true;
        } catch (\Throwable) {
            // Doublon (déjà prévenu aujourd'hui) ou table absente : pas d'e-mail plutôt qu'un e-mail par rapport.
            return false;
        }
    }

    private function releaseDailySlot(string $steamUid): void
    {
        try {
            $st = $this->pdo->prepare('DELETE FROM atak_player_auto_notices WHERE steam_uid = ? AND problem = ? AND sent_on = ?');
            $st->execute([$steamUid, self::PROBLEM, $this->today()]);
        } catch (\Throwable) {
        }
    }

    private function today(): string
    {
        return gmdate('Y-m-d', $this->now ?? time());
    }

    private static function fmt(mixed $raw): string
    {
        $ts = UtcDateTime::timestamp($raw);

        return $ts === null ? '?' : gmdate('d/m/Y H:i', $ts);
    }

    private static function clip(string $s, int $max): string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));

        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
    }
}
