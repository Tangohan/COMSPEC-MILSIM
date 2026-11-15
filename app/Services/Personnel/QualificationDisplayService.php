<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Core\Database;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use PDO;
use Throwable;

/**
 * Affichage des qualifications d'un membre, qualification par qualification : visible sur Arma (fiche opérateur que le
 * site envoie au jeu) et visible sur le profil (profil vu par les autres membres, carte de l'annuaire JNET, C.V. public).
 *
 * Le masquage est un choix d'AFFICHAGE, jamais un retrait : la qualification reste détenue et continue de compter pour
 * les prérequis d'emploi, les formations, les kits, les accès SSE, les spécialités air et les alertes d'échéance. Ces
 * lectures-là n'appellent pas ce service.
 *
 * Une attribution sans ligne dans personnel_qualification_display est visible aux deux endroits. Sans la table (étape
 * qualification_display_prefs non appliquée), tout reste visible et rien ne s'enregistre : `ready()` répond false.
 */
final class QualificationDisplayService
{
    public const GAME = 'game';
    public const PROFILE = 'profile';

    public const TABLE = 'personnel_qualification_display';

    private const COLUMNS = [self::GAME => 'show_in_game', self::PROFILE => 'show_on_profile'];

    private PDO $pdo;
    private ?bool $ready = null;

    /** @var (callable(int, int): bool)|null le compte appartient-il à la communauté ? */
    private $isMember;

    /**
     * @param (callable(int, int): bool)|null $isMember (membre, communauté) : appartenance ; par défaut UserRepository
     */
    public function __construct(?PDO $pdo = null, ?callable $isMember = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->isMember = $isMember;
    }

    /** La table des choix d'affichage existe-t-elle (étape de mise à jour appliquée) ? */
    public function ready(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            $this->pdo->query('SELECT qualification_id FROM ' . self::TABLE . ' LIMIT 1');
            $this->ready = true;
        } catch (Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    public static function isSurface(string $surface): bool
    {
        return isset(self::COLUMNS[$surface]);
    }

    /**
     * État d'affichage des attributions données. Une attribution absente du résultat est visible partout.
     *
     * @param list<int> $awardIds
     * @return array<int, array{game: bool, profile: bool}>
     */
    public function states(array $awardIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $awardIds), static fn (int $id): bool => $id > 0)));
        if ($ids === [] || !$this->ready()) {
            return [];
        }
        $out = [];
        try {
            foreach (array_chunk($ids, 500) as $chunk) {
                $st = $this->pdo->prepare(
                    'SELECT qualification_id, show_in_game, show_on_profile FROM ' . self::TABLE
                    . ' WHERE qualification_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
                );
                $st->execute($chunk);
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $out[(int) $row['qualification_id']] = [
                        self::GAME => (int) $row['show_in_game'] !== 0,
                        self::PROFILE => (int) $row['show_on_profile'] !== 0,
                    ];
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * Ajoute à chaque attribution `display_game` et `display_profile` (true : visible).
     *
     * @param list<array<string, mixed>> $rows attributions (clé `id`)
     * @return list<array<string, mixed>>
     */
    public function decorate(array $rows): array
    {
        $states = $this->states(array_map(static fn ($r): int => is_array($r) ? (int) ($r['id'] ?? 0) : 0, $rows));
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $state = $states[(int) ($row['id'] ?? 0)] ?? [self::GAME => true, self::PROFILE => true];
            $row['display_game'] = $state[self::GAME];
            $row['display_profile'] = $state[self::PROFILE];
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Données du bloc « Affichage des qualifications » (views/partials/qualification_display.php) : une ligne par
     * attribution, avec son nom, son niveau, sa date d'obtention et l'état des deux interrupteurs.
     *
     * @param list<array<string, mixed>> $awards attributions du membre (QualificationAwardRepository::listForUser)
     * @param string $url adresse d'enregistrement (POST)
     * @param bool $canToggle le lecteur peut-il basculer (le membre lui-même, ou un responsable) ?
     * @return array{ready: bool, can_toggle: bool, url: string, rows: list<array{id: int, name: string, level: string, date: string, game: bool, profile: bool}>}
     */
    public function panel(array $awards, string $url, bool $canToggle): array
    {
        $rows = [];
        foreach ($this->decorate($awards) as $award) {
            $id = (int) ($award['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $name = trim((string) ($award['definition_name'] ?? $award['qualification_name'] ?? $award['name'] ?? ''));
            $ts = trim((string) ($award['obtained_at'] ?? '')) !== '' ? strtotime((string) $award['obtained_at']) : false;
            $rows[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : 'Qualification',
                'level' => trim((string) ($award['level_name'] ?? $award['level_short_name'] ?? $award['level'] ?? '')),
                'date' => $ts !== false ? date('d/m/Y', $ts) : '',
                'game' => (bool) $award['display_game'],
                'profile' => (bool) $award['display_profile'],
            ];
        }

        return ['ready' => $this->ready(), 'can_toggle' => $canToggle, 'url' => $url, 'rows' => $rows];
    }

    /**
     * Retire les attributions que le membre (ou son responsable) a masquées pour cet endroit. L'ordre est conservé.
     *
     * @param list<array<string, mixed>> $rows attributions (clé `id`)
     * @return list<array<string, mixed>>
     */
    public function visibleOn(array $rows, string $surface): array
    {
        if (!self::isSurface($surface) || $rows === []) {
            return array_values($rows);
        }
        $states = $this->states(array_map(static fn ($r): int => is_array($r) ? (int) ($r['id'] ?? 0) : 0, $rows));
        if ($states === []) {
            return array_values($rows);
        }
        $out = [];
        foreach ($rows as $row) {
            $id = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
            if (isset($states[$id]) && $states[$id][$surface] === false) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Bascule l'affichage d'une qualification du membre ($awardId), ou de toutes ($awardId = null : « tout afficher » /
     * « tout masquer » d'une colonne). Qui peut : le membre pour ses propres qualifications, ou un responsable
     * ($actorCanManage, droits existants de gestion de la fiche et des qualifications). Tout est borné à la communauté
     * de la session : une attribution d'un autre membre ou d'une autre communauté est introuvable.
     *
     * @return array{ok: bool, status: int, code: string, changed: int, states: array<int, array{game: bool, profile: bool}>}
     */
    public function apply(int $tenantId, int $actorId, bool $actorCanManage, int $memberId, ?int $awardId, string $surface, bool $visible): array
    {
        $fail = static fn (int $status, string $code): array => ['ok' => false, 'status' => $status, 'code' => $code, 'changed' => 0, 'states' => []];
        if ($tenantId < 1 || $actorId < 1) {
            return $fail(401, 'session');
        }
        if (!self::isSurface($surface) || $memberId < 1 || ($awardId !== null && $awardId < 1)) {
            return $fail(422, 'invalid');
        }
        if ($actorId !== $memberId && !$actorCanManage) {
            return $fail(403, 'forbidden');
        }
        if (!$this->memberOfTenant($memberId, $tenantId)) {
            return $fail(404, 'not_found');
        }
        if (!$this->ready()) {
            return $fail(409, 'not_ready');
        }

        $sql = 'SELECT id, qualification_name FROM personnel_qualifications WHERE user_id = ? AND (tenant_id = ? OR tenant_id IS NULL)';
        $args = [$memberId, $tenantId];
        if ($awardId !== null) {
            $sql .= ' AND id = ?';
            $args[] = $awardId;
        }
        try {
            $st = $this->pdo->prepare($sql . ' ORDER BY id');
            $st->execute($args);
            $awards = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return $fail(500, 'error');
        }
        if ($awards === [] && $awardId !== null) {
            return $fail(404, 'not_found');
        }

        $ids = array_map(static fn (array $a): int => (int) $a['id'], $awards);
        $before = $this->states($ids);
        $column = self::COLUMNS[$surface];
        $now = date('Y-m-d H:i:s');
        $changed = 0;
        $states = [];
        try {
            foreach ($awards as $award) {
                $id = (int) $award['id'];
                $state = $before[$id] ?? [self::GAME => true, self::PROFILE => true];
                $states[$id] = $state;
                if ($state[$surface] === $visible) {
                    continue;
                }
                if (isset($before[$id])) {
                    $up = $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET ' . $column . ' = ?, updated_by = ?, updated_at = ? WHERE qualification_id = ?');
                    $up->execute([$visible ? 1 : 0, $actorId, $now, $id]);
                } else {
                    $ins = $this->pdo->prepare(
                        'INSERT INTO ' . self::TABLE . ' (qualification_id, tenant_id, user_id, show_in_game, show_on_profile, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $ins->execute([
                        $id,
                        $tenantId,
                        $memberId,
                        $surface === self::GAME ? ($visible ? 1 : 0) : 1,
                        $surface === self::PROFILE ? ($visible ? 1 : 0) : 1,
                        $actorId,
                        $now,
                    ]);
                }
                $states[$id][$surface] = $visible;
                $changed++;
                $this->audit($tenantId, $actorId, $memberId, $id, (string) ($award['qualification_name'] ?? ''), $surface, !$visible, $visible);
            }
        } catch (Throwable $e) {
            error_log('[qualifications] affichage : ' . $e->getMessage());

            return $fail(500, 'error');
        }

        return ['ok' => true, 'status' => 200, 'code' => 'saved', 'changed' => $changed, 'states' => $states];
    }

    private function memberOfTenant(int $memberId, int $tenantId): bool
    {
        try {
            if ($this->isMember !== null) {
                return (bool) ($this->isMember)($memberId, $tenantId);
            }

            return \App\Core\Container::get(\App\Repositories\UserRepository::class)->isMemberOfTenant($memberId, $tenantId);
        } catch (Throwable) {
            return false;
        }
    }

    /** Journal d'audit : qui, quelle qualification de quel membre, quel endroit, ancien et nouvel état. Ne bloque jamais. */
    private function audit(int $tenantId, int $actorId, int $memberId, int $awardId, string $name, string $surface, bool $old, bool $new): void
    {
        try {
            $context = ['member_user_id' => $memberId, 'qualification' => $name, 'surface' => $surface];
            (new AuditService())->logChange(
                AuditAction::QUALIFICATION_DISPLAY_UPDATED,
                $tenantId,
                $actorId,
                'personnel_qualification',
                $awardId,
                $context + ['visible' => $old],
                $context + ['visible' => $new],
            );
        } catch (Throwable $e) {
            error_log('[qualifications] audit affichage : ' . $e->getMessage());
        }
    }
}
