<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Intitulés de la feuille ORBAT imprimable et du PDF, en français, anglais, russe et espagnol.
 * Les noms d’unités, de postes et la mission restent tels que la communauté les a saisis.
 */
final class OrbatSheetLabels
{
    /** @var array<string, array{fr: string, en: string, ru: string, es: string}> */
    private const LABELS = [
        'chart' => [
            'fr' => 'ORGANIZATION CHART (ORBAT)',
            'en' => 'ORGANIZATION CHART (ORBAT)',
            'ru' => 'СХЕМА ОРГАНИЗАЦИИ (ORBAT)',
            'es' => 'ORGANIGRAMA (ORBAT)',
        ],
        'toe' => [
            'fr' => 'TABLEAU D’ORGANISATION ET D’ÉQUIPEMENT',
            'en' => 'TABLE OF ORGANIZATION AND EQUIPMENT',
            'ru' => 'ШТАТНОЕ РАСПИСАНИЕ И ТАБЕЛЬ ОСНАЩЕНИЯ',
            'es' => 'CUADRO DE ORGANIZACIÓN Y EQUIPO',
        ],
        'annex' => ['fr' => 'ANNEXE B', 'en' => 'ANNEX B', 'ru' => 'ПРИЛОЖЕНИЕ Б', 'es' => 'ANEXO B'],
        'annex_short' => ['fr' => 'ANNEXE B-1', 'en' => 'ANNEX B-1', 'ru' => 'ПРИЛОЖЕНИЕ Б-1', 'es' => 'ANEXO B-1'],
        'figure' => ['fr' => 'FIGURE B-1', 'en' => 'FIGURE B-1', 'ru' => 'РИСУНОК Б-1', 'es' => 'FIGURA B-1'],
        'landscape' => ['fr' => 'paysage', 'en' => 'landscape', 'ru' => 'альбомная', 'es' => 'horizontal'],
        'page_break' => [
            'fr' => 'saut de page auto',
            'en' => 'automatic page break',
            'ru' => 'автоперенос страниц',
            'es' => 'salto de página automático',
        ],
        'unit' => ['fr' => 'UNITÉ', 'en' => 'UNIT', 'ru' => 'ПОДРАЗДЕЛЕНИЕ', 'es' => 'UNIDAD'],
        'country' => ['fr' => 'PAYS', 'en' => 'COUNTRY', 'ru' => 'СТРАНА', 'es' => 'PAÍS'],
        'theater' => ['fr' => 'THÉÂTRE', 'en' => 'THEATER', 'ru' => 'ТВД', 'es' => 'TEATRO'],
        'effect_date' => ['fr' => 'DATE D’EFFET', 'en' => 'EFFECTIVE DATE', 'ru' => 'ДАТА ВВОДА', 'es' => 'FECHA DE EFECTO'],
        'reference' => ['fr' => 'RÉFÉRENCE', 'en' => 'REFERENCE', 'ru' => 'НОМЕР', 'es' => 'REFERENCIA'],
        'version' => ['fr' => 'VERSION', 'en' => 'VERSION', 'ru' => 'ВЕРСИЯ', 'es' => 'VERSIÓN'],
        'units' => ['fr' => 'UNITÉS', 'en' => 'UNITS', 'ru' => 'ПОДРАЗДЕЛЕНИЙ', 'es' => 'UNIDADES'],
        'units_lower' => ['fr' => 'unités', 'en' => 'units', 'ru' => 'подразделений', 'es' => 'unidades'],
        'pers' => ['fr' => 'pers.', 'en' => 'pers.', 'ru' => 'чел.', 'es' => 'pers.'],
        'composition' => [
            'fr' => 'COMPOSITION GÉNÉRALE',
            'en' => 'GENERAL COMPOSITION',
            'ru' => 'ОБЩИЙ СОСТАВ',
            'es' => 'COMPOSICIÓN GENERAL',
        ],
        'strength_total' => [
            'fr' => 'Effectif total (théorique)',
            'en' => 'Total strength (authorized)',
            'ru' => 'Численность по штату',
            'es' => 'Efectivo total (teórico)',
        ],
        'strength_present' => [
            'fr' => 'Effectif présent (T/O)',
            'en' => 'Present strength (T/O)',
            'ru' => 'Численность в наличии',
            'es' => 'Efectivo presente (T/O)',
        ],
        'availability_rate' => [
            'fr' => 'Taux de disponibilité',
            'en' => 'Availability rate',
            'ru' => 'Укомплектованность',
            'es' => 'Tasa de disponibilidad',
        ],
        'units_shown' => [
            'fr' => 'Unités affichées',
            'en' => 'Units shown',
            'ru' => 'Показано подразделений',
            'es' => 'Unidades mostradas',
        ],
        'no_subunits' => [
            'fr' => 'Aucune sous-unité à afficher sur cette feuille.',
            'en' => 'No sub-unit to show on this sheet.',
            'ru' => 'На этом листе нет подчинённых подразделений.',
            'es' => 'No hay subunidades que mostrar en esta hoja.',
        ],
        'strength_summary' => [
            'fr' => 'SYNTHÈSE EFFECTIFS — TOUTES UNITÉS',
            'en' => 'STRENGTH SUMMARY — ALL UNITS',
            'ru' => 'СВОДКА ЧИСЛЕННОСТИ — ВСЕ ПОДРАЗДЕЛЕНИЯ',
            'es' => 'RESUMEN DE EFECTIVOS — TODAS LAS UNIDADES',
        ],
        'element' => ['fr' => 'ÉLÉMENT', 'en' => 'ELEMENT', 'ru' => 'ЭЛЕМЕНТ', 'es' => 'ELEMENTO'],
        'element_all' => [
            'fr' => 'ÉLÉMENT (toutes unités)',
            'en' => 'ELEMENT (all units)',
            'ru' => 'ЭЛЕМЕНТ (все подразделения)',
            'es' => 'ELEMENTO (todas las unidades)',
        ],
        'authorized' => ['fr' => 'THÉORIQUE', 'en' => 'AUTHORIZED', 'ru' => 'ПО ШТАТУ', 'es' => 'TEÓRICO'],
        'present' => ['fr' => 'PRÉSENT', 'en' => 'PRESENT', 'ru' => 'В НАЛИЧИИ', 'es' => 'PRESENTE'],
        'availability' => ['fr' => 'DISPONIBILITÉ', 'en' => 'AVAILABILITY', 'ru' => 'УКОМПЛЕКТ.', 'es' => 'DISPONIBILIDAD'],
        'availability_short' => ['fr' => 'DISP.', 'en' => 'AVAIL.', 'ru' => 'УКОМП.', 'es' => 'DISP.'],
        'total' => ['fr' => 'TOTAL', 'en' => 'TOTAL', 'ru' => 'ИТОГО', 'es' => 'TOTAL'],
        'mission' => ['fr' => 'MISSION', 'en' => 'MISSION', 'ru' => 'ЗАДАЧА', 'es' => 'MISIÓN'],
        'mission_default' => [
            'fr' => 'Conduire les opérations conformément aux directives du commandement.',
            'en' => 'Conduct operations in accordance with command directives.',
            'ru' => 'Вести действия в соответствии с указаниями командования.',
            'es' => 'Conducir las operaciones conforme a las directivas del mando.',
        ],
        'notes' => ['fr' => 'NOTES', 'en' => 'NOTES', 'ru' => 'ПРИМЕЧАНИЯ', 'es' => 'NOTAS'],
        'note_1' => [
            'fr' => 'Effectifs selon tableau d’organisation en vigueur.',
            'en' => 'Strength per the current table of organization.',
            'ru' => 'Численность согласно действующему штатному расписанию.',
            'es' => 'Efectivos según el cuadro de organización vigente.',
        ],
        'note_2' => [
            'fr' => 'Les indisponibilités sont suivies par le bureau RH / S-1.',
            'en' => 'Unavailability is tracked by the HR office / S-1.',
            'ru' => 'Отсутствующих учитывает кадровая служба / S-1.',
            'es' => 'Las indisponibilidades las sigue la oficina de personal / S-1.',
        ],
        'note_3' => [
            'fr' => 'Les rattachements temporaires sont indiqués sur ordre.',
            'en' => 'Temporary attachments are shown by order.',
            'ru' => 'Временные прикомандирования указываются приказом.',
            'es' => 'Las agregaciones temporales se indican por orden.',
        ],
        'note_4' => [
            'fr' => 'Format d’impression adaptatif (%s), non limité au A4.',
            'en' => 'Adaptive print size (%s), not limited to A4.',
            'ru' => 'Адаптивный формат печати (%s), не только A4.',
            'es' => 'Formato de impresión adaptable (%s), no limitado a A4.',
        ],
        'legend' => [
            'fr' => 'LÉGENDE — SYMBOLES OPÉRATIONNELS',
            'en' => 'LEGEND — OPERATIONAL SYMBOLS',
            'ru' => 'УСЛОВНЫЕ ОБОЗНАЧЕНИЯ',
            'es' => 'LEYENDA — SÍMBOLOS OPERATIVOS',
        ],
        'legend_combat' => ['fr' => 'Combat / manœuvre', 'en' => 'Combat / maneuver', 'ru' => 'Боевые / манёвр', 'es' => 'Combate / maniobra'],
        'legend_hq' => ['fr' => 'Commandement', 'en' => 'Command', 'ru' => 'Управление', 'es' => 'Mando'],
        'legend_support' => ['fr' => 'Soutien', 'en' => 'Support', 'ru' => 'Обеспечение', 'es' => 'Apoyo'],
        'legend_air' => ['fr' => 'Aviation', 'en' => 'Aviation', 'ru' => 'Авиация', 'es' => 'Aviación'],
        'print' => ['fr' => 'Imprimer / PDF navigateur', 'en' => 'Print / browser PDF', 'ru' => 'Печать / PDF браузера', 'es' => 'Imprimir / PDF del navegador'],
        'download_pdf' => ['fr' => 'Télécharger PDF', 'en' => 'Download PDF', 'ru' => 'Скачать PDF', 'es' => 'Descargar PDF'],
        'back' => ['fr' => 'Retour ORBAT', 'en' => 'Back to ORBAT', 'ru' => 'Назад к ORBAT', 'es' => 'Volver al ORBAT'],
        'size' => ['fr' => 'Format', 'en' => 'Size', 'ru' => 'Формат', 'es' => 'Formato'],
        'sheet_language' => ['fr' => 'Langue de la feuille', 'en' => 'Sheet language', 'ru' => 'Язык листа', 'es' => 'Idioma de la hoja'],
        'unit_default' => ['fr' => 'Unité', 'en' => 'Unit', 'ru' => 'Подразделение', 'es' => 'Unidad'],
        'organisation_default' => ['fr' => 'Organisation', 'en' => 'Organization', 'ru' => 'Организация', 'es' => 'Organización'],
    ];

    /** @var array<string, array{fr: string, en: string, ru: string, es: string}> */
    private const TYPES = [
        'hq' => ['fr' => 'HQ', 'en' => 'HQ', 'ru' => 'ШТАБ', 'es' => 'CG'],
        'support' => ['fr' => 'SOUTIEN', 'en' => 'SUPPORT', 'ru' => 'ОБЕСП.', 'es' => 'APOYO'],
        'air' => ['fr' => 'AIR', 'en' => 'AIR', 'ru' => 'АВИА', 'es' => 'AÉREO'],
        'fire' => ['fr' => 'FEU', 'en' => 'FIRES', 'ru' => 'ОГОНЬ', 'es' => 'FUEGO'],
        'engineer' => ['fr' => 'GÉNIE', 'en' => 'ENGINEER', 'ru' => 'ИНЖ.', 'es' => 'ZAPADORES'],
        'log' => ['fr' => 'LOG', 'en' => 'LOG', 'ru' => 'ТЫЛ', 'es' => 'LOG'],
        'isr' => ['fr' => 'ISR', 'en' => 'ISR', 'ru' => 'РАЗВ.', 'es' => 'ISR'],
        'combat' => ['fr' => 'COMBAT', 'en' => 'COMBAT', 'ru' => 'БОЕВОЕ', 'es' => 'COMBATE'],
    ];

    public static function language(mixed $raw): string
    {
        return CommunityLanguages::normalize($raw) ?? CommunityLanguages::DEFAULT;
    }

    public static function get(string $key, string $lang): string
    {
        $row = self::LABELS[$key] ?? null;
        if ($row === null) {
            return $key;
        }

        return $row[self::language($lang)] ?? $row['fr'];
    }

    public static function typeLabel(string $type, string $lang): string
    {
        $row = self::TYPES[$type] ?? self::TYPES['combat'];

        return $row[self::language($lang)] ?? $row['fr'];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    /** @return list<string> */
    public static function typeKeys(): array
    {
        return array_keys(self::TYPES);
    }
}
