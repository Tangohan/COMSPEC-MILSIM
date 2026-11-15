<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Sse\Links\SseLinkViewer;

/**
 * Lecteur de test du graphe de liaison : les droits sont des tableaux, comme le ferait une session (sections lues et écrites,
 * habilitation, identités de membres autorisées, portée des dossiers). Aucune simulation de la logique du graphe : seules les
 * réponses de la politique d'accès sont fournies.
 */
final class ArraySseLinkViewer implements SseLinkViewer
{
    /** Niveaux de classification, du plus bas au plus haut. */
    private const RANK = ['interne' => 0, 'encadrement' => 1, 'confidentiel' => 2, 'tres_restreint' => 3];

    /**
     * @param list<string> $read sections lisibles
     * @param list<string> $write sections où l'on écrit
     * @param list<int> $members comptes de membres dont l'identité est lisible
     * @param list<int>|null $caseScope dossiers autorisés (null : aucune limite)
     * @param list<int> $interest dossiers d'intérêt ouverts
     */
    public function __construct(
        private int $tenant = 1,
        private ?int $user = 7,
        private array $read = ['fichiers', 'papiers', 'dossiers', 'photos', 'terrain', 'outils', 'analyse'],
        private array $write = ['fichiers', 'papiers', 'dossiers'],
        private bool $truth = false,
        private string $clearance = 'encadrement',
        private array $members = [],
        private ?array $caseScope = null,
        private array $interest = [],
        private bool $guest = false,
        private array $manage = [],
    ) {
    }

    public static function full(int $tenant = 1): self
    {
        return new self($tenant, 7, ['fichiers', 'papiers', 'dossiers', 'photos', 'terrain', 'outils', 'analyse', 'extractions'], ['fichiers', 'papiers', 'dossiers', 'photos', 'terrain'], true, 'tres_restreint', [], null, [1, 2, 3, 4, 5], false);
    }

    public function with(array $changes): self
    {
        $o = clone $this;
        foreach ($changes as $k => $v) {
            $o->$k = $v;
        }

        return $o;
    }

    public function tenantId(): int
    {
        return $this->tenant;
    }

    public function userId(): ?int
    {
        return $this->guest ? null : $this->user;
    }

    public function isGuest(): bool
    {
        return $this->guest || $this->user === null;
    }

    public function canRead(string $section): bool
    {
        return in_array($section, $this->read, true);
    }

    public function canWrite(string $section): bool
    {
        return !$this->isGuest() && in_array($section, $this->write, true);
    }

    /** Droit « gérer » : les sections listées dans $manage (propriété posée par with()). */
    public function canManage(string $section): bool
    {
        return !$this->isGuest() && in_array($section, $this->manage, true);
    }

    public function canSeeTruth(): bool
    {
        return $this->truth;
    }

    public function clearanceAllows(string $classification): bool
    {
        return (self::RANK[$classification] ?? 0) <= (self::RANK[$this->clearance] ?? 0);
    }

    public function memberAllowed(int $ownerUserId): bool
    {
        return $ownerUserId === $this->user || in_array($ownerUserId, $this->members, true);
    }

    public function caseScope(): ?array
    {
        return $this->caseScope;
    }

    public function canOpenInterest(int $interestCaseId): bool
    {
        return in_array($interestCaseId, $this->interest, true);
    }
}
