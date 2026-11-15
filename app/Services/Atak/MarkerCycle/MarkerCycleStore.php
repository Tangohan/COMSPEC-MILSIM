<?php

declare(strict_types=1);

namespace App\Services\Atak\MarkerCycle;

/**
 * Accès aux données du cycle des marqueurs : réglages, éléments affichés sur la carte (marqueurs et tracés), missions
 * archivées. Le dépôt réel (PdoMarkerCycleStore) parle à MySQL ; les tests utilisent une version en mémoire.
 *
 * Un « élément » est un tableau : kind (marker|shape), id, at (dernière modification, UTC « Y-m-d H:i:s »), payload
 * (contenu complet : colonnes de la ligne, JSON décodés). Tout est borné par communauté : aucun appel ne lit ni
 * n'écrit hors du tenant_id reçu.
 *
 * @phpstan-type Item array{kind: string, id: int, at: string, payload: array<string, mixed>}
 */
interface MarkerCycleStore
{
    /** Les trois tables existent (sinon : aucun filtre, aucun archivage, comportement d'avant). */
    public function ready(): bool;

    /** @return array<string, mixed>|null ligne de réglages brute, null si la communauté n'a rien enregistré */
    public function settingsRow(int $tenantId): ?array;

    /** @param array<string, mixed> $settings réglages déjà normalisés */
    public function saveSettings(int $tenantId, array $settings, ?int $userId): void;

    /** Fuseau par défaut de la communauté (tenants.default_timezone), null si inconnu. */
    public function tenantTimezone(int $tenantId): ?string;

    /** @return list<array{tenant: int, map: int}> couples (communauté, carte) qui ont au moins un élément affiché */
    public function liveScopes(): array;

    /**
     * Éléments plus anciens que $beforeUtc, ni épinglés ni déjà masqués, du plus ancien au plus récent.
     *
     * @return list<array{kind: string, id: int, at: string, payload: array<string, mixed>}>
     */
    public function staleItems(int $tenantId, int $mapId, string $beforeUtc, int $limit): array;

    /**
     * Écrit l'instantané (en une transaction) : crée la mission archivée si elle manque, ajoute les éléments (sans
     * doublon : un même élément n'est jamais écrit deux fois), recalcule les compteurs. Lève une exception si
     * l'écriture échoue : l'appelant ne retire alors rien de la carte.
     *
     * @param array{key: string, label: string, start_utc: string, end_utc: string} $window
     * @param list<array{kind: string, id: int, at: string, payload: array<string, mixed>}> $items
     * @return int identifiant de la mission archivée
     */
    public function writeArchive(int $tenantId, int $mapId, array $window, string $source, ?int $userId, array $items): int;

    /**
     * Retire de la carte des éléments déjà archivés. Ne touche jamais un élément épinglé.
     *
     * @param list<array{kind: string, id: int}> $refs
     * @return int nombre d'éléments retirés
     */
    public function deleteLive(int $tenantId, int $mapId, array $refs): int;

    /** Remet un élément archivé sur la carte (nouvelle ligne, cycle courant). @return int|null identifiant créé */
    public function insertLive(int $tenantId, int $mapId, string $kind, array $payload): ?int;

    /** Épingle (ou désépingle) un élément affiché. @return bool false si l'élément n'existe pas dans cette communauté */
    public function setPinned(int $tenantId, string $kind, int $id, bool $pinned): bool;

    /**
     * @param array{map?: int, from?: string, to?: string, q?: string} $filter from/to : « Y-m-d H:i:s » UTC (chevauchement)
     * @return list<array<string, mixed>> missions archivées, la plus récente d'abord
     */
    public function listArchives(int $tenantId, array $filter, int $limit, int $offset): array;

    /** @return array<string, mixed>|null */
    public function getArchive(int $tenantId, int $archiveId): ?array;

    /** @return list<array{kind: string, id: int, at: string, payload: array<string, mixed>}> */
    public function archiveItems(int $tenantId, int $archiveId, int $limit, int $offset): array;

    /** Supprime une mission archivée et son contenu. @return bool */
    public function deleteArchive(int $tenantId, int $archiveId): bool;

    /** Supprime les missions archivées terminées avant $beforeUtc. @return int nombre de missions supprimées */
    public function purgeArchivesEndedBefore(int $tenantId, string $beforeUtc, int $limit): int;

    /** @return list<int> communautés qui ont au moins une mission archivée (pour la rétention) */
    public function tenantsWithArchives(): array;

    /** Verrou contre l'exécution concurrente. @return bool true si obtenu */
    public function lock(string $name): bool;

    public function unlock(string $name): void;
}
