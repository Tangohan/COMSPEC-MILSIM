<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Core\Database;
use App\Core\Gate;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\MemberNameService;
use App\Services\Rbac\RbacService;
use App\Support\MemberName;
use App\Support\OrbatRosterPayload;
use App\Support\OrgVisibilityCapabilities;
use App\Support\ShortCache;
use App\Support\SteamId;
use PDO;

/**
 * ORBAT du site pour l'onglet ORBAT du BFT (GET /api/atak/community-orbat, commande GetCommunityOrbat de la DLL).
 *
 * Le jeu voit ce qu'un simple membre voit sur le site, sans droit de cadre ni de gestion : l'arbre passe par
 * {@see OrbatRosterPayload::buildForTenant()} avec des capacités vides, donc mêmes fiches masquées, unités masquées,
 * anonymisées, restreintes, archivées ou inactives. S'y ajoute le retrait des unités non publiées (« show_on_public_page »)
 * et de leurs sous-unités. La réponse est la même pour tous les joueurs de la communauté, ce qui permet de la garder en cache.
 *
 * Ni e-mail, ni identifiant Steam : le rapprochement en jeu se fait sur « tag », empreinte du Steam ID recalculée côté jeu
 * (HashText de la DLL sur le UID de chaque joueur présent) ; aucun rapprochement par nom. Sans Steam sûr : pas de tag.
 */
final class AtakCommunityOrbatService
{
    public const VERSION = 1;
    public const CACHE_TTL = 120;
    public const MAX_UNITS = 200;
    public const MAX_POSTS = 600;
    public const MAX_PEOPLE = 800;
    public const MAX_TEXT = 60;
    private const MEMBERS_PER_UNIT = 120;
    private const TAG_LENGTH = 24;
    private const CLOSED_STATUSES = ['banned', 'deleted', 'disabled', 'suspended', 'inactive'];

    public function __construct(private UnitRepository $units)
    {
    }

    /** @return array<string, mixed> */
    public function forTenant(int $tenantId): array
    {
        return ShortCache::remember('atak:orbat:' . $tenantId, self::CACHE_TTL, fn (): array => $this->build($tenantId));
    }

    /**
     * Le joueur de la session de jeu peut-il lire l'ORBAT ? Compte actif, membre de la communauté et droit
     * « organization.orbat.view » du site. Droits illisibles : refusé.
     */
    public static function viewerMayRead(int $tenantId, int $userId, UserRepository $users): bool
    {
        if ($tenantId < 1 || $userId < 1) {
            return false;
        }
        $gate = Gate::getInstance();
        $before = [$gate->permissionSlugs(), $gate->getUnitPermissionMap(), $gate->isPlatformAdmin()];
        try {
            $member = $users->findById($userId, $tenantId);
            if ($member === null || in_array(strtolower((string) ($member['status'] ?? 'active')), self::CLOSED_STATUSES, true)) {
                return false;
            }
            (new RbacService())->setPermissionsForGateFromUserRow($member + ['tenant_id' => $tenantId], $users);

            return $gate->allows('organization.orbat.view') || $gate->allows('organization.orbat.manage');
        } catch (\Throwable $e) {
            error_log('[atak-orbat] droits illisibles : ' . $e->getMessage());

            return false;
        } finally {
            $gate->setFullRbacState($before[0], $before[1]);
            $gate->setPlatformAdmin($before[2]);
        }
    }

    /** @return array<string, mixed> */
    private function build(int $tenantId): array
    {
        $root = OrbatRosterPayload::buildForTenant($this->units, $tenantId, 0, false, new OrgVisibilityCapabilities(), null, null, self::MEMBERS_PER_UNIT);

        return self::fromRoster($root, $this->identities($tenantId, self::personIds($root)), self::saltFor($tenantId), gmdate('c'));
    }

    public static function saltFor(int $tenantId): string
    {
        return substr(hash('sha256', 'atak-community-orbat|' . $tenantId), 0, 16);
    }

    /** Empreinte comparée en jeu : HashText de « orbat|sel|uid », tronqué à 24 caractères. */
    public static function steamTag(string $salt, string $steamId): string
    {
        return substr(hash('sha256', 'orbat|' . $salt . '|' . $steamId), 0, self::TAG_LENGTH);
    }

    /**
     * Steam fiable par compte. Celui du compte, sinon celui de ses sessions de jeu encore valides s'il n'y en a qu'un ;
     * un Steam porté par deux comptes ne désigne personne.
     *
     * @param array<int, string> $accountSteam user_id => steam_id du compte
     * @param list<array{user_id: int, steam_id: string}> $sessionSteam
     * @return array<int, string> user_id => SteamID64
     */
    public static function reliableSteam(array $accountSteam, array $sessionSteam): array
    {
        $bySession = [];
        foreach ($sessionSteam as $row) {
            $sid = SteamId::normalize($row['steam_id']);
            if ($sid !== null) {
                $bySession[$row['user_id']][$sid] = true;
            }
        }
        $picked = [];
        foreach ($accountSteam as $uid => $raw) {
            $sid = SteamId::normalize($raw);
            if ($sid !== null) {
                $picked[$uid] = $sid;
            }
        }
        foreach ($bySession as $uid => $set) {
            if (!isset($picked[$uid]) && count($set) === 1) {
                $picked[$uid] = (string) array_key_first($set);
            }
        }
        $owners = array_count_values($picked);

        return array_filter($picked, static fn (string $sid): bool => $owners[$sid] === 1);
    }

    /**
     * Réduit l'arbre du site à ce que le jeu doit voir (sans base : testable). Unités à plat, parent avant enfants.
     *
     * @param array<string, mixed>|null $root arbre de OrbatRosterPayload pour un simple membre
     * @param array<int, array{name?: string, callsign?: string, steam?: string}> $identities par compte
     * @return array{version: int, generated_at: string, revision: string, truncated: bool, tag_salt: string, units: list<array<string, mixed>>}
     */
    public static function fromRoster(?array $root, array $identities, string $salt, string $generatedAt): array
    {
        $out = ['units' => [], 'posts' => 0, 'people' => 0, 'truncated' => false];
        if ($root !== null) {
            if ((int) ($root['unitId'] ?? 0) > 0) {
                self::walk($root, 0, $out, $identities, $salt);
            } else {
                foreach (is_array($root['children'] ?? null) ? $root['children'] : [] as $child) {
                    if (is_array($child)) {
                        self::walk($child, 0, $out, $identities, $salt);
                    }
                }
            }
        }
        $encoded = json_encode([$out['units'], $salt], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return [
            'version' => self::VERSION,
            'generated_at' => $generatedAt,
            'revision' => sha1((string) $encoded),
            'truncated' => $out['truncated'],
            'tag_salt' => $salt,
            'units' => $out['units'],
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param array{units: list<array<string, mixed>>, posts: int, people: int, truncated: bool} $out
     * @param array<int, array{name?: string, callsign?: string, steam?: string}> $identities
     */
    private static function walk(array $node, int $parent, array &$out, array $identities, string $salt): void
    {
        // Branche masquée : aucune trace, pas même la carte de remplacement du site.
        if (!empty($node['isOrbatPlaceholder']) || (int) ($node['unitId'] ?? 0) < 1) {
            return;
        }
        if (array_key_exists('showOnPublicPage', $node) && !$node['showOnPublicPage']) {
            return;
        }
        if (count($out['units']) >= self::MAX_UNITS) {
            $out['truncated'] = true;

            return;
        }
        // Réservé avant les personnes : parent toujours avant ses enfants.
        $index = count($out['units']);
        $id = $index + 1;
        $out['units'][$index] = [];
        $redacted = !empty($node['viewerNamesRedacted']);
        $anonymizedUnit = !empty($node['isAnonymizedUnit']);
        $seen = [];
        $anonymousHolders = 0;
        $posts = [];
        foreach (is_array($node['billets'] ?? null) ? $node['billets'] : [] as $billet) {
            if (!is_array($billet)) {
                continue;
            }
            if ($out['posts'] >= self::MAX_POSTS) {
                $out['truncated'] = true;
                break;
            }
            ++$out['posts'];
            $holders = [];
            foreach (is_array($billet['holders'] ?? null) ? $billet['holders'] : [] as $holder) {
                if (!is_array($holder)) {
                    continue;
                }
                $person = self::person($holder, $identities, $salt, $out);
                if ($person !== null) {
                    $seen[$person['user']] = true;
                    $holders[] = $person;
                    $anonymousHolders += $person['anonymized'] ? 1 : 0;
                }
            }
            $posts[] = [
                'title' => self::text($billet['title'] ?? ''),
                'callsign' => $redacted ? '' : self::text($billet['org_callsign'] ?? ''),
                'key' => in_array($billet['key_post_kind'] ?? '', ['commander', 'deputy'], true) ? (string) $billet['key_post_kind'] : '',
                'vacant' => max(0, (int) ($billet['vacant'] ?? 0)),
                'holders' => $holders,
            ];
        }
        // Chef porté par l'unité sans poste de commandement défini (identifiant déjà retiré sur une unité aux noms masqués).
        $commanderId = (int) ($node['commanderUserId'] ?? 0);
        if ($commanderId > 0 && !isset($seen[$commanderId])) {
            $person = self::person(['user_id' => $commanderId, 'grade' => $node['commanderGrade'] ?? '', 'trigram' => $node['commanderTrigram'] ?? ''], $identities, $salt, $out);
            if ($person !== null) {
                $seen[$commanderId] = true;
                array_unshift($posts, ['title' => 'Chef d’unité', 'callsign' => '', 'key' => 'commander', 'vacant' => 0, 'holders' => [$person]]);
            }
        }
        $members = [];
        foreach (is_array($node['members'] ?? null) ? $node['members'] : [] as $member) {
            if (!is_array($member) || ((int) ($member['user_id'] ?? 0) > 0 && isset($seen[(int) $member['user_id']]))) {
                continue;
            }
            // Titulaire anonymisé déjà sur son poste : il figure aussi parmi les membres anonymisés, sans identifiant.
            if ((int) ($member['user_id'] ?? 0) < 1 && $anonymousHolders > 0) {
                --$anonymousHolders;
                continue;
            }
            $person = self::person($member, $identities, $salt, $out);
            if ($person !== null) {
                $seen[$person['user']] = true;
                $members[] = $person;
            }
        }
        $out['units'][$index] = [
            'id' => $id,
            'parent' => $parent,
            'name' => self::text($node['label'] ?? ''),
            'code' => $anonymizedUnit ? '' : self::text($node['unitCode'] ?? ''),
            'echelon' => $anonymizedUnit ? '' : self::text($node['echelon'] ?? ''),
            // Symbole MIL-STD de l'unité (fonction du site, hampe de QG) : repris par le téléphone pour ses titulaires.
            'function' => $anonymizedUnit ? '' : self::text($node['symbolFunction'] ?? ''),
            'hq' => !$anonymizedUnit && strlen((string) ($node['sidc'] ?? '')) === 15 && ((string) $node['sidc'])[10] === 'A',
            'restricted' => $redacted,
            'posts' => $posts,
            'members' => $members,
        ];
        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            if (is_array($child)) {
                self::walk($child, $id, $out, $identities, $salt);
            }
        }
    }

    /**
     * Une personne citée dans l'arbre. Identifiant retiré par la confidentialité : libellé de remplacement seul, sans tag
     * ni grade ; identifiant connu : nom court et indicatif relus du compte, jamais le libellé du site (repli sur l'e-mail).
     *
     * @param array<string, mixed> $row
     * @param array<int, array{name?: string, callsign?: string, steam?: string}> $identities
     * @param array{units: list<array<string, mixed>>, posts: int, people: int, truncated: bool} $out
     * @return array{user: int, tag: string, name: string, grade: string, callsign: string, trigram: string, anonymized: bool}|null
     */
    private static function person(array $row, array $identities, string $salt, array &$out): ?array
    {
        if ($out['people'] >= self::MAX_PEOPLE) {
            $out['truncated'] = true;

            return null;
        }
        ++$out['people'];
        $uid = (int) ($row['user_id'] ?? 0);
        if ($uid < 1) {
            $label = self::text($row['label'] ?? '');

            return ['user' => 0, 'tag' => '', 'name' => $label === '' ? MemberName::FALLBACK : $label, 'grade' => '', 'callsign' => '', 'trigram' => '', 'anonymized' => true];
        }
        $identity = $identities[$uid] ?? [];
        $steam = (string) ($identity['steam'] ?? '');
        $name = self::text($identity['name'] ?? '');
        $callsign = self::text($identity['callsign'] ?? '');

        return [
            'user' => $uid,
            'tag' => $steam !== '' ? self::steamTag($salt, $steam) : '',
            'name' => $name === '' && $callsign === '' ? MemberName::FALLBACK : $name,
            'grade' => self::text($row['grade'] ?? ''),
            'callsign' => $callsign,
            'trigram' => self::text($row['trigram'] ?? ''),
            'anonymized' => false,
        ];
    }

    /** Texte d'une ligne : sans balise, une seule ligne, borné ; une adresse e-mail n'en sort jamais. */
    private static function text(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $t = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
        if (str_contains($t, '@')) {
            return '';
        }

        return mb_substr($t, 0, self::MAX_TEXT);
    }

    /**
     * Comptes cités dans l'arbre (hors personnes anonymisées, déjà sans identifiant).
     *
     * @param array<string, mixed>|null $node
     * @return list<int>
     */
    private static function personIds(?array $node): array
    {
        if ($node === null) {
            return [];
        }
        $ids = [(int) ($node['commanderUserId'] ?? 0)];
        foreach (is_array($node['members'] ?? null) ? $node['members'] : [] as $m) {
            $ids[] = is_array($m) ? (int) ($m['user_id'] ?? 0) : 0;
        }
        foreach (is_array($node['billets'] ?? null) ? $node['billets'] : [] as $b) {
            foreach (is_array($b['holders'] ?? null) ? $b['holders'] : [] as $h) {
                $ids[] = is_array($h) ? (int) ($h['user_id'] ?? 0) : 0;
            }
        }
        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $c) {
            if (is_array($c)) {
                array_push($ids, ...self::personIds($c));
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Nom court (« Prénom N. »), indicatif et Steam fiable des comptes. « Masquer mes informations personnelles » de
     * la fiche : indicatif seul. Réglage illisible : indicatif seul pour tous.
     *
     * @param list<int> $userIds
     * @return array<int, array{name: string, callsign: string, steam: string}>
     */
    private function identities(int $tenantId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $pdo = Database::getPdo();
        $rows = [];
        $accountSteam = [];
        $sessions = [];
        $hidden = [];
        $hiddenKnown = true;
        foreach (array_chunk($userIds, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = $pdo->prepare('SELECT id, display_name, callsign, steam_id FROM users WHERE id IN (' . $in . ')');
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $rows[(int) $row['id']] = $row;
                $accountSteam[(int) $row['id']] = (string) ($row['steam_id'] ?? '');
            }
            try {
                $st = $pdo->prepare('SELECT DISTINCT user_id, steam_id FROM game_sessions WHERE tenant_id = ? AND revoked_at IS NULL AND steam_id IS NOT NULL AND user_id IN (' . $in . ')');
                $st->execute(array_merge([$tenantId], $chunk));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $sessions[] = ['user_id' => (int) $row['user_id'], 'steam_id' => (string) $row['steam_id']];
                }
            } catch (\Throwable $e) {
                error_log('[atak-orbat] sessions de jeu illisibles : ' . $e->getMessage());
            }
            try {
                $st = $pdo->prepare('SELECT user_id FROM user_profile_display_settings WHERE hide_personal_info = 1 AND user_id IN (' . $in . ')');
                $st->execute($chunk);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $uid) {
                    $hidden[(int) $uid] = true;
                }
            } catch (\Throwable $e) {
                $hiddenKnown = false;
                error_log('[atak-orbat] réglage d’affichage illisible : ' . $e->getMessage());
            }
        }
        try {
            $names = (new MemberNameService($pdo))->identitiesFor($tenantId, $userIds);
        } catch (\Throwable $e) {
            $names = [];
            error_log('[atak-orbat] noms illisibles : ' . $e->getMessage());
        }
        $steam = self::reliableSteam($accountSteam, $sessions);
        $out = [];
        foreach ($rows as $uid => $row) {
            $callsign = trim((string) ($row['callsign'] ?? ''));
            $name = '';
            if ($hiddenKnown && !isset($hidden[$uid])) {
                $short = MemberName::short(MemberName::withIdentity(['display_name' => (string) ($row['display_name'] ?? ''), 'callsign' => $callsign], $names[$uid] ?? null));
                $name = $short === MemberName::FALLBACK ? '' : $short;
            }
            $out[$uid] = ['name' => $name, 'callsign' => $callsign, 'steam' => $steam[$uid] ?? ''];
        }

        return $out;
    }
}
