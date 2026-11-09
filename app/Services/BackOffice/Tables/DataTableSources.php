<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Tables;

use App\Core\Database;
use App\Core\Gate;
use App\Repositories\GradeRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\NoAccountDirectory;
use App\Support\EffectifsLmsAccess;
use PDO;

/**
 * Sources de données du constructeur de tableaux : liste fermée, colonnes fixes, droits par source.
 *
 * Rien n'est construit à partir d'un texte saisi : la personne choisit des clés du catalogue, jamais du SQL. Chaque
 * chargeur ne lit que la communauté de la session (tenant_id) et ne renvoie que les membres de cette communauté.
 * Pas d'adresse e-mail, d'identifiant Steam, de note ni de donnée médicale dans le catalogue.
 *
 * Types de colonne : text, number, date (AAAA-MM-JJ).
 */
final class DataTableSources
{
    public const MAX_ROWS = 5000;

    /**
     * @return array<string, array{label: string, hint: string, columns: array<string, array{label: string, type: string}>}>
     */
    public static function catalog(): array
    {
        return [
            'effectifs' => [
                'label' => 'Effectifs',
                'hint' => 'Tous les membres de la communauté, avec ou sans compte.',
                'columns' => [
                    'name' => ['label' => 'Nom', 'type' => 'text'],
                    'first_name' => ['label' => 'Prénom', 'type' => 'text'],
                    'last_name' => ['label' => 'Nom de famille', 'type' => 'text'],
                    'callsign' => ['label' => 'Indicatif', 'type' => 'text'],
                    'grade' => ['label' => 'Grade', 'type' => 'text'],
                    'unit' => ['label' => 'Unité principale', 'type' => 'text'],
                    'status' => ['label' => 'Statut', 'type' => 'text'],
                    'account' => ['label' => 'Compte', 'type' => 'text'],
                    'member_number' => ['label' => 'Matricule', 'type' => 'text'],
                    'steam' => ['label' => 'Steam renseigné', 'type' => 'text'],
                    'created_at' => ['label' => 'Arrivée', 'type' => 'date'],
                    'last_login' => ['label' => 'Dernière connexion', 'type' => 'date'],
                ],
            ],
            'sans_comptes' => [
                'label' => 'Personnes sans compte',
                'hint' => 'Fiches sans compte, invitations en cours et comptes à activer.',
                'columns' => [
                    'name' => ['label' => 'Nom', 'type' => 'text'],
                    'callsign' => ['label' => 'Indicatif', 'type' => 'text'],
                    'status' => ['label' => 'Statut', 'type' => 'text'],
                    'source' => ['label' => 'Origine', 'type' => 'text'],
                    'created_at' => ['label' => 'Créée le', 'type' => 'date'],
                    'last_activity' => ['label' => 'Dernière activité', 'type' => 'date'],
                ],
            ],
            'affectations' => [
                'label' => 'Affectations',
                'hint' => 'Qui tient quel poste dans quelle unité.',
                'columns' => [
                    'member' => ['label' => 'Membre', 'type' => 'text'],
                    'callsign' => ['label' => 'Indicatif', 'type' => 'text'],
                    'unit' => ['label' => 'Unité', 'type' => 'text'],
                    'role' => ['label' => 'Rôle', 'type' => 'text'],
                    'primary' => ['label' => 'Principale', 'type' => 'text'],
                    'status' => ['label' => 'Statut', 'type' => 'text'],
                    'started_at' => ['label' => 'Début', 'type' => 'date'],
                    'ended_at' => ['label' => 'Fin', 'type' => 'date'],
                ],
            ],
            'qualifications' => [
                'label' => 'Qualifications',
                'hint' => 'Qualifications détenues, niveau et validité.',
                'columns' => [
                    'member' => ['label' => 'Membre', 'type' => 'text'],
                    'callsign' => ['label' => 'Indicatif', 'type' => 'text'],
                    'qualification' => ['label' => 'Qualification', 'type' => 'text'],
                    'level' => ['label' => 'Niveau', 'type' => 'text'],
                    'status' => ['label' => 'Statut', 'type' => 'text'],
                    'obtained_at' => ['label' => 'Obtenue le', 'type' => 'date'],
                    'expires_at' => ['label' => 'Expire le', 'type' => 'date'],
                    'days_left' => ['label' => 'Jours restants', 'type' => 'number'],
                ],
            ],
            'presences' => [
                'label' => 'Présences',
                'hint' => 'Réponses et pointages aux événements des 12 derniers mois.',
                'columns' => [
                    'member' => ['label' => 'Membre', 'type' => 'text'],
                    'callsign' => ['label' => 'Indicatif', 'type' => 'text'],
                    'event' => ['label' => 'Événement', 'type' => 'text'],
                    'event_type' => ['label' => 'Type', 'type' => 'text'],
                    'starts_at' => ['label' => 'Date', 'type' => 'date'],
                    'answer' => ['label' => 'Réponse', 'type' => 'text'],
                    'checked_in' => ['label' => 'Présent', 'type' => 'text'],
                ],
            ],
            'materiel' => [
                'label' => 'Matériel',
                'hint' => 'Dotation nominative : qui a reçu quoi.',
                'columns' => [
                    'member' => ['label' => 'Membre', 'type' => 'text'],
                    'callsign' => ['label' => 'Indicatif', 'type' => 'text'],
                    'item' => ['label' => 'Article', 'type' => 'text'],
                    'category' => ['label' => 'Catégorie', 'type' => 'text'],
                    'serial' => ['label' => 'N° de série', 'type' => 'text'],
                    'status' => ['label' => 'Statut', 'type' => 'text'],
                    'assigned_at' => ['label' => 'Remis le', 'type' => 'date'],
                    'returned_at' => ['label' => 'Rendu le', 'type' => 'date'],
                ],
            ],
        ];
    }

    /** La personne a-t-elle le droit de voir cette source ? Défaut : refusé. */
    public static function allowed(string $source, Gate $gate): bool
    {
        if (!isset(self::catalog()[$source])) {
            return false;
        }
        $admin = $gate->allows('admin.organization') || $gate->allows('admin.access');

        return match ($source) {
            'effectifs', 'sans_comptes', 'affectations', 'qualifications' => EffectifsLmsAccess::allows($gate),
            'presences' => $admin || $gate->allows('events.manage'),
            'materiel' => $admin || $gate->allows('personnel.equipment.manage'),
            default => false,
        };
    }

    /**
     * Catalogue limité aux sources que la personne peut voir.
     *
     * @return array<string, array{label: string, hint: string, columns: array<string, array{label: string, type: string}>}>
     */
    public static function availableFor(Gate $gate): array
    {
        return array_filter(self::catalog(), static fn (string $key): bool => self::allowed($key, $gate), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Toutes les colonnes de la source, une ligne par enregistrement. Le droit est vérifié par l'appelant (allowed()).
     *
     * @return array{rows: list<array<string, string|float>>, truncated: bool}
     */
    public function load(string $source, int $tenantId): array
    {
        if ($tenantId < 1 || !isset(self::catalog()[$source])) {
            return ['rows' => [], 'truncated' => false];
        }
        $users = \App\Core\Container::get(UserRepository::class);
        try {
            $rows = match ($source) {
                'effectifs' => $this->effectifs($users, $tenantId),
                'sans_comptes' => $this->sansComptes($users, $tenantId),
                'affectations' => $this->affectations($users, $tenantId),
                'qualifications' => $this->qualifications($users, $tenantId),
                'presences' => $this->presences($users, $tenantId),
                'materiel' => $this->materiel($users, $tenantId),
            };
        } catch (\Throwable $e) {
            error_log('[tables] source ' . $source . ' : ' . $e->getMessage());
            $rows = [];
        }
        $truncated = count($rows) > self::MAX_ROWS;

        return ['rows' => array_slice($rows, 0, self::MAX_ROWS), 'truncated' => $truncated];
    }

    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    /** @return array<int, array<string, mixed>> membres de la communauté par identifiant */
    private function members(UserRepository $users, int $tenantId): array
    {
        $out = [];
        foreach ($users->allForTenant($tenantId) as $u) {
            $out[(int) $u['id']] = $u;
        }

        return $out;
    }

    private static function nameOf(array $u): string
    {
        $n = trim((string) ($u['display_name'] ?? ''));
        if ($n === '') {
            $n = trim(trim((string) ($u['first_name'] ?? '')) . ' ' . trim((string) ($u['last_name'] ?? '')));
        }

        return $n !== '' ? $n : ('Membre #' . (int) ($u['id'] ?? 0));
    }

    private static function day(mixed $v): string
    {
        $s = trim((string) $v);

        return strlen($s) >= 10 && !str_starts_with($s, '0000') ? substr($s, 0, 10) : '';
    }

    private static function yesNo(mixed $v): string
    {
        return !empty($v) ? 'Oui' : 'Non';
    }

    private static function statusLabel(string $s): string
    {
        return match (strtolower(trim($s))) {
            'active' => 'Actif',
            'inactive' => 'Inactif',
            'pending' => 'En attente',
            'pending_verification' => 'À vérifier',
            'suspended' => 'Suspendu',
            '' => '',
            default => $s,
        };
    }

    /** @return list<array<string, string|float>> */
    private function effectifs(UserRepository $users, int $tenantId): array
    {
        $members = $this->members($users, $tenantId);
        $grades = [];
        foreach (\App\Core\Container::get(GradeRepository::class)->listActiveForDoctrinePicker($tenantId) as $g) {
            $grades[(int) $g['id']] = trim((string) ($g['label_long'] ?? '')) ?: trim((string) ($g['label_short'] ?? ''));
        }
        $units = [];
        if ($members !== []) {
            $ids = array_keys($members);
            foreach (array_chunk($ids, 500) as $chunk) {
                $st = $this->pdo()->prepare(
                    'SELECT pp.user_id, un.name FROM personnel_profiles pp
                     INNER JOIN units un ON un.id = pp.primary_unit_id AND un.tenant_id = ?
                     WHERE pp.user_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
                );
                $st->execute(array_merge([$tenantId], $chunk));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $units[(int) $r['user_id']] = (string) $r['name'];
                }
            }
        }
        $out = [];
        foreach ($members as $id => $u) {
            $kind = NoAccountDirectory::classify($u);
            $out[] = [
                'name' => self::nameOf($u),
                'first_name' => trim((string) ($u['first_name'] ?? '')),
                'last_name' => trim((string) ($u['last_name'] ?? '')),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'grade' => $grades[(int) ($u['grade_id'] ?? 0)] ?? '',
                'unit' => $units[$id] ?? '',
                'status' => self::statusLabel((string) ($u['status'] ?? '')),
                'account' => $kind === null ? 'Compte actif' : NoAccountDirectory::STATUSES[$kind],
                'member_number' => trim((string) ($u['tenant_member_number'] ?? '')),
                'steam' => self::yesNo(trim((string) ($u['steam_id'] ?? '')) !== ''),
                'created_at' => self::day($u['created_at'] ?? ''),
                'last_login' => self::day($u['last_login_at'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return list<array<string, string|float>> */
    private function sansComptes(UserRepository $users, int $tenantId): array
    {
        $out = [];
        foreach ((new NoAccountDirectory($users))->list($tenantId) as $r) {
            $out[] = [
                'name' => $r['name'],
                'callsign' => $r['callsign'],
                'status' => $r['status_label'],
                'source' => $r['source_label'],
                'created_at' => self::day($r['created_at']),
                'last_activity' => self::day($r['last_activity']),
            ];
        }

        return $out;
    }

    /** @return list<array<string, string|float>> */
    private function affectations(UserRepository $users, int $tenantId): array
    {
        $members = $this->members($users, $tenantId);
        $st = $this->pdo()->prepare(
            'SELECT pa.user_id, un.name AS unit_name, pa.role_name, pa.is_primary, pa.status, pa.started_at, pa.ended_at
             FROM personnel_assignments pa
             INNER JOIN units un ON un.id = pa.unit_id
             WHERE un.tenant_id = ?
             ORDER BY un.name ASC, pa.id ASC
             LIMIT ' . (self::MAX_ROWS + 1)
        );
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $u = $members[(int) $r['user_id']] ?? null;
            if ($u === null) {
                continue;
            }
            $out[] = [
                'member' => self::nameOf($u),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'unit' => (string) $r['unit_name'],
                'role' => (string) $r['role_name'],
                'primary' => self::yesNo($r['is_primary']),
                'status' => match ((string) $r['status']) { 'active' => 'Active', 'inactive' => 'Terminée', 'pending' => 'En attente', default => (string) $r['status'] },
                'started_at' => self::day($r['started_at']),
                'ended_at' => self::day($r['ended_at']),
            ];
        }

        return $out;
    }

    /** @return list<array<string, string|float>> */
    private function qualifications(UserRepository $users, int $tenantId): array
    {
        $members = $this->members($users, $tenantId);
        $out = [];
        $today = new \DateTimeImmutable('today');
        foreach (array_chunk(array_keys($members), 500) as $chunk) {
            $st = $this->pdo()->prepare(
                'SELECT user_id, qualification_name, level, status, obtained_at, expires_at FROM personnel_qualifications
                 WHERE user_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')
                 ORDER BY qualification_name ASC, id ASC'
            );
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $u = $members[(int) $r['user_id']];
                $exp = self::day($r['expires_at']);
                $left = '';
                if ($exp !== '') {
                    try {
                        $left = (float) $today->diff(new \DateTimeImmutable($exp))->format('%r%a');
                    } catch (\Throwable) {
                        $left = '';
                    }
                }
                $out[] = [
                    'member' => self::nameOf($u),
                    'callsign' => trim((string) ($u['callsign'] ?? '')),
                    'qualification' => (string) $r['qualification_name'],
                    'level' => (string) ($r['level'] ?? ''),
                    'status' => match ((string) $r['status']) { 'valid' => 'Valide', 'expiring' => 'À renouveler', 'expired' => 'Expirée', 'in_progress' => 'En cours', default => (string) $r['status'] },
                    'obtained_at' => self::day($r['obtained_at']),
                    'expires_at' => $exp,
                    'days_left' => $left,
                ];
            }
        }

        return $out;
    }

    /** @return list<array<string, string|float>> */
    private function presences(UserRepository $users, int $tenantId): array
    {
        $members = $this->members($users, $tenantId);
        $st = $this->pdo()->prepare(
            'SELECT r.user_id, r.status, r.checked_in_at, ce.title, ce.event_type, ce.starts_at
             FROM community_event_rsvps r
             INNER JOIN community_events ce ON ce.id = r.event_id
             WHERE ce.tenant_id = ? AND ce.cancelled_at IS NULL AND ce.starts_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
             ORDER BY ce.starts_at DESC, r.id ASC
             LIMIT ' . (self::MAX_ROWS * 2)
        );
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $u = $members[(int) $r['user_id']] ?? null;
            if ($u === null) {
                continue;
            }
            $out[] = [
                'member' => self::nameOf($u),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'event' => (string) $r['title'],
                'event_type' => (string) $r['event_type'],
                'starts_at' => self::day($r['starts_at']),
                'answer' => match ((string) $r['status']) { 'yes' => 'Oui', 'no' => 'Non', 'maybe' => 'Peut-être', default => (string) $r['status'] },
                'checked_in' => self::yesNo($r['checked_in_at'] !== null),
            ];
        }

        return $out;
    }

    /** @return list<array<string, string|float>> */
    private function materiel(UserRepository $users, int $tenantId): array
    {
        $members = $this->members($users, $tenantId);
        $st = $this->pdo()->prepare(
            'SELECT pea.personnel_id, d.name AS item, d.category, pea.serial_number, pea.status, pea.assigned_at, pea.returned_at
             FROM personnel_equipment_assignments pea
             INNER JOIN equipment_item_definitions d ON d.id = pea.definition_id AND d.tenant_id = pea.tenant_id
             WHERE pea.tenant_id = ?
             ORDER BY pea.assigned_at DESC, pea.id DESC
             LIMIT ' . (self::MAX_ROWS + 1)
        );
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $u = $members[(int) $r['personnel_id']] ?? null;
            if ($u === null) {
                continue;
            }
            $out[] = [
                'member' => self::nameOf($u),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'item' => (string) $r['item'],
                'category' => (string) ($r['category'] ?? ''),
                'serial' => (string) ($r['serial_number'] ?? ''),
                'status' => match ((string) $r['status']) { 'issued' => 'Remis', 'returned' => 'Rendu', 'lost' => 'Perdu', default => (string) $r['status'] },
                'assigned_at' => self::day($r['assigned_at']),
                'returned_at' => self::day($r['returned_at']),
            ];
        }

        return $out;
    }
}
