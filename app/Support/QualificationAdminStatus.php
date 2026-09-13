<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Statuts administratifs d'une attribution de qualification (stockés).
 * Les états temporels (expirante, expirée, grâce) sont calculés séparément.
 */
final class QualificationAdminStatus
{
    public const CANDIDATE = 'candidate';
    public const IN_TRAINING = 'in_training';
    public const IN_EVALUATION = 'in_evaluation';
    public const OBTAINED = 'obtained';
    public const FAILED = 'failed';
    public const SUSPENDED = 'suspended';
    public const REVOKED = 'revoked';

    /** @var list<string> */
    public const ALL = [
        self::CANDIDATE,
        self::IN_TRAINING,
        self::IN_EVALUATION,
        self::OBTAINED,
        self::FAILED,
        self::SUSPENDED,
        self::REVOKED,
    ];

    public static function normalize(?string $raw): string
    {
        $t = strtolower(trim((string) $raw));
        $aliases = [
            'candidat' => self::CANDIDATE,
            'en_formation' => self::IN_TRAINING,
            'en formation' => self::IN_TRAINING,
            'en_evaluation' => self::IN_EVALUATION,
            'en_évaluation' => self::IN_EVALUATION,
            'en évaluation' => self::IN_EVALUATION,
            'obtenue' => self::OBTAINED,
            'valid' => self::OBTAINED,
            'expiring' => self::OBTAINED,
            'expired' => self::OBTAINED,
            'echouee' => self::FAILED,
            'échouée' => self::FAILED,
            'suspendue' => self::SUSPENDED,
            'retiree' => self::REVOKED,
            'retirée' => self::REVOKED,
            'in_progress' => self::IN_TRAINING,
        ];

        if (isset($aliases[$t])) {
            return $aliases[$t];
        }

        return in_array($t, self::ALL, true) ? $t : self::CANDIDATE;
    }

    public static function label(string $status): string
    {
        return match (self::normalize($status)) {
            self::CANDIDATE => 'Candidat',
            self::IN_TRAINING => 'En formation',
            self::IN_EVALUATION => 'En évaluation',
            self::OBTAINED => 'Obtenue',
            self::FAILED => 'Échouée',
            self::SUSPENDED => 'Suspendue',
            self::REVOKED => 'Retirée',
            default => 'Candidat',
        };
    }

    /** @return list<string> */
    public static function allowedTransitions(string $from): array
    {
        return match (self::normalize($from)) {
            self::CANDIDATE => [self::IN_TRAINING, self::IN_EVALUATION, self::OBTAINED, self::FAILED, self::REVOKED],
            self::IN_TRAINING => [self::IN_EVALUATION, self::OBTAINED, self::FAILED, self::SUSPENDED, self::REVOKED],
            self::IN_EVALUATION => [self::OBTAINED, self::FAILED, self::IN_TRAINING, self::SUSPENDED, self::REVOKED],
            self::OBTAINED => [self::SUSPENDED, self::REVOKED],
            self::FAILED => [self::CANDIDATE, self::IN_TRAINING],
            self::SUSPENDED => [self::OBTAINED, self::REVOKED],
            self::REVOKED => [],
            default => [],
        };
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array(self::normalize($to), self::allowedTransitions($from), true);
    }
}
