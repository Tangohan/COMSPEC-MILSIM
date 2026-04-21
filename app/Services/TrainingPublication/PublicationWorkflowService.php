<?php

declare(strict_types=1);

namespace App\Services\TrainingPublication;

class PublicationWorkflowService
{
    /** @var array<string, list<string>> */
    private array $transitions = [
        'draft' => ['review'],
        'review' => ['validated', 'draft'],
        'validated' => ['published', 'draft'],
        'published' => ['archived', 'draft'],
        'archived' => [],
    ];

    public function transition(string $from, string $to): void
    {
        $allowed = $this->transitions[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException(sprintf('Transition invalide: %s -> %s', $from, $to), 422);
        }
    }

    public function defaultValidationChain(): array
    {
        return [
            ['role' => 'redacteur', 'status' => 'approved', 'required' => true, 'signed_at' => gmdate('c')],
            ['role' => 'relecteur_metier', 'status' => 'pending', 'required' => true],
            ['role' => 'validateur_hierarchique', 'status' => 'pending', 'required' => true],
            ['role' => 'valideur_conformite_courrier', 'status' => 'pending', 'required' => true],
            ['role' => 'approbation_finale', 'status' => 'pending', 'required' => true],
        ];
    }

    public function applyDecision(array $chain, string $actorRole, string $decision, ?string $comment): array
    {
        if ($decision === 'rejected' && trim((string) $comment) === '') {
            throw new \RuntimeException('Commentaire obligatoire en cas de refus.', 422);
        }

        $updated = false;
        foreach ($chain as &$step) {
            if ((string) ($step['role'] ?? '') !== $actorRole) {
                continue;
            }
            if (($step['status'] ?? 'pending') === 'approved') {
                break;
            }
            $step['status'] = $decision;
            $step['comment'] = $comment;
            $step['signed_at'] = gmdate('c');
            $updated = true;
            break;
        }

        if (!$updated) {
            throw new \RuntimeException('Étape de validation introuvable ou déjà validée.', 422);
        }

        return $chain;
    }

    public function isFullyApproved(array $chain): bool
    {
        foreach ($chain as $step) {
            if (($step['required'] ?? false) && ($step['status'] ?? '') !== 'approved') {
                return false;
            }
        }

        return true;
    }
}
