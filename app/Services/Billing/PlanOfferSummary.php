<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Textes de comparaison des formules (page publique /platform/upgrade et aperçu de l'administration), générés depuis
 * les valeurs réelles des formules et le catalogue : plus aucun chiffre écrit à la main.
 *
 * Première formule : ses modules et ses plafonds. Suivantes : « Tout ce qu’offre <précédente>, plus : » puis les
 * modules ajoutés et les plafonds qui changent. Les modules qu'aucun écran ne contrôle (catalogue, read = false)
 * sont annoncés une seule fois comme inclus partout, puisqu'ils le sont en pratique.
 */
final class PlanOfferSummary
{
    /** Accroche par formule (formules du site) ; une formule ajoutée n'en a pas. */
    private const TAGLINES = ['free' => 'Essentiel', 'standard' => 'Équipe', 'pro' => 'Complet', 'pro_plus' => 'Étendu'];

    private const RECOMMENDED = 'pro';

    private const PERIODS = ['monthly' => 'par mois', 'weekly' => 'par semaine', 'daily' => 'par jour'];

    /**
     * @param list<array<string, mixed>> $plans lignes subscription_plans, dans l'ordre d'affichage
     * @param array<string, array<string, mixed>> $overrides slug => features à utiliser à la place des valeurs enregistrées (aperçu)
     *
     * @return list<array{
     *   slug: string, name: string, tagline: string, recommended: bool, inherits: ?string,
     *   items: list<array{label: string, value: ?string, suffix: ?string}>
     * }>
     */
    public static function cards(array $plans, array $overrides = []): array
    {
        $defs = SubscriptionPlanFeaturesCatalog::definitions();
        $cards = [];
        $prev = null;
        $prevName = null;
        foreach (array_values($plans) as $i => $plan) {
            $slug = (string) ($plan['slug'] ?? '');
            if (isset($overrides[$slug])) {
                $plan['features_json'] = json_encode($overrides[$slug], JSON_UNESCAPED_UNICODE);
            }
            $values = SubscriptionPlanFeaturesCatalog::planValues($plan)['values'];
            $items = [];
            if ($i === 0) {
                foreach (SubscriptionPlanFeaturesCatalog::unreadKeys() as $key) {
                    if ($key !== 'community_create') {
                        $items[] = self::item($defs[$key]['label']);
                    }
                }
            }
            // Modules d'abord, plafonds ensuite.
            foreach ($defs as $key => $def) {
                if ($def['read'] && $def['type'] === 'bool' && $values[$key] && ($prev === null || !$prev[$key])) {
                    $items[] = self::item($def['label']);
                }
            }
            foreach ($defs as $key => $def) {
                if (!$def['read'] || $def['type'] === 'bool') {
                    continue;
                }
                $v = $values[$key];
                if ($prev !== null && $prev[$key] === $v) {
                    continue;
                }
                // Plafond sans objet si le module qu'il limite n'est pas inclus (ex. parcours sans Formations).
                if ($key === 'max_training_courses' && !$values['training']) {
                    continue;
                }
                $items[] = self::item($def['label'], SubscriptionPlanFeaturesCatalog::formatValue($key, $v));
            }
            foreach (self::periodicQuotas($plan, $values) as $q) {
                $items[] = $q;
            }
            $name = trim((string) ($plan['name'] ?? '')) ?: $slug;
            $cards[] = [
                'slug' => $slug,
                'name' => $name,
                'tagline' => self::TAGLINES[$slug] ?? '',
                'recommended' => $slug === self::RECOMMENDED,
                'inherits' => $prevName,
                'items' => $items,
            ];
            $prev = $values;
            $prevName = $name;
        }

        return $cards;
    }

    /**
     * Quotas périodiques (limits_json.quotas) d'un module non inclus : « Événements et calendrier : 3 par mois ».
     *
     * @param array<string, mixed> $plan
     * @param array<string, bool|int> $values
     *
     * @return list<array{label: string, value: ?string, suffix: ?string}>
     */
    private static function periodicQuotas(array $plan, array $values): array
    {
        $raw = $plan['limits_json'] ?? null;
        $limits = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
        $quotas = is_array($limits) && is_array($limits['quotas'] ?? null) ? $limits['quotas'] : [];
        $defs = SubscriptionPlanFeaturesCatalog::definitions();
        $out = [];
        foreach ($quotas as $feature => $q) {
            $feature = (string) $feature;
            $limit = is_array($q) ? (int) ($q['limit'] ?? 0) : 0;
            if ($limit <= 0 || !empty($values[$feature]) || !isset($defs[$feature])) {
                continue;
            }
            $period = self::PERIODS[(string) ($q['reset_period'] ?? 'monthly')] ?? self::PERIODS['monthly'];
            $out[] = ['label' => $defs[$feature]['label'], 'value' => (string) $limit, 'suffix' => $period];
        }

        return $out;
    }

    /** @return array{label: string, value: ?string, suffix: ?string} */
    private static function item(string $label, ?string $value = null): array
    {
        return ['label' => $label, 'value' => $value, 'suffix' => null];
    }

    /**
     * Formules montrées au public : gratuite puis formules payantes en vente, dans l'ordre d'affichage.
     *
     * @param list<array<string, mixed>> $allPlans
     * @param list<string> $onSaleSlugs
     *
     * @return list<array<string, mixed>>
     */
    public static function publicPlans(array $allPlans, array $onSaleSlugs): array
    {
        return array_values(array_filter($allPlans, static function (array $p) use ($onSaleSlugs): bool {
            $slug = strtolower(trim((string) ($p['slug'] ?? '')));

            return $slug === 'free' || in_array($slug, $onSaleSlugs, true);
        }));
    }
}
