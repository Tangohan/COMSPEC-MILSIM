<?php

declare(strict_types=1);

namespace App\Support\QualificationCatalog;

/**
 * Rendu visuel d’une qualification : domaine (catégorie du catalogue, sinon deviné dans le nom), icône SVG en ligne
 * (couleur du texte) et nature (brevet, qualification, cursus ou sélection, licence).
 */
final class QualificationVisual
{
    /** Mots repérés dans le nom, l’intitulé officiel, le code ou la catégorie du référentiel => domaine. */
    private const WORDS = [
        'airborne' => '/para(chut|trooper)|airborne|jump|saut|halo|haho|freefall|chute libre|mff|static line/i',
        'aviation' => '/pilot|aviat|vol\b|flight|helicop|h[ée]lico|aircrew|wings|rotary|fixed.wing|drone|uas/i',
        'marksmanship' => '/sniper|tireur|marksman|shoot|tir\b|rifle|fusil|sharpshoot/i',
        'medical' => '/m[ée]dic|sant[ée]|sauvet|combat lifesaver|tccc|corpsman|infirm|first aid|secour/i',
        'cbrn_eod' => '/g[ée]nie|engineer|sapper|sapeur|eod|ied|d[ée]minage|demolition|nrbc|cbrn/i',
        'communications' => '/transmission|radio|signal|commun|sic\b|cis\b|rto/i',
        'language' => '/langu|linguist|interpr[eè]te|anglais|english|stanag 6001/i',
        'air_support' => '/jtac|tacp|fac\b|cas\b|close air|appui a[ée]rien|jfo/i',
        'fires' => '/mortier|mortar|artill|fires|appui.feux|fso/i',
        'sof' => '/forces sp[ée]ciales|special forces|sof\b|ranger|commando|seal|sas\b|ksk|delta|green beret|special tactics/i',
        'selection' => '/s[ée]lection|cursus|pipeline|q.course|course de qualification/i',
        'maritime' => '/plong|div(e|ing)|nageur|combat swimmer|maritime|amphib|boat/i',
        'intelligence' => '/renseign|intellig|ciblage|target|isr\b/i',
        'c2_staff' => '/[ée]tat.major|staff|command|chef de|leader/i',
        'environment' => '/montagn|mountain|arctic|grand froid|jungle|d[ée]sert|winter/i',
    ];

    public static function domain(string $text, string $category = ''): string
    {
        if ($category !== '' && isset(QualificationCatalog::CATEGORIES[$category])) {
            return $category === 'personnel_recovery' || $category === 'influence' ? 'sof' : $category;
        }
        foreach (self::WORDS as $key => $re) {
            if (preg_match($re, $text) === 1) {
                return $key;
            }
        }

        return '';
    }

    /** Nature : brevet, cursus (sélection), licence ou qualification par défaut. */
    public static function kind(string $text): string
    {
        return match (true) {
            preg_match('/brevet|badge|wings|insignia|tab\b/i', $text) === 1 => 'brevet',
            preg_match('/s[ée]lection|cursus|pipeline|course\b|stage/i', $text) === 1 => 'cursus',
            preg_match('/licen[cs]e|permis|certificat|certificate/i', $text) === 1 => 'licence',
            default => 'qualification',
        };
    }

    /** Icône SVG décorative (le nom de la qualification est toujours écrit à côté). */
    public static function icon(string $domain, string $class = 'qsg-ic'): string
    {
        $body = match ($domain) {
            'airborne' => '<path d="M4 11a8 8 0 0 1 16 0z"/><path d="M4 11l8 9 8-9M9 11l3 9 3-9"/>',
            'aviation' => '<path d="M2 14l20-6-3 4-7 2-4 6-1-5-5-1z"/>',
            'marksmanship' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 1v5M12 18v5M1 12h5M18 12h5"/>',
            'medical' => '<path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/>',
            'cbrn_eod' => '<path d="M12 3v4M5 6l3 3M19 6l-3 3"/><circle cx="12" cy="15" r="6"/><path d="M12 12v3l2 2"/>',
            'communications' => '<path d="M12 12v10M8 22h8"/><path d="M8.5 8.5a5 5 0 0 1 7 0M5.5 5.5a9.5 9.5 0 0 1 13 0"/><circle cx="12" cy="12" r="1.5"/>',
            'language' => '<path d="M4 5h16v10H9l-5 4z"/><path d="M8 9h8M8 12h5"/>',
            'air_support' => '<path d="M12 2l3 7h7l-6 4 2 8-6-5-6 5 2-8-6-4h7z"/>',
            'fires' => '<path d="M4 20h16M7 20l5-14 5 14M9.5 13h5"/>',
            'sof', 'selection' => '<path d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
            'maritime' => '<path d="M12 3v15M7 8h10M5 13a7 7 0 0 0 14 0"/><circle cx="12" cy="3" r="1"/>',
            'intelligence' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'c2_staff' => '<path d="M5 21V3M5 4h12l-2 4 2 4H5"/>',
            'environment' => '<path d="M2 20l7-12 4 6 3-4 6 10z"/>',
            default => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14L7 22l5-3 5 3-1.5-8"/>',
        };

        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
    }
}
