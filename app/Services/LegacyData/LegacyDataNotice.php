<?php

declare(strict_types=1);

namespace App\Services\LegacyData;

use App\Services\BackOffice\Menu\PendingTaskDetail;
use App\Support\EnlistmentRetroAutoClose;
use App\Support\ShortCache;
use Throwable;

/**
 * Avis « Données anciennes à vérifier » pour les responsables d'une communauté : en une phrase claire et avec les nombres,
 * ce que les écrans récents affichent autrement parce que la donnée est ancienne ou incomplète. Rien n'est jamais
 * supprimé ni modifié par cet avis : il informe et envoie vers l'écran qui permet de compléter.
 *
 * Le calcul (LegacyDataCounter, quelques COUNT) est mis en cache 10 minutes par communauté (ShortCache) et ne bloque
 * jamais : en cas d'erreur, aucun avis. Les textes sont composés ici en français et en anglais ; la page « Tâches en
 * attente » les affiche via PendingTaskDetail (voir LegacyDataBadge).
 */
final class LegacyDataNotice
{
    public const CACHE_SECONDS = 600;

    /** Page qui permet de corriger : Qualité des données, section « Données anciennes à vérifier ». */
    public const PATH = 'back-office/organisation/qualite-donnees';

    public const ANCHOR = 'dq-donnees-anciennes';

    /** Une catégorie « sans unité / grade / portrait » n'entre dans l'avis qu'à partir de ce nombre de membres (un cas isolé est normal). */
    public const MIN_PROFILE_GAP = 3;

    /**
     * @return array<string, int> voir LegacyDataCounter::KEYS ; tous à 0 en cas d'erreur
     */
    public static function counts(int $tenantId, ?LegacyDataCounter $counter = null): array
    {
        if ($tenantId < 1) {
            return array_fill_keys(LegacyDataCounter::KEYS, 0);
        }

        return ShortCache::remember(
            'legacy_data_notice:' . $tenantId . ':g' . ShortCache::gen('legacy_data:' . $tenantId),
            self::CACHE_SECONDS,
            static function () use ($tenantId, $counter): array {
                try {
                    return ($counter ?? new LegacyDataCounter())->count($tenantId);
                } catch (Throwable $e) {
                    error_log('[legacy_data] avis de la communauté ' . $tenantId . ' : ' . $e->getMessage());

                    return array_fill_keys(LegacyDataCounter::KEYS, 0);
                }
            }
        );
    }

    /**
     * Phrases de l'avis, une par point à vérifier, avec les nombres.
     *
     * @param array<string, int> $counts
     * @return list<array{key: string, count: int, text: string}>
     */
    public static function lines(array $counts, string $lang = 'fr'): array
    {
        $en = $lang === 'en';
        $lines = [];
        $noName = (int) ($counts['no_civil_name'] ?? 0);
        if ($noName > 0) {
            $lines[] = ['key' => 'no_civil_name', 'count' => $noName, 'text' => LegacyIdentityNotice::message($noName, $lang)];
        }
        $stale = (int) ($counts['stale_dossiers'] ?? 0);
        if ($stale > 0) {
            $days = EnlistmentRetroAutoClose::AUTO_CLOSE_MAX_LATE_DAYS;
            $lines[] = ['key' => 'stale_dossiers', 'count' => $stale, 'text' => $en
                ? ($stale === 1
                    ? '1 old recruitment file has no 30-day review: it was not closed automatically because it is more than ' . $days . ' days overdue.'
                    : $stale . ' old recruitment files have no 30-day review: they were not closed automatically because they are more than ' . $days . ' days overdue.')
                : ($stale === 1
                    ? '1 dossier de recrutement ancien est resté sans bilan : il n’a pas été clôturé automatiquement car son retard dépasse ' . $days . ' jours.'
                    : $stale . ' dossiers de recrutement anciens sont restés sans bilan : ils n’ont pas été clôturés automatiquement car leur retard dépasse ' . $days . ' jours.')];
        }
        $gaps = [];
        foreach (['no_unit' => ['sans unité', 'without a unit'], 'no_grade' => ['sans grade', 'without a rank'], 'no_portrait' => ['sans portrait', 'without a portrait']] as $key => $label) {
            $n = (int) ($counts[$key] ?? 0);
            if ($n >= self::MIN_PROFILE_GAP) {
                $gaps[] = $n . ' ' . ($en ? $label[1] : $label[0]);
            }
        }
        if ($gaps !== []) {
            $lines[] = ['key' => 'profile_gaps', 'count' => count($gaps), 'text' => ($en ? 'Members: ' : 'Membres : ') . implode($en ? ', ' : ', ', $gaps) . '.'];
        }

        return $lines;
    }

    /**
     * Explication de la tâche du tableau de bord ; null quand il n'y a rien à signaler.
     *
     * @param array<string, int> $counts
     */
    public static function compose(array $counts, string $lang = 'fr'): ?PendingTaskDetail
    {
        $lines = self::lines($counts, $lang);
        if ($lines === []) {
            return null;
        }
        $en = $lang === 'en';
        $texts = array_map(static fn (array $l): string => $l['text'], $lines);

        return new PendingTaskDetail(
            $en ? 'Old data to check' : 'Données anciennes à vérifier',
            implode(' ', $texts),
            $en
                ? 'Nothing is deleted or overwritten: this data stays as it is. Some screens simply show it differently (a name under the callsign, a file left as it was) until it is completed.'
                : 'Rien n’est supprimé ni écrasé : ces données restent telles quelles. Certains écrans les affichent simplement autrement (un nom sous l’indicatif, un dossier laissé tel quel) tant qu’elles ne sont pas complétées.',
            $en
                ? 'Open Data quality: the « Old data to check » section shows where to complete each point (member records, HR requests, recruitment reviews) and can take over old character names after a preview.'
                : 'Ouvrez Qualité des données : la section « Données anciennes à vérifier » indique où compléter chaque point (fiches, demandes RH, bilans de recrutement) et permet de reprendre les anciens noms de personnage, après une simulation.',
            $en ? 'Check old data' : 'Vérifier les données anciennes',
            self::PATH . '#' . self::ANCHOR,
            $en ? 'Community administrators.' : 'Les administrateurs de la communauté.',
            $en
                ? 'Over the versions, screens changed how they show people and files. Data entered long ago can be incomplete (no first or last name, no unit, no portrait). Athena never deletes or overwrites it: this notice tells you what is incomplete so you can complete it.'
                : 'Au fil des versions, les écrans ont changé de façon d’afficher les personnes et les dossiers. Les données saisies il y a longtemps peuvent être incomplètes (ni prénom ni nom, pas d’unité, pas de portrait). Athena ne les supprime ni ne les écrase jamais : cet avis vous dit ce qui est incomplet pour que vous le complétiez.',
            true,
            $en ? 'en' : 'fr'
        );
    }

    /** Rend l'avis périmé tout de suite (après une reprise d'anciens noms, par exemple) au lieu d'attendre ses 10 minutes. */
    public static function forget(int $tenantId): void
    {
        ShortCache::bump('legacy_data:' . $tenantId);
    }
}
