<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

/**
 * Pré-kit du configurateur (table game_item_prekits) : liste d'objets [[classe, nombre]…] insérée en un clic dans
 * l'uniforme, le gilet ou le sac (ex. « Équipement obligatoire » : garrot, pansements, radio, carte).
 *
 * Pur : contrôle du contenu (bornes, classes Arma, doublons fusionnés) et plan d'insertion dans un contenant selon les
 * règles du jeu : objets acceptés par le contenant (allowedSlots), charge maximale (maximumLoad), pas d'arme ni de
 * sac dans un pré-kit. Mêmes règles que le configurateur côté navigateur (prekitPlan dans equipment-configurator.js).
 * Rien n'est deviné : une masse ou une capacité inconnue laisse passer l'objet et le plan le signale (unchecked).
 */
final class PrekitDocument
{
    public const MAX_LINES = 40;

    public const MAX_COUNT = 99;

    public const NAME_MAX = 90;

    public const DESCRIPTION_MAX = 300;

    /** Familles refusées dans un pré-kit : armes (montées à l'établi) et sacs (Arma ne range pas un sac porté). */
    private const REFUSED_KINDS = ['primary', 'secondary', 'handgun', 'backpack', 'uniform', 'vest'];

    private const CONTAINER_WORDS = ['uniform' => 'l’uniforme', 'vest' => 'le gilet', 'backpack' => 'le sac'];

    /**
     * @param mixed $items texte JSON ou liste [[classe, nombre]…]
     * @return array{ok: bool, error?: string, items: list<array{0: string, 1: int}>}
     */
    public static function normalize(mixed $items): array
    {
        if (is_string($items)) {
            $items = strlen($items) > 20000 ? null : json_decode($items, true);
        }
        if (!is_array($items) || !array_is_list($items)) {
            return ['ok' => false, 'error' => 'Contenu du pré-kit illisible.', 'items' => []];
        }
        $out = [];
        foreach ($items as $line) {
            if (!is_array($line) || !is_string($line[0] ?? null)) {
                return ['ok' => false, 'error' => 'Contenu du pré-kit illisible.', 'items' => []];
            }
            $class = trim($line[0]);
            if (preg_match(ItemCatalogImport::CLASS_RE, $class) !== 1) {
                return ['ok' => false, 'error' => 'Classe Arma invalide dans le pré-kit : ' . mb_substr($class, 0, 60) . '.', 'items' => []];
            }
            $count = is_int($line[1] ?? null) || (is_string($line[1] ?? null) && ctype_digit($line[1])) ? (int) $line[1] : 0;
            if ($count < 1 || $count > self::MAX_COUNT) {
                return ['ok' => false, 'error' => 'Nombre invalide pour « ' . $class . ' » (1 à ' . self::MAX_COUNT . ').', 'items' => []];
            }
            $key = strtolower($class);
            if (isset($out[$key])) {
                $out[$key][1] = min(self::MAX_COUNT, $out[$key][1] + $count);
            } else {
                $out[$key] = [$class, $count];
            }
        }
        if ($out === []) {
            return ['ok' => false, 'error' => 'Le pré-kit est vide : ajoutez au moins un objet.', 'items' => []];
        }
        if (count($out) > self::MAX_LINES) {
            return ['ok' => false, 'error' => 'Trop d’objets différents dans le pré-kit (' . self::MAX_LINES . ' au plus).', 'items' => []];
        }

        return ['ok' => true, 'items' => array_values($out)];
    }

    /**
     * Objets du pré-kit refusés par principe (armes, sacs, tenues) ou absents du catalogue.
     *
     * @param list<array{0: string, 1: int}> $items
     * @param array<string, array<string, mixed>> $catalog clé = classe en minuscules (ConfiguratorCatalog::merge)
     * @return list<string> messages
     */
    public static function problems(array $items, array $catalog): array
    {
        $out = [];
        foreach ($items as [$class]) {
            $spec = $catalog[strtolower($class)] ?? null;
            if ($spec === null) {
                $out[] = '« ' . $class . ' » est absent du catalogue de la communauté.';
            } elseif (in_array((string) ($spec['kind'] ?? ''), self::REFUSED_KINDS, true)) {
                $out[] = '« ' . self::name($class, $catalog) . ' » (' . ConfiguratorKinds::label((string) $spec['kind']) . ') ne va pas dans un pré-kit : seulement des objets à ranger.';
            }
        }

        return $out;
    }

    /**
     * Masse totale du pré-kit (unités Arma), null si une masse manque.
     *
     * @param list<array{0: string, 1: int}> $items
     * @param array<string, array<string, mixed>> $catalog
     */
    public static function mass(array $items, array $catalog): ?float
    {
        $total = 0.0;
        foreach ($items as [$class, $count]) {
            $m = $catalog[strtolower($class)]['mass'] ?? null;
            if ($m === null) {
                return null;
            }
            $total += (float) $m * $count;
        }

        return round($total, 3);
    }

    /**
     * Plan d'insertion dans un contenant : ce qui tient (unité par unité, dans l'ordre du pré-kit) et ce qui reste.
     *
     * @param list<array{0: string, 1: int}> $items
     * @param string $container uniform|vest|backpack
     * @param string $containerClass classe du contenant posé ('' = pas de contenant)
     * @param float|null $currentLoad charge actuelle du contenant (null = inconnue)
     * @param array<string, array<string, mixed>> $catalog
     * @return array{ok: bool, error?: string, added: list<array{0: string, 1: int}>, skipped: list<array{class: string, count: int, reason: string}>, load: float|null, max: int|null, complete: bool, unchecked: bool}
     */
    public static function plan(array $items, string $container, string $containerClass, ?float $currentLoad, array $catalog): array
    {
        $empty = ['added' => [], 'skipped' => [], 'load' => $currentLoad, 'max' => null, 'complete' => false, 'unchecked' => false];
        if (!isset(ConfiguratorKinds::SLOT_CODES[$container])) {
            return ['ok' => false, 'error' => 'Choisissez l’uniforme, le gilet ou le sac.'] + $empty;
        }
        if ($containerClass === '') {
            return ['ok' => false, 'error' => 'Posez d’abord ' . self::article($container) . ' pour y insérer le pré-kit.'] + $empty;
        }
        $cSpec = $catalog[strtolower($containerClass)] ?? null;
        $max = isset($cSpec['load']) && $cSpec['load'] !== null ? (int) $cSpec['load'] : null;
        $code = ConfiguratorKinds::SLOT_CODES[$container];
        $load = $currentLoad;
        $unchecked = $max === null || $load === null;
        $added = [];
        $skipped = [];
        foreach ($items as [$class, $count]) {
            $spec = $catalog[strtolower($class)] ?? null;
            $label = self::name($class, $catalog);
            if ($spec === null) {
                $skipped[] = ['class' => $class, 'count' => $count, 'reason' => '« ' . $class . ' » est absent du catalogue.'];
                continue;
            }
            if (in_array((string) ($spec['kind'] ?? ''), self::REFUSED_KINDS, true)) {
                $skipped[] = ['class' => $class, 'count' => $count, 'reason' => '« ' . $label . ' » ne se range pas comme un objet.'];
                continue;
            }
            $slots = is_array($spec['slots'] ?? null) ? array_map('intval', $spec['slots']) : [];
            if ($slots !== [] && !in_array($code, $slots, true)) {
                $skipped[] = ['class' => $class, 'count' => $count, 'reason' => '« ' . $label . ' » ne se range pas dans ' . self::CONTAINER_WORDS[$container] . ' (le jeu le refuse).'];
                continue;
            }
            $m = isset($spec['mass']) && $spec['mass'] !== null ? (float) $spec['mass'] : null;
            $fit = $count;
            if ($m === null) {
                $unchecked = true;
                $load = null;
            } elseif ($max !== null && $load !== null) {
                $fit = $m <= 0 ? $count : (int) min($count, floor(($max - $load + 0.0001) / $m));
                $fit = max(0, $fit);
                $load += $m * $fit;
            } elseif ($load !== null) {
                $load += $m * $count;
            }
            if ($fit > 0) {
                $added[] = [(string) $spec['class'] !== '' ? (string) $spec['class'] : $class, $fit];
            }
            if ($fit < $count) {
                $skipped[] = ['class' => $class, 'count' => $count - $fit, 'reason' => '« ' . $label . ' » × ' . ($count - $fit) . ' : plus de place dans ' . self::CONTAINER_WORDS[$container] . '.'];
            }
        }

        return [
            'ok' => true,
            'added' => $added,
            'skipped' => $skipped,
            'load' => $load === null ? null : round($load, 3),
            'max' => $max,
            'complete' => $skipped === [],
            'unchecked' => $unchecked,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $catalog
     */
    private static function name(string $class, array $catalog): string
    {
        $n = (string) ($catalog[strtolower($class)]['name'] ?? '');

        return $n !== '' ? $n : $class;
    }

    private static function article(string $container): string
    {
        return ['uniform' => 'un uniforme', 'vest' => 'un gilet', 'backpack' => 'un sac'][$container] ?? 'un contenant';
    }
}
