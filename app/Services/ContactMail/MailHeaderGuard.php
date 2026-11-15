<?php

declare(strict_types=1);

namespace App\Services\ContactMail;

/**
 * Valeurs qui finissent dans un en-tête d'e-mail (destinataire, objet, nom d'affichage) : refus net d'un retour à la ligne
 * ou d'un caractère de contrôle, qui permettrait d'ajouter un en-tête (Bcc, Reply-To…) à un message.
 */
final class MailHeaderGuard
{
    public const MAX_SUBJECT = 200;

    public const MAX_RECIPIENTS = 10;

    /** @throws \InvalidArgumentException */
    public static function address(string $value): string
    {
        if (self::hasControl($value)) {
            throw new \InvalidArgumentException('Adresse refusée : retour à la ligne ou caractère interdit.');
        }
        $v = trim($value);
        if (!ContactMailConfig::isValidAddress($v)) {
            throw new \InvalidArgumentException('Adresse e-mail invalide.');
        }

        return strtolower($v);
    }

    /**
     * Liste d'adresses séparées par des virgules, points-virgules ou retours à la ligne saisis dans un champ.
     *
     * @return list<string>
     * @throws \InvalidArgumentException
     */
    public static function addressList(string $value): array
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
            throw new \InvalidArgumentException('Adresse refusée : caractère interdit.');
        }
        $out = [];
        foreach (preg_split('/[,;\r\n]+/', $value) ?: [] as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }
            $out[] = self::address($piece);
        }
        $out = array_values(array_unique($out));
        if (count($out) > self::MAX_RECIPIENTS) {
            throw new \InvalidArgumentException('Trop de destinataires.');
        }

        return $out;
    }

    /** @throws \InvalidArgumentException */
    public static function subject(string $value): string
    {
        if (self::hasControl($value)) {
            throw new \InvalidArgumentException('Objet refusé : retour à la ligne ou caractère interdit.');
        }
        $v = trim($value);
        if ($v === '') {
            throw new \InvalidArgumentException('Objet manquant.');
        }
        if (mb_strlen($v) > self::MAX_SUBJECT) {
            throw new \InvalidArgumentException('Objet trop long.');
        }

        return $v;
    }

    /** Nom d'affichage : sans retour à la ligne, guillemets ni chevrons. */
    public static function displayName(string $value): string
    {
        $v = trim(preg_replace('/[\x00-\x1F\x7F<>"\\\\]+/u', ' ', $value) ?? '');

        return mb_substr($v, 0, 80);
    }

    public static function hasControl(string $v): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $v) === 1;
    }

    /** Objet sans préfixes de réponse empilés : « Re: RE: Fwd: Bonjour » donne « Bonjour ». */
    public static function baseSubject(string $subject): string
    {
        $s = trim($subject);
        for ($i = 0; $i < 10; $i++) {
            $next = preg_replace('/^\s*(re|ré|aw|sv|tr|fw|fwd|rv)\s*(\[\d+\])?\s*:\s*/iu', '', $s);
            if ($next === null || $next === $s) {
                break;
            }
            $s = $next;
        }

        return trim($s);
    }

    /** « Re: Bonjour » sans empilement. */
    public static function replySubject(string $subject): string
    {
        $base = self::baseSubject($subject);

        return 'Re: ' . ($base !== '' ? $base : '(sans objet)');
    }

    /** Forme de comparaison d'un objet pour regrouper les fils. */
    public static function normalizeSubject(string $subject): string
    {
        return mb_strtolower(mb_substr(self::baseSubject($subject), 0, 250));
    }
}
