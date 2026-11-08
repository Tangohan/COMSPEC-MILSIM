<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Core\Session;
use App\Repositories\AtakAdminControlRepository;
use App\Repositories\AtakMobileLinkRepository;
use App\Services\Personnel\MemberNameService;
use App\Services\Qr\QrPngGenerator;
use App\Services\Tactical\NineLineBoardService;
use App\Support\AtakMissionKey;
use App\Support\SteamId;
use PDO;

/**
 * « Sur mon téléphone » : l'ATAK en jeu (apps Comptes rendus, CR fin d'op, Carte, briefs air) demande un lien,
 * affiche son QR code, le joueur le scanne avec son vrai téléphone et retrouve l'app en version mobile sur Athena.
 *
 * - Jeton : 20 caractères tirés de random_bytes dans un alphabet de 31 signes sans ambiguïté (≈ 99 bits),
 *   seule son empreinte SHA-256 est gardée, usage unique, 5 minutes. Lié au compte de la session de jeu, à la
 *   communauté et à la partie (clé de mission).
 * - Session mobile : clé de session PHP limitée aux pages /m/…, 8 heures au plus (une partie), révocable par le
 *   membre (Appareils connectés). Contrôlée en base à chaque requête.
 * - Ce qui est fait sur le téléphone suit les flux du jeu : marqueurs web (sondés par le jeu), comptes rendus et CR
 *   fin d'op confiés à l'ATAK du joueur par un avis Athena (« mobile_report », « mobile_debrief »), qui les envoie
 *   comme s'ils avaient été remplis dans le téléphone en jeu.
 */
final class AtakMobileLinkService
{
    /** App du jeu => [page mobile, intitulé]. */
    public const APPS = [
        'cr' => ['m/cr', 'Comptes rendus'],
        'compte-rendu' => ['m/compte-rendu', 'CR fin d’op'],
        'carte' => ['m/carte', 'Carte'],
        'briefs' => ['m/briefs', 'Briefs air'],
        'controle' => ['m/controle', 'Contrôle ID'],
        // Espace d'administration (app Admin du téléphone en jeu) : réservé à la gestion de la communauté.
        'admin' => ['m/admin', 'Administration'],
    ];

    public const TOKEN_TTL = 300;

    public const SESSION_TTL = 28800;

    /** Espace d'administration : jeton de 2 minutes, session d'une heure (pas d'une partie entière). */
    public const ADMIN_TOKEN_TTL = 120;

    public const ADMIN_SESSION_TTL = 3600;

    public const SESSION_KEY = 'atak_mobile_link';

    /** Jetons demandés par un joueur sur 10 minutes. */
    public const ISSUE_LIMIT = 10;

    public const NOTICE_REPORT = 'mobile_report';

    public const NOTICE_DEBRIEF = 'mobile_debrief';

    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const TOKEN_LENGTH = 20;

    /** Un avis confié à l'ATAK n'est plus rejoué après 2 heures (joueur sorti de la partie). */
    private const NOTICE_TTL = 7200;

    public function __construct(
        private ?AtakMobileLinkRepository $links = null,
        private ?QrPngGenerator $qr = null,
        private ?PDO $pdo = null,
        private ?AtakAdminControlRepository $notices = null,
        private ?AtakGameAdminService $admin = null,
    ) {
    }

    public static function newToken(): string
    {
        $out = '';
        $n = strlen(self::ALPHABET);
        while (strlen($out) < self::TOKEN_LENGTH) {
            // Rejet des octets au-delà du dernier multiple de 31 : tirage uniforme.
            $b = ord(random_bytes(1));
            if ($b < 248) {
                $out .= self::ALPHABET[$b % $n];
            }
        }

        return $out;
    }

    public static function tokenLooksValid(string $token): bool
    {
        return strlen($token) === self::TOKEN_LENGTH && strspn($token, self::ALPHABET) === self::TOKEN_LENGTH;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Adresse à taper, affichée sous le QR code : sans https:// ni /public (adresse racine réécrite, comme /lier). */
    public static function shortUrl(string $url): string
    {
        $s = (string) preg_replace('#^https?://#i', '', $url);

        return (string) preg_replace('#/public(/|$)#i', '/', $s, 1);
    }

    /**
     * Lien demandé par l'ATAK en jeu.
     *
     * @param array<string, mixed>|null $context fiche du CR fin d'op en cours (app « compte-rendu »)
     * @return array{ok: true, url: string, short_url: string, expires_in: int, app: string, qr: list<string>}|array{ok: false, error: string}
     */
    public function issue(
        int $tenantId,
        int $userId,
        ?string $steamUid,
        mixed $missionKey,
        int $mapId,
        string $app,
        string $playerName,
        ?float $x = null,
        ?float $y = null,
        ?array $context = null,
        ?int $now = null,
    ): array {
        $now ??= time();
        if (!isset(self::APPS[$app])) {
            return ['ok' => false, 'error' => 'bad_app'];
        }
        if ($tenantId < 1 || $userId < 1) {
            return ['ok' => false, 'error' => 'not_linked'];
        }
        $repo = $this->links();
        if (!$repo->ready()) {
            return ['ok' => false, 'error' => 'unavailable'];
        }
        // L'espace d'administration n'est donné qu'à la gestion de la communauté, jamais sur la seule parole du jeu.
        if ($app === 'admin' && $this->admin()->role($tenantId, $userId) === null) {
            $this->audit($tenantId, $userId, null, 'mobile_admin_link_refused');

            return ['ok' => false, 'error' => 'forbidden'];
        }
        if ($repo->countIssuedSince($tenantId, $userId, gmdate('Y-m-d H:i:s', $now - 600)) >= self::ISSUE_LIMIT) {
            return ['ok' => false, 'error' => 'rate_limited'];
        }
        $token = self::newToken();
        $contextJson = $context !== null && $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if (is_string($contextJson) && strlen($contextJson) > 60000) {
            $contextJson = null;
        }
        $mk = AtakMissionKey::normalize($missionKey);
        $id = $repo->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'steam_uid' => $steamUid !== null && $steamUid !== '' ? SteamId::normalize($steamUid) : null,
            'mission_key' => $mk !== '' ? $mk : null,
            'map_id' => $mapId,
            'app' => $app,
            'player_name' => trim($playerName),
            'pos_x' => $x,
            'pos_y' => $y,
            'context_json' => is_string($contextJson) ? $contextJson : null,
            'token_hash' => self::hash($token),
            'token_expires_at' => gmdate('Y-m-d H:i:s', $now + ($app === 'admin' ? self::ADMIN_TOKEN_TTL : self::TOKEN_TTL)),
        ]);
        $url = url('m/' . $token);
        $matrix = $this->qr()->matrix($url) ?? [];
        $this->audit($tenantId, $userId, $id, 'mobile_link_issued:' . $app);

        return [
            'ok' => true,
            'url' => $url,
            'short_url' => self::shortUrl($url),
            'expires_in' => $app === 'admin' ? self::ADMIN_TOKEN_TTL : self::TOKEN_TTL,
            'app' => $app,
            'qr' => $matrix,
        ];
    }

    /**
     * Scan du QR code : le jeton devient la session mobile (une seule fois). Null si inconnu, déjà servi ou expiré.
     *
     * @return array<string, mixed>|null
     */
    public function redeem(string $token, string $ip, string $userAgent, ?int $now = null): ?array
    {
        $now ??= time();
        if (!self::tokenLooksValid($token)) {
            return null;
        }
        $repo = $this->links();
        if (!$repo->ready()) {
            return null;
        }
        $row = $repo->redeem(self::hash($token), gmdate('Y-m-d H:i:s', $now + self::SESSION_TTL), $ip, $userAgent, $now);
        if ($row !== null) {
            if ((string) $row['app'] === 'admin') {
                // Session d'administration courte, à compter du scan.
                $row['session_expires_at'] = gmdate('Y-m-d H:i:s', $now + self::ADMIN_SESSION_TTL);
                $repo->shortenSession((int) $row['id'], (string) $row['session_expires_at']);
            }
            $this->audit((int) $row['tenant_id'], (int) $row['user_id'], (int) $row['id'], 'mobile_link_opened:' . (string) $row['app']);
        }

        return $row;
    }

    /** Ouvre la session mobile dans ce navigateur (nouvel identifiant de session : pas de fixation). */
    public static function remember(array $link): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, ['id' => (int) $link['id'], 'tenant' => (int) $link['tenant_id']]);
    }

    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Session mobile de ce navigateur, relue en base (révocation, expiration, communauté).
     *
     * @return array<string, mixed>|null
     */
    public function current(?int $now = null): ?array
    {
        $s = Session::get(self::SESSION_KEY);
        if (!is_array($s)) {
            return null;
        }
        $id = (int) ($s['id'] ?? 0);
        $tenant = (int) ($s['tenant'] ?? 0);
        try {
            $row = $this->links()->findActive($id, $tenant, $now);
        } catch (\Throwable) {
            return null;
        }
        if ($row === null) {
            self::forget();

            return null;
        }
        $seen = strtotime((string) ($row['last_seen_at'] ?? '') . ' UTC') ?: 0;
        if (($now ?? time()) - $seen > 60) {
            try {
                $this->links()->touch($id);
            } catch (\Throwable) {
            }
        }

        return $row;
    }

    /**
     * Session d'administration de ce navigateur : liée au compte du QR code, au téléphone qui l'a scanné (même navigateur
     * qu'au scan) et aux droits que ce compte a MAINTENANT sur le site, relus à chaque requête. Un droit retiré coupe tout.
     *
     * @return array{link: array<string, mixed>, role: string, sessions: bool}|null
     */
    public function currentAdmin(?string $userAgent = null, ?int $now = null): ?array
    {
        $link = $this->current($now);
        if ($link === null || (string) ($link['app'] ?? '') !== 'admin') {
            return null;
        }
        $ua = mb_substr((string) ($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);
        if ($ua === '' || !hash_equals((string) ($link['user_agent'] ?? ''), $ua)) {
            $this->audit((int) $link['tenant_id'], (int) $link['user_id'], (int) $link['id'], 'mobile_admin_session_refused:browser');

            return null;
        }
        $tenant = (int) $link['tenant_id'];
        $user = (int) $link['user_id'];
        $role = $this->admin()->role($tenant, $user);
        if ($role === null) {
            $this->links()->revoke($tenant, $user, (int) $link['id']);
            self::forget();
            $this->audit($tenant, $user, (int) $link['id'], 'mobile_admin_session_refused:rights');

            return null;
        }

        return [
            'link' => $link,
            'role' => $role,
            'sessions' => $this->admin()->can($tenant, $user, ['admin.organization', 'admin.access', 'site.support']),
        ];
    }

    /**
     * Fiche jointe au lien (CR fin d'op en cours dans le téléphone en jeu).
     *
     * @return array<string, mixed>
     */
    public static function context(array $link): array
    {
        $c = json_decode((string) ($link['context_json'] ?? ''), true);

        return is_array($c) ? $c : [];
    }

    /**
     * Indicatifs des aéronefs dont ce joueur est pilote ou membre d'équipage (remontés par le jeu, 10 dernières
     * minutes) : nom en jeu du joueur, ou son indicatif / son nom de membre reconnus dans l'équipage.
     *
     * @return list<string>
     */
    public function aircrew(array $link, ?int $now = null): array
    {
        $tenantId = (int) $link['tenant_id'];
        $aliases = [];
        $add = static function (string $n) use (&$aliases): void {
            $k = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $n)));
            if ($k !== '' && $k !== 'pilote') {
                $aliases[$k] = true;
            }
        };
        $add((string) ($link['player_name'] ?? ''));
        try {
            $names = new MemberNameService($this->db());
            foreach ($names->identitiesFor($tenantId, [(int) $link['user_id']]) as $identity) {
                $add((string) ($identity['callsign'] ?? ''));
                $add((string) ($identity['display_name'] ?? ''));
                $add(trim((string) ($identity['first_name'] ?? '') . ' ' . (string) ($identity['last_name'] ?? '')));
            }
        } catch (\Throwable) {
        }
        if ($aliases === []) {
            return [];
        }
        $out = [];
        try {
            $aircraft = (new NineLineBoardService($this->db()))->aircraft($tenantId, $now);
        } catch (\Throwable) {
            return [];
        }
        foreach ($aircraft as $a) {
            $people = $a['crew'] ?? [];
            if (is_array($a['pilot'] ?? null)) {
                $people[] = $a['pilot'];
            }
            foreach ($people as $p) {
                foreach ([(string) ($p['game_name'] ?? ''), (string) ($p['callsign'] ?? '')] as $n) {
                    $k = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $n)));
                    if ($k !== '' && isset($aliases[$k]) && !in_array((string) $a['callsign'], $out, true)) {
                        $out[] = (string) $a['callsign'];
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Avis confié à l'ATAK du joueur (il le traite à la relève suivante, 10 à 30 s), renvoie son numéro.
     */
    public function queueNotice(array $link, string $kind, string $title, string $body, ?int $now = null): int
    {
        return $this->notices()->createMessage([
            'tenant_id' => (int) $link['tenant_id'],
            'user_id' => (int) $link['user_id'],
            'steam_uid' => ($link['steam_uid'] ?? '') !== '' ? (string) $link['steam_uid'] : null,
            'terminal_uid' => null,
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'level' => 'info',
            'require_ack' => false,
            'expires_at' => gmdate('Y-m-d H:i:s', ($now ?? time()) + self::NOTICE_TTL),
            'actor_id' => (int) $link['user_id'],
        ]);
    }

    /** Où en est un avis confié à l'ATAK : pending (en attente du jeu), delivered (reçu), acked (envoyé), unknown. */
    public function noticeState(array $link, int $id): string
    {
        $m = $id > 0 ? $this->notices()->findMessage($id) : null;
        if ($m === null || (int) $m['tenant_id'] !== (int) $link['tenant_id'] || (int) ($m['user_id'] ?? 0) !== (int) $link['user_id']) {
            return 'unknown';
        }
        if (!empty($m['acked_at'])) {
            return 'acked';
        }

        return !empty($m['delivered_at']) ? 'delivered' : 'pending';
    }

    /** Révocation par le membre (une session, ou toutes avec $id = 0). */
    public function revoke(int $tenantId, int $userId, int $id = 0): int
    {
        $n = $this->links()->revoke($tenantId, $userId, $id);
        if ($n > 0) {
            $this->audit($tenantId, $userId, $id > 0 ? $id : null, 'mobile_link_revoked');
        }

        return $n;
    }

    private function audit(int $tenantId, int $userId, ?int $linkId, string $what): void
    {
        try {
            (new \App\Services\Audit\AuditService())->log(
                \App\Services\Audit\AuditAction::SECURITY_EVENT,
                $tenantId,
                $userId,
                'atak_mobile_link',
                $linkId,
                null,
                $what
            );
        } catch (\Throwable) {
            // Best-effort : l'audit ne bloque jamais le lien.
        }
    }

    private function links(): AtakMobileLinkRepository
    {
        return $this->links ??= new AtakMobileLinkRepository($this->pdo);
    }

    private function notices(): AtakAdminControlRepository
    {
        return $this->notices ??= new AtakAdminControlRepository($this->pdo);
    }

    private function admin(): AtakGameAdminService
    {
        return $this->admin ??= new AtakGameAdminService();
    }

    private function qr(): QrPngGenerator
    {
        return $this->qr ??= new QrPngGenerator();
    }

    private function db(): PDO
    {
        return $this->pdo ??= \App\Core\Database::getPdo();
    }
}
