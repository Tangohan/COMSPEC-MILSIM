<?php

declare(strict_types=1);

namespace App\Services\MemberIntegration;

/**
 * Ce que l'application d'un paquet d'intégration demande au reste de la plateforme. L'implémentation réelle
 * (LiveIntegrationPorts) passe par les services existants : aucune écriture directe qui contournerait leurs contrôles.
 */
interface IntegrationPorts
{
    /** Compte membre de la candidature, créé ou rattaché au besoin (hors transaction du paquet). 0 : impossible. */
    public function ensureAccount(int $tenantId, int $enlistmentId, int $actorId): int;

    /**
     * Grade, rôles, unité, emploi, identité, Steam : le parcours d'intégration existant.
     *
     * @param array<string, mixed> $options
     * @return array{ok: bool, message: ?string, user_id?: int}
     */
    public function applyBase(int $tenantId, int $enlistmentId, int $actorId, array $options): array;

    /** Raison pour laquelle la qualification ne peut pas être délivrée à l'intégration, null si elle le peut. */
    public function qualificationBlocker(int $tenantId, int $definitionId): ?string;

    /**
     * Délivre ou met en formation. Lève une exception si les règles de la qualification s'y opposent.
     *
     * @return 'applied'|'unchanged'
     */
    public function awardQualification(int $tenantId, int $userId, int $definitionId, string $status, int $validMonths, int $actorId): string;

    /**
     * Inscrit à la formation, sans e-mail propre.
     *
     * @return 'applied'|'unchanged'
     */
    public function enrollCourse(int $tenantId, int $userId, int $courseId, int $actorId): string;

    /**
     * Libellés lisibles de ce que reçoit le membre : grade, unité, emploi, niveaux d'accès.
     *
     * @param array<string, mixed> $options
     * @return array{grade: string, unit: string, job: string, roles: list<string>}
     */
    public function baseLabels(int $tenantId, array $options): array;

    /** Historique du membre et journal du dossier de candidature. */
    public function journal(int $tenantId, int $userId, int $enlistmentId, int $actorId, string $title, string $detail): void;

    /**
     * Récapitulatif de bienvenue au nouveau membre.
     *
     * @param array<string, mixed> $recap
     */
    public function sendWelcome(int $tenantId, int $userId, array $recap): bool;
}
