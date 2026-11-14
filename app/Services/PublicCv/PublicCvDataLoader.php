<?php

declare(strict_types=1);

namespace App\Services\PublicCv;

use App\Core\Database;
use App\Repositories\AcademyRepository;
use App\Repositories\AtakDistanceRepository;
use App\Repositories\AtakScreenTimeRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAwardRepository;
use App\Repositories\PersonnelMobilityRequestRepository;
use App\Repositories\PersonnelOrgHistoryRepository;
use App\Repositories\PersonnelStageBilanRepository;
use App\Repositories\PilotLogbookRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\UserCommunityMembershipRepository;
use App\Repositories\UserDiscordLinkRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PilotLogbookService;
use App\Support\CommunityMediaDetails;
use App\Support\OperatorPortraits;
use App\Support\VisibilityLevel;
use PDO;

/**
 * Lit, pour la page publique d’un joueur, les données déjà existantes du portail.
 *
 * Ne lit volontairement jamais : e-mail, identité civile (user_profiles / identité légale), dossier médical,
 * dossier sécurisé, notes de commandement. Chaque bloc est indépendant : une table absente donne un bloc vide.
 * Les données brutes passent ensuite par PublicCvPresenter, qui ne garde que les champs affichables.
 */
class PublicCvDataLoader
{
    private const OPERATIONS_LIST = 6;
    private const PHOTOS_LIST = 9;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * @return array<string, mixed>|null null quand le compte n’est plus membre actif de la communauté
     */
    public function load(int $userId, int $tenantId): ?array
    {
        if ($userId < 1 || $tenantId < 1) {
            return null;
        }
        $user = (new UserRepository($this->pdo()))->findById($userId, $tenantId);
        if ($user === null) {
            return null;
        }
        if (strtolower(trim((string) ($user['status'] ?? ''))) !== 'active' || trim((string) ($user['deleted_at'] ?? '')) !== '') {
            return null;
        }

        return [
            'user' => $user,
            'grade' => $this->grade($user, $tenantId),
            'unit_name' => $this->unitName($userId, $tenantId),
            'tenant' => $this->tenant($tenantId),
            'banner_url' => $this->bannerUrl($tenantId),
            'avatar_url' => $this->safe(static fn () => OperatorPortraits::forUser($tenantId, $userId)),
            'decorations' => $this->decorations($userId, $tenantId),
            'qualifications' => $this->qualifications($userId, $tenantId),
            'certificates' => $this->safe(static fn () => (new TrainingCertificateRepository())->listByUserId($userId, $tenantId)) ?? [],
            'academy' => $this->safe(fn () => (new AcademyRepository($this->pdo()))->states($tenantId, $userId)) ?? [],
            'operations' => $this->operations($userId, $tenantId),
            'flight_seconds' => $this->flightSeconds($userId, $tenantId),
            'photos' => $this->photos($userId, $tenantId),
            'jobs' => $this->jobs($userId, $tenantId),
            'unit_post' => $this->unitPost($userId, $tenantId),
            'distances' => $this->safe(fn () => (new AtakDistanceRepository($this->pdo()))->summaryForUser($tenantId, $userId)),
            'play' => $this->safe(fn () => (new AtakScreenTimeRepository($this->pdo()))->playStatsForUser($tenantId, $userId)),
            'game_roles' => $this->safe(fn () => (new AtakScreenTimeRepository($this->pdo()))->rolesForUser($tenantId, $userId)) ?? [],
            'member_since' => $this->memberSince($user, $tenantId),
            'promotions' => $this->promotions($userId, $tenantId),
            'evaluations' => $this->evaluations($userId, $tenantId),
            'mutations' => $this->mutations($userId, $tenantId),
            'discord' => $this->discord($userId),
        ];
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T|null
     */
    private function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Date (Y-m-d) d'arrivée dans le séjour en cours, null si inconnue. */
    private function periodStart(int $userId, int $tenantId): ?string
    {
        $v = $this->safe(fn () => (new UserCommunityMembershipRepository($this->pdo()))->currentPeriodStart($userId, $tenantId));

        return is_string($v) && $v !== '' ? substr($v, 0, 10) : null;
    }

    /**
     * Notes et commentaires des bilans du commandement de CETTE communauté, séjour en cours.
     * Les bilans RH et de recrutement restent privés. Vide si la communauté a coupé les bilans.
     *
     * @return array{average: ?float, items: list<array<string, mixed>>}
     */
    private function evaluations(int $userId, int $tenantId): array
    {
        $empty = ['average' => null, 'items' => []];
        $on = $this->safe(static fn () => \App\Services\Personnel\RoleplayFollowupSettings::individualBilansEnabled(\App\Services\Personnel\RoleplayFollowupSettings::forTenant($tenantId)));
        if ($on !== true) {
            return $empty;
        }
        $since = $this->periodStart($userId, $tenantId);
        $rows = $this->safe(static fn () => (new PersonnelStageBilanRepository())->listForUser($tenantId, $userId, 30)) ?? [];
        $items = [];
        $sum = 0;
        $n = 0;
        foreach ($rows as $r) {
            if ((string) ($r['bilan_kind'] ?? '') !== PersonnelStageBilanRepository::KIND_COMMANDEMENT) {
                continue;
            }
            $date = (string) ($r['event_date'] ?? '');
            if ($since !== null && $date !== '' && $date < $since) {
                continue;
            }
            $rating = isset($r['rating']) && $r['rating'] !== '' ? max(1, min(5, (int) $r['rating'])) : null;
            if ($rating !== null) {
                $sum += $rating;
                ++$n;
            }
            $items[] = [
                'date' => $date,
                'stage' => (string) ($r['stage_label'] ?? ''),
                'title' => (string) ($r['title'] ?? ''),
                'rating' => $rating,
                'comment' => mb_substr(trim((string) ($r['body'] ?? '')), 0, 400),
            ];
        }

        return ['average' => $n > 0 ? round($sum / $n, 1) : null, 'items' => array_slice($items, 0, 10)];
    }

    /**
     * Mutations de CETTE communauté, séjour en cours : changements d'unité, de poste ou d'affectation
     * (historique du dossier + demandes de mobilité acceptées). Jamais les motifs ni les notes du commandement.
     *
     * @return list<array{date: string, text: string}>
     */
    private function mutations(int $userId, int $tenantId): array
    {
        $since = $this->periodStart($userId, $tenantId);
        $out = [];
        $hist = $this->safe(static fn () => (new PersonnelOrgHistoryRepository())->listForUser($tenantId, $userId, 60)) ?? [];
        foreach ($hist as $h) {
            $text = trim((string) ($h['summary'] ?? ''));
            $date = substr((string) ($h['created_at'] ?? ''), 0, 10);
            if ($text === '' || preg_match('/(?:^|·)\s*(?:Unité|Poste|Fonction|Affectation)\s*:/u', $text) !== 1) {
                continue;
            }
            if ($since !== null && $date !== '' && $date < $since) {
                continue;
            }
            $out[] = ['date' => $date, 'text' => $text];
        }
        $mob = $this->safe(static fn () => (new PersonnelMobilityRequestRepository())->listForUser($tenantId, $userId, 30)) ?? [];
        foreach ($mob as $m) {
            if (!in_array((string) ($m['status'] ?? ''), ['approved', 'applied'], true)) {
                continue;
            }
            $type = (string) ($m['request_type'] ?? '');
            if (!in_array($type, ['unit_change', 'specialty_change', 'assignment'], true)) {
                continue;
            }
            $date = substr((string) ($m['created_at'] ?? ''), 0, 10);
            if ($since !== null && $date !== '' && $date < $since) {
                continue;
            }
            $label = PersonnelMobilityRequestRepository::TYPE_LABELS[$type] ?? $type;
            $target = trim((string) ($m['target_label'] ?? ''));
            $out[] = ['date' => $date, 'text' => $label . ($target !== '' ? ' : ' . $target : '')];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return array_slice($out, 0, 15);
    }

    /** @return array{name: string, url: string}|null seulement un compte Discord lié et vérifié par OAuth */
    private function discord(int $userId): ?array
    {
        $row = $this->safe(fn () => (new UserDiscordLinkRepository($this->pdo()))->find($userId));
        $id = is_array($row) ? trim((string) ($row['discord_user_id'] ?? '')) : '';
        if ($id === '' || preg_match('/^\d{5,25}$/', $id) !== 1) {
            return null;
        }
        $name = trim((string) ($row['discord_username'] ?? ''));

        return ['name' => $name !== '' ? $name : 'Discord', 'url' => 'https://discord.com/users/' . $id];
    }

    /** @return list<array<string, mixed>> */
    private function decorations(int $userId, int $tenantId): array
    {
        // Avec l’insigne quand la colonne existe ; sinon la liste simple du dossier.
        return $this->safe(function () use ($userId, $tenantId): array {
            $st = $this->pdo()->prepare(
                'SELECT d.name AS definition_name, d.decoration_grade, d.image_path, a.awarded_at
                 FROM personnel_awards a INNER JOIN award_definitions d ON d.id = a.definition_id
                 WHERE a.tenant_id = ? AND a.personnel_id = ?
                 ORDER BY a.awarded_at DESC, a.id DESC'
            );
            $st->execute([$tenantId, $userId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }) ?? $this->safe(static fn () => (new PersonnelAwardRepository())->listForPersonnel($tenantId, $userId)) ?? [];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    private function grade(array $user, int $tenantId): ?array
    {
        $gradeId = (int) ($user['grade_id'] ?? 0);
        if ($gradeId < 1) {
            return null;
        }

        return $this->safe(static fn () => (new GradeRepository())->findById($gradeId, $tenantId));
    }

    private function unitName(int $userId, int $tenantId): ?string
    {
        return $this->safe(function () use ($userId, $tenantId): ?string {
            $st = $this->pdo()->prepare(
                'SELECT un.name FROM user_units uu
                 INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = ?
                 WHERE uu.user_id = ? AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
                 ORDER BY uu.is_primary DESC, uu.id DESC LIMIT 1'
            );
            $st->execute([$tenantId, $userId]);
            $name = $st->fetchColumn();

            return is_string($name) && trim($name) !== '' ? trim($name) : null;
        });
    }

    /** @return array<string, mixed>|null */
    private function tenant(int $tenantId): ?array
    {
        return $this->safe(fn () => (new TenantRepository($this->pdo()))->findById($tenantId));
    }

    /** Bannière que la communauté affiche déjà sur sa page publique (réglage de la vitrine). */
    private function bannerUrl(int $tenantId): ?string
    {
        return $this->safe(function () use ($tenantId): ?string {
            $st = $this->pdo()->prepare('SELECT banner_url FROM tenant_branding WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $url = $st->fetchColumn();

            return is_string($url) && trim($url) !== '' ? trim($url) : null;
        });
    }

    /** @return list<array<string, mixed>> */
    private function qualifications(int $userId, int $tenantId): array
    {
        $rows = $this->safe(static fn () => (new QualificationAwardRepository())->listForUser($userId, $tenantId)) ?? [];
        // Qualifications que la communauté a choisi de ne pas montrer sur la page publique.
        $hidden = array_flip($this->safe(static fn () => (new \App\Repositories\QualificationAwardRuleRepository())->hiddenDefinitionIds($tenantId, 'show_on_profile')) ?? []);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || isset($hidden[(int) ($row['definition_id'] ?? 0)])) {
                continue;
            }
            // Jamais une qualification masquée, restreinte ou anonymisée sur la fiche interne.
            if (VisibilityLevel::normalize((string) ($row['visibility_level'] ?? VisibilityLevel::NORMAL)) !== VisibilityLevel::NORMAL) {
                continue;
            }
            if (!in_array(strtolower((string) ($row['status'] ?? 'valid')), ['valid', 'expiring'], true)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /** @return array{count: int, recent: list<array<string, mixed>>} */
    private function operations(int $userId, int $tenantId): array
    {
        $empty = ['count' => 0, 'recent' => []];
        $where = "e.tenant_id = ? AND r.user_id = ? AND e.cancelled_at IS NULL AND e.starts_at < NOW()
                  AND (r.status = 'yes' OR r.checked_in_at IS NOT NULL)";

        return $this->safe(function () use ($userId, $tenantId, $where): array {
            $st = $this->pdo()->prepare(
                "SELECT COUNT(*) FROM community_event_rsvps r INNER JOIN community_events e ON e.id = r.event_id WHERE {$where}"
            );
            $st->execute([$tenantId, $userId]);
            $count = (int) $st->fetchColumn();
            $st = $this->pdo()->prepare(
                "SELECT e.title, e.starts_at, e.event_type FROM community_event_rsvps r
                 INNER JOIN community_events e ON e.id = r.event_id
                 WHERE {$where} ORDER BY e.starts_at DESC LIMIT " . self::OPERATIONS_LIST
            );
            $st->execute([$tenantId, $userId]);

            return ['count' => $count, 'recent' => $st->fetchAll(PDO::FETCH_ASSOC) ?: []];
        }) ?? $empty;
    }

    private function flightSeconds(int $userId, int $tenantId): int
    {
        $entries = $this->safe(fn () => (new PilotLogbookRepository($this->pdo()))->listForUser($tenantId, $userId)) ?? [];
        $legacy = $this->safe(function () use ($userId, $tenantId): array {
            $st = $this->pdo()->prepare(
                'SELECT vehicle_class, vehicle_label, seconds FROM atak_pilot_time WHERE tenant_id = ? AND user_id = ?'
            );
            $st->execute([$tenantId, $userId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }) ?? [];
        if ($entries === [] && $legacy === []) {
            return 0;
        }

        return (int) ($this->safe(static fn () => PilotLogbookService::summary($entries, $legacy)['total']) ?? 0);
    }

    /**
     * Fonctions (emplois) du joueur dans la communauté, la principale en tête — comme la colonne « Fonction » des Effectifs.
     *
     * Seuls l’icône et l’abrégé de la fonction sont repris (repères visuels publics), jamais sa description ni ses étiquettes.
     *
     * @return list<array{name: string, detail: string, is_primary: int, icon?: ?string, abbreviation?: ?string}>
     */
    private function jobs(int $userId, int $tenantId): array
    {
        return $this->safe(function () use ($userId, $tenantId): array {
            $st = $this->pdo()->prepare(
                'SELECT r.name, pj.role_detail AS detail, pj.is_primary, pj.personnel_job_role_id AS role_id
                 FROM personnel_profile_job_roles pj
                 INNER JOIN personnel_job_roles r ON r.id = pj.personnel_job_role_id AND r.tenant_id = pj.tenant_id
                 WHERE pj.tenant_id = ? AND pj.user_id = ?
                 ORDER BY pj.is_primary DESC, pj.sort_order ASC, pj.id ASC LIMIT 6'
            );
            $st->execute([$tenantId, $userId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $decorations = $rows !== [] ? (new \App\Services\Personnel\JobRoleEnrichmentService($this->pdo()))->decorations($tenantId) : [];
            foreach ($rows as &$row) {
                $decoration = $decorations[(int) ($row['role_id'] ?? 0)] ?? [];
                $row['icon'] = $decoration['icon'] ?? null;
                $row['abbreviation'] = $decoration['abbreviation'] ?? null;
                unset($row['role_id']);
            }
            unset($row);

            return $rows;
        }) ?? [];
    }

    /**
     * Poste tenu dans l’unité d’affectation principale (organigramme), avec le nom de cette unité.
     *
     * @return array{role_name: ?string, unit_name: ?string}|null
     */
    private function unitPost(int $userId, int $tenantId): ?array
    {
        return $this->safe(function () use ($userId, $tenantId): ?array {
            $st = $this->pdo()->prepare(
                "SELECT pa.role_name, un.name AS unit_name FROM personnel_assignments pa
                 INNER JOIN units un ON un.id = pa.unit_id AND un.tenant_id = ?
                 WHERE pa.user_id = ? AND (pa.status = 'active' OR pa.status = '' OR pa.status IS NULL)
                   AND (pa.ended_at IS NULL OR pa.ended_at >= CURDATE())
                 ORDER BY pa.is_primary DESC, pa.started_at DESC, pa.id DESC LIMIT 1"
            );
            $st->execute([$tenantId, $userId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        });
    }

    /**
     * Date d’arrivée dans la communauté : adhésion active, sinon création du compte pour la communauté d’origine.
     *
     * @param array<string, mixed> $user
     */
    private function memberSince(array $user, int $tenantId): ?string
    {
        $joined = $this->safe(function () use ($user, $tenantId): ?string {
            $st = $this->pdo()->prepare(
                "SELECT MIN(joined_at) FROM user_community_memberships WHERE user_id = ? AND tenant_id = ? AND status = 'active'"
            );
            $st->execute([(int) ($user['id'] ?? 0), $tenantId]);
            $v = $st->fetchColumn();

            return is_string($v) && trim($v) !== '' ? $v : null;
        });
        if ($joined !== null) {
            return $joined;
        }
        $created = trim((string) ($user['created_at'] ?? ''));

        return $created !== '' && (int) ($user['tenant_id'] ?? 0) === $tenantId ? $created : null;
    }

    /**
     * Promotions de grade du dossier (date + texte structuré « Grade : … · Fonction : … »). Le titre, les motifs et les
     * autres types d’évènement ne sont jamais lus. Seules les lignes dont le grade existe dans la communauté sont
     * gardées (le journal n’est pas rangé par communauté).
     *
     * @return list<array{event_date: string, description: string}>
     */
    private function promotions(int $userId, int $tenantId): array
    {
        $grades = $this->safe(static function () use ($tenantId): array {
            $labels = [];
            foreach ((new GradeRepository())->listForTenant($tenantId) as $g) {
                foreach (['label_long', 'label_short', 'name', 'short_name'] as $k) {
                    $v = mb_strtolower(trim((string) ($g[$k] ?? '')));
                    if ($v !== '') {
                        $labels[$v] = true;
                    }
                }
            }

            return $labels;
        }) ?? [];
        if ($grades === []) {
            return [];
        }
        $since = $this->periodStart($userId, $tenantId);
        $rows = $this->safe(function () use ($userId, $since): array {
            // Seulement le séjour en cours : les promotions d'une présence précédente (ici ou ailleurs) ne reviennent pas.
            $st = $this->pdo()->prepare(
                "SELECT event_date, description FROM personnel_service_history
                 WHERE user_id = ? AND event_type = 'promotion'" . ($since !== null ? ' AND event_date >= ?' : '') . "
                 ORDER BY event_date DESC, id DESC LIMIT 20"
            );
            $st->execute($since !== null ? [$userId, $since] : [$userId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }) ?? [];
        $out = [];
        foreach ($rows as $r) {
            if (preg_match('/(?:^|·)\s*Grade\s*:\s*([^·]+)/u', (string) ($r['description'] ?? ''), $m) === 1
                && isset($grades[mb_strtolower(trim($m[1]))])) {
                $out[] = ['event_date' => (string) ($r['event_date'] ?? ''), 'description' => (string) ($r['description'] ?? '')];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function photos(int $userId, int $tenantId): array
    {
        return $this->safe(function () use ($userId, $tenantId): array {
            // Seulement ce que la communauté publie déjà sur sa page publique, que ce joueur a déposé,
            // et sans floutage à appliquer (le floutage se fait à l’affichage de la page de la communauté).
            $st = $this->pdo()->prepare(
                'SELECT title, storage_path FROM community_media_items
                 WHERE tenant_id = ? AND created_by = ? AND show_on_public_page = 1 AND status = ? AND media_kind = ?
                   AND storage_path IS NOT NULL AND (blur_mode IS NULL OR blur_mode = ?)
                 ORDER BY id DESC LIMIT ' . self::PHOTOS_LIST
            );
            $st->execute([
                $tenantId,
                $userId,
                CommunityMediaDetails::STATUS_PUBLISHED,
                CommunityMediaDetails::KIND_IMAGE,
                CommunityMediaDetails::BLUR_NONE,
            ]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }) ?? [];
    }
}
