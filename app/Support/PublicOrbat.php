<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * ORBAT de la vitrine publique (/c/{slug}, section « Organisation ») : langue des libellés,
 * insigne de repli et fiche publique des chefs d’unité (grade court + portrait opérateur).
 */
final class PublicOrbat
{
    /** Libellés du bloc, par langue de l’ORBAT. Le français reste la source (traduit à l’affichage pour un visiteur anglais). */
    private const LABELS = [
        'fr' => [
            'strength' => 'Effectif', 'founded' => 'Création', 'slots' => 'Places', 'open' => 'Ouvert', 'full' => 'Complet',
            'slot' => 'place', 'slots_n' => 'places', 'member' => 'membre', 'members' => 'membres', 'leader' => 'Chef d’unité',
            'vacant' => 'Responsable non publié', 'record' => 'Voir la fiche →', 'under' => 'Rattachée à', 'unit' => 'Unité',
            'subunits' => 'Sous-unités de', 'chain' => 'Chaîne de commandement publique',
        ],
        'en' => [
            'strength' => 'Strength', 'founded' => 'Founded', 'slots' => 'Slots', 'open' => 'Open', 'full' => 'Full',
            'slot' => 'slot', 'slots_n' => 'slots', 'member' => 'member', 'members' => 'members', 'leader' => 'Unit leader',
            'vacant' => 'Leader not published', 'record' => 'View record →', 'under' => 'Part of', 'unit' => 'Unit',
            'subunits' => 'Sub-units of', 'chain' => 'Public chain of command',
        ],
        'ru' => [
            'strength' => 'Численность', 'founded' => 'Создано', 'slots' => 'Места', 'open' => 'Открыто', 'full' => 'Укомплектовано',
            'slot' => 'место', 'slots_n' => 'мест', 'member' => 'боец', 'members' => 'бойцов', 'leader' => 'Командир',
            'vacant' => 'Командир не указан', 'record' => 'Открыть карточку →', 'under' => 'В составе', 'unit' => 'Подразделение',
            'subunits' => 'Подразделения', 'chain' => 'Публичная структура командования',
        ],
        'es' => [
            'strength' => 'Efectivo', 'founded' => 'Creación', 'slots' => 'Plazas', 'open' => 'Abierto', 'full' => 'Completo',
            'slot' => 'plaza', 'slots_n' => 'plazas', 'member' => 'miembro', 'members' => 'miembros', 'leader' => 'Jefe de unidad',
            'vacant' => 'Responsable no publicado', 'record' => 'Ver la ficha →', 'under' => 'Integrada en', 'unit' => 'Unidad',
            'subunits' => 'Subunidades de', 'chain' => 'Cadena de mando pública',
        ],
    ];

    /**
     * Langue de l’ORBAT : réglage de la communauté (orbat_language, sinon default_locale), français par défaut.
     *
     * @param array<string, mixed> $community settings.community
     */
    public static function language(array $community): string
    {
        $lang = '';
        $helper = 'App\\Support\\CommunityLanguages';
        if (class_exists($helper) && method_exists($helper, 'orbatLanguage')) {
            try {
                $lang = (string) $helper::orbatLanguage($community);
            } catch (Throwable) {
                $lang = '';
            }
        }
        if ($lang === '') {
            $lang = trim((string) ($community['orbat_language'] ?? ''));
        }
        if ($lang === '') {
            $lang = trim((string) ($community['default_locale'] ?? ''));
        }
        $lang = strtolower(substr($lang, 0, 2));

        return isset(self::LABELS[$lang]) ? $lang : 'fr';
    }

    /** @return array<string, string> */
    public static function labels(string $lang): array
    {
        return self::LABELS[$lang] ?? self::LABELS['fr'];
    }

    /**
     * Insigne de repli : deux à trois caractères tirés du code, sinon des initiales du nom.
     */
    public static function insignia(string $code, string $name): string
    {
        $code = trim($code);
        if ($code !== '') {
            $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $code) ?? '';
            if ($compact !== '' && mb_strlen($compact) <= 4) {
                return mb_strtoupper($compact);
            }
        }
        $words = preg_split('/[\s\-_.\/]+/u', trim($code !== '' ? $code : $name)) ?: [];
        $out = '';
        foreach ($words as $w) {
            if ($w === '') {
                continue;
            }
            $out .= mb_substr($w, 0, 1);
            if (mb_strlen($out) >= 3) {
                break;
            }
        }
        if (mb_strlen($out) < 2) {
            $out = mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', $name) ?? '', 0, 2);
        }

        return mb_strtoupper($out !== '' ? $out : '?');
    }

    /**
     * Grade court et portrait opérateur des chefs d’unité (une requête, erreurs ignorées : la carte s’affiche sans).
     *
     * @param list<int> $userIds
     * @return array<int, array{grade: string, portrait: ?string}>
     */
    public static function commanders(int $tenantId, array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($tenantId < 1 || $ids === []) {
            return [];
        }
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['grade' => '', 'portrait' => null];
        }
        try {
            foreach (OperatorPortraits::forUsers($tenantId, $ids) as $uid => $url) {
                $out[(int) $uid]['portrait'] = $url;
            }
        } catch (Throwable) {
        }
        try {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = Database::getPdo()->prepare(
                'SELECT g.*, u.id AS orb_uid FROM users u INNER JOIN grades g ON g.id = u.grade_id WHERE u.tenant_id = ? AND u.id IN (' . $ph . ')'
            );
            $stmt->execute(array_merge([$tenantId], $ids));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $uid = (int) ($row['orb_uid'] ?? 0);
                if (!isset($out[$uid])) {
                    continue;
                }
                foreach (['label_short', 'short_name', 'abbreviation', 'code', 'label_long', 'name'] as $col) {
                    $v = trim((string) ($row[$col] ?? ''));
                    if ($v !== '') {
                        $out[$uid]['grade'] = mb_substr($v, 0, 24);
                        break;
                    }
                }
            }
        } catch (Throwable) {
        }

        return $out;
    }
}
