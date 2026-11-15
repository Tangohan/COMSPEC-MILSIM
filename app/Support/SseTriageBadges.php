<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseTriageInbox;

/**
 * Pastilles de la barre latérale SSE et bouton « À trier » : valeurs et balisage, sans logique de file (SseTriageInbox).
 * Rien ne repose sur la couleur seule : chaque pastille porte son nombre et un nom accessible (« 5 à trier »).
 * Les textes sont du français : HtmlTranslator les traduit à l'affichage, et l'API JSON choisit sa langue elle-même.
 */
final class SseTriageBadges
{
    public const CAP = 99;

    /** « 7 », « 99+ », ou chaîne vide quand il n'y a rien. */
    public static function label(int $count): string
    {
        return $count < 1 ? '' : ($count > self::CAP ? self::CAP . '+' : (string) $count);
    }

    public static function tone(string $tone): string
    {
        return in_array($tone, [SseTriageInbox::TONE_WARN, SseTriageInbox::TONE_BAD], true) ? $tone : SseTriageInbox::TONE_NEUTRAL;
    }

    public static function aria(int $count, string $lang = 'fr'): string
    {
        return self::label($count) . ($lang === 'en' ? ' to sort' : ' à trier');
    }

    /** Pastille d'une entrée de menu ; masquée (mais présente pour l'actualisation) quand la file est vide. */
    public static function badge(string $navId, int $count, string $tone): string
    {
        $tone = self::tone($tone);

        return '<span class="sb-badge sb-badge--' . $tone . '" data-sb-nav="' . self::e($navId) . '" data-count="' . max(0, $count) . '"'
            . ($count < 1 ? ' hidden' : ' role="img" aria-label="' . self::e(self::aria($count)) . '"')
            . '>' . self::e(self::label($count)) . '</span>';
    }

    /** Pastille discrète d'une section : somme de ses entrées, visible seulement quand la section est repliée. */
    public static function pill(string $sectionId, int $count, string $tone): string
    {
        $tone = self::tone($tone);

        return '<span class="sb-pill sb-pill--' . $tone . '" data-sb-section="' . self::e($sectionId) . '" data-count="' . max(0, $count) . '"'
            . ($count < 1 ? ' hidden' : ' role="img" aria-label="' . self::e(self::aria($count)) . '"')
            . '>' . self::e(self::label($count)) . '</span>';
    }

    public static function worst(string $a, string $b): string
    {
        $rank = [SseTriageInbox::TONE_NEUTRAL => 0, SseTriageInbox::TONE_WARN => 1, SseTriageInbox::TONE_BAD => 2];

        return ($rank[self::tone($b)] ?? 0) > ($rank[self::tone($a)] ?? 0) ? self::tone($b) : self::tone($a);
    }

    /**
     * Données de la barre latérale : total distinct, et par entrée de menu le nombre et le ton.
     *
     * @param list<array<string, mixed>> $sections navigation visible (SsePortalNav)
     * @return array{total: int, entries: array<string, array{count: int, tone: string}>, sections: array<string, array{count: int, tone: string}>, enabled: bool}
     */
    public static function forNav(array $sections, ?SseTriageInbox $inbox = null, ?int $tenantId = null, ?int $userId = null): array
    {
        $empty = ['total' => 0, 'entries' => [], 'sections' => [], 'enabled' => false];
        try {
            $tenantId ??= (new SseAccessCodeService())->tenantId() ?: (int) Session::get('tenant_id');
            $userId ??= (int) Session::get('user_id');
            $navIds = [];
            foreach ($sections as $s) {
                foreach ($s['items'] as $i) {
                    $navIds[] = (string) $i['id'];
                }
            }
            $snap = ($inbox ?? SseTriageInbox::forSession())->snapshot((int) $tenantId, (int) $userId, $navIds);
        } catch (\Throwable) {
            return $empty;
        }

        return self::fromSnapshot($sections, $snap);
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @param array{total: int, sources: array<string, array<string, mixed>>, items: list<array<string, mixed>>} $snap
     * @return array{total: int, entries: array<string, array{count: int, tone: string}>, sections: array<string, array{count: int, tone: string}>, enabled: bool}
     */
    public static function fromSnapshot(array $sections, array $snap): array
    {
        $entries = [];
        foreach ($snap['sources'] as $src) {
            $nav = (string) $src['nav'];
            $entries[$nav] = ['count' => (int) $src['count'], 'tone' => (string) $src['tone']];
        }
        $perSection = [];
        foreach ($sections as $s) {
            $sum = 0;
            $tone = SseTriageInbox::TONE_NEUTRAL;
            foreach ($s['items'] as $i) {
                if (isset($entries[(string) $i['id']])) {
                    $sum += $entries[(string) $i['id']]['count'];
                    $tone = self::worst($tone, $entries[(string) $i['id']]['tone']);
                }
            }
            $perSection[(string) $s['id']] = ['count' => $sum, 'tone' => $tone];
        }

        return ['total' => (int) $snap['total'], 'entries' => $entries, 'sections' => $perSection, 'enabled' => true];
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
