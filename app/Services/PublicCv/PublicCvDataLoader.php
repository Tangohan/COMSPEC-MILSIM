<?php

declare(strict_types=1);

namespace App\Services\PublicCv;

use App\Core\Database;
use App\Repositories\AcademyRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelAwardRepository;
use App\Repositories\PilotLogbookRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TrainingCertificateRepository;
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
            'avatar_url' => $this->safe(static fn () => OperatorPortraits::forUser($tenantId, $userId)),
            'decorations' => $this->decorations($userId, $tenantId),
            'qualifications' => $this->qualifications($userId, $tenantId),
            'certificates' => $this->safe(static fn () => (new TrainingCertificateRepository())->listByUserId($userId, $tenantId)) ?? [],
            'academy' => $this->safe(fn () => (new AcademyRepository($this->pdo()))->states($tenantId, $userId)) ?? [],
            'operations' => $this->operations($userId, $tenantId),
            'flight_seconds' => $this->flightSeconds($userId, $tenantId),
            'photos' => $this->photos($userId, $tenantId),
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

    /** @return list<array<string, mixed>> */
    private function qualifications(int $userId, int $tenantId): array
    {
        $rows = $this->safe(static fn () => (new QualificationAwardRepository())->listForUser($userId, $tenantId)) ?? [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
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
