<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

/**
 * Ce que le graphe de liaison doit savoir d'UN lecteur : sa communauté (jamais lue dans la requête), ses droits par
 * section SSE, son habilitation, et ce que la passerelle des images de membres lui permet. Toute décision de droit passe ici ;
 * le graphe n'en invente aucune. L'implémentation réelle est SseLinkSessionViewer ; les tests en donnent une en mémoire.
 */
interface SseLinkViewer
{
    public function tenantId(): int;

    public function userId(): ?int;

    /** Session invitée (par code d'accès) : lit, n'écrit jamais. */
    public function isGuest(): bool;

    /** Lire une section de droits du portail (fichiers, papiers, dossiers, photos, terrain...). */
    public function canRead(string $section): bool;

    /** Écrire dans une section. */
    public function canWrite(string $section): bool;

    /** Habilitation « Confidentiel » : nature des identités de couverture, d'emprunt ou falsifiées, vérité de jeu. */
    public function canSeeTruth(): bool;

    /** Habilitation suffisante pour un niveau de classification (interne, encadrement, confidentiel, tres_restreint). */
    public function clearanceAllows(string $classification): bool;

    /** Le lecteur voit-il ce qui touche l'identité de ce membre (CONFIDENTIEL ÉLEVÉ : titulaire, hiérarchie, habilitation, dérogation) ? */
    public function memberAllowed(int $ownerUserId): bool;

    /** Dossiers qu'un code d'accès limite, ou null si aucune limite. @return list<int>|null */
    public function caseScope(): ?array;

    /** Droit d'ouvrir un dossier d'intérêt (diffusion restreinte par dossier). */
    public function canOpenInterest(int $interestCaseId): bool;
}
