<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Refus d'une action sur un dossier d'intérêt. Le type dit quoi faire à l'écran : corriger des champs (validation), recharger
 * (conflit), se contenter du message (droit, règle, introuvable).
 */
final class SseInterestCaseException extends \RuntimeException
{
    public const VALIDATION = 'validation';
    public const CONFLICT = 'conflict';
    public const FORBIDDEN = 'forbidden';
    public const NOT_FOUND = 'not_found';
    public const RULE = 'rule';
    public const DEGRADED = 'degraded';

    /** @param array<string, string> $fields message par champ */
    public function __construct(public readonly string $kind, string $message, public readonly array $fields = [])
    {
        parent::__construct($message);
    }

    /** @param array<string, string> $fields */
    public static function validation(array $fields, ?string $message = null): self
    {
        $count = count($fields);

        return new self(self::VALIDATION, $message ?? ($count > 1 ? $count . ' champs sont à corriger.' : 'Un champ est à corriger : ' . (string) reset($fields)), $fields);
    }

    public static function conflict(string $message): self
    {
        return new self(self::CONFLICT, $message);
    }

    public static function forbidden(string $message): self
    {
        return new self(self::FORBIDDEN, $message);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND, 'Ce dossier d’intérêt n’existe pas ou vous n’y avez pas accès.');
    }

    public static function rule(string $message): self
    {
        return new self(self::RULE, $message);
    }

    public static function degraded(string $what): self
    {
        return new self(self::DEGRADED, $what . ' sera disponible après la mise à jour de la base : demandez à un administrateur de lancer l’étape « Dossiers d’intérêt SSE ».');
    }
}
