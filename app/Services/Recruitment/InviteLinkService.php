<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Repositories\InviteLinkRepository;
use App\Services\Platform\FeatureGateService;

/**
 * Règles des liens d'invitation /i/<code> : état (actif, expiré, épuisé, révoqué), échéances proposées, codes
 * non devinables, adresse personnalisée et limites de la formule. Les limites ne s'appliquent qu'à la création et à
 * la réactivation : un lien déjà actif n'est jamais coupé par un changement de formule.
 */
final class InviteLinkService
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_EXHAUSTED = 'exhausted';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_DELETED = 'deleted';

    /** Fonctionnalités de formule (SubscriptionPlanFeaturesCatalog). */
    public const FEATURE_MAX_ACTIVE = 'max_active_invite_links';
    public const FEATURE_CUSTOM_SLUG = 'invite_link_custom_slug';

    /** Échéances proposées (secondes ; null = jamais, 'date' = date choisie). */
    public const EXPIRY_PRESETS = [
        'never' => null,
        '30m' => 1800,
        '1h' => 3600,
        '6h' => 21600,
        '12h' => 43200,
        '1d' => 86400,
        '7d' => 604800,
        'date' => 'date',
    ];

    /** Alphabet sans caractères ambigus (0/o, 1/l/i). 31^10 combinaisons : impossible à deviner sous limitation de débit. */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const GENERATED_LENGTH = 10;

    public function __construct(
        private ?FeatureGateService $featureGate = null,
    ) {
    }

    /** @param array<string, mixed> $link */
    public static function status(array $link, ?int $now = null): string
    {
        $now ??= time();
        if (!empty($link['revoked_at'])) {
            return self::STATUS_REVOKED;
        }
        $expires = trim((string) ($link['expires_at'] ?? ''));
        if ($expires !== '') {
            $ts = strtotime($expires);
            if ($ts !== false && $ts <= $now) {
                return self::STATUS_EXPIRED;
            }
        }
        $max = $link['max_uses'] ?? null;
        if ($max !== null && (int) $max > 0 && (int) ($link['uses_count'] ?? 0) >= (int) $max) {
            return self::STATUS_EXHAUSTED;
        }

        return self::STATUS_ACTIVE;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_EXPIRED => 'Expiré',
            self::STATUS_EXHAUSTED => 'Épuisé',
            self::STATUS_REVOKED => 'Révoqué',
            default => 'Actif',
        };
    }

    /** Message affiché au visiteur d'un lien inutilisable. */
    public static function unusableMessage(string $status): string
    {
        return match ($status) {
            self::STATUS_EXPIRED => 'Ce lien d’invitation a expiré. Demandez un nouveau lien à la personne qui vous l’a envoyé.',
            self::STATUS_EXHAUSTED => 'Ce lien d’invitation a déjà été utilisé le nombre de fois prévu. Demandez un nouveau lien à la personne qui vous l’a envoyé.',
            self::STATUS_REVOKED => 'Ce lien d’invitation a été désactivé par la communauté. Demandez un nouveau lien à la personne qui vous l’a envoyé.',
            self::STATUS_DELETED => 'Ce lien d’invitation a été supprimé par la communauté. Demandez un nouveau lien à la personne qui vous l’a envoyé.',
            default => 'Ce lien d’invitation n’existe pas. Vérifiez l’adresse ou demandez un nouveau lien.',
        };
    }

    public static function presetLabel(string $preset): string
    {
        return match ($preset) {
            '30m' => '30 minutes',
            '1h' => '1 heure',
            '6h' => '6 heures',
            '12h' => '12 heures',
            '1d' => '1 jour',
            '7d' => '7 jours',
            'date' => 'Jusqu’à une date choisie',
            default => 'Jamais',
        };
    }

    /**
     * Échéance à enregistrer. null = jamais ; false = saisie invalide (date absente ou passée, choix inconnu).
     */
    public static function expiresAtFromInput(string $preset, string $date = '', ?int $now = null): string|false|null
    {
        $now ??= time();
        if (!array_key_exists($preset, self::EXPIRY_PRESETS)) {
            return false;
        }
        $value = self::EXPIRY_PRESETS[$preset];
        if ($value === null) {
            return null;
        }
        if ($value === 'date') {
            $ts = strtotime(trim($date));
            if ($ts === false || $ts <= $now) {
                return false;
            }

            return date('Y-m-d H:i:s', $ts);
        }

        return date('Y-m-d H:i:s', $now + (int) $value);
    }

    /** Code public tiré au hasard, jamais déjà pris. */
    public static function generateCode(callable $isTaken): string
    {
        $max = strlen(self::ALPHABET) - 1;
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = '';
            for ($i = 0; $i < self::GENERATED_LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, $max)];
            }
            if (!$isTaken($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Aucun code libre trouvé.');
    }

    /** Adresse personnalisée : 3 à 40 caractères, lettres minuscules, chiffres et tirets (pas en début ni en fin). */
    public static function normalizeCustomSlug(string $raw): ?string
    {
        $slug = strtolower(trim($raw));
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{1,38})[a-z0-9]$/', $slug) !== 1 || str_contains($slug, '--')) {
            return null;
        }

        return $slug;
    }

    /** Forme acceptée d'un code reçu dans une adresse (/i/<code>) ou collé par un visiteur. */
    public static function isWellFormedCode(string $code): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]{1,62}[a-z0-9]$/', strtolower(trim($code))) === 1;
    }

    /** Extrait le code d'une adresse collée (https://…/i/abc) ; null si ce n'est pas un lien d'invitation. */
    public static function codeFromPastedLink(string $raw): ?string
    {
        if (preg_match('~(?:^|/)i/([A-Za-z0-9-]{3,64})/?(?:[?#].*)?$~', trim($raw), $m) !== 1) {
            return null;
        }
        $code = strtolower($m[1]);

        return self::isWellFormedCode($code) ? $code : null;
    }

    public static function publicUrl(string $code): string
    {
        return url('i/' . rawurlencode($code));
    }

    /** Plafond de liens actifs de la formule (0 = illimité). */
    public function maxActiveLinks(int $tenantId): int
    {
        return $this->featureGate?->intWithCatalogDefault($tenantId, self::FEATURE_MAX_ACTIVE) ?? 0;
    }

    public function customSlugAllowed(int $tenantId): bool
    {
        return $this->featureGate?->allowsWithCatalogDefault($tenantId, self::FEATURE_CUSTOM_SLUG) ?? true;
    }

    /** Un lien de plus peut-il être actif ? (création ou réactivation) */
    public function canActivateAnother(int $tenantId, InviteLinkRepository $links): bool
    {
        return self::withinQuota($this->maxActiveLinks($tenantId), $links->countActiveForTenant($tenantId));
    }

    /** Plafond 0 = illimité ; sinon un lien de plus tant que les liens actifs restent sous le plafond. */
    public static function withinQuota(int $max, int $active): bool
    {
        return $max <= 0 || $active < $max;
    }
}
