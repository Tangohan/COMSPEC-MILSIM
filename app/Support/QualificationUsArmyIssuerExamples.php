<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catalogue d’exemples réalistes d’organismes émetteurs US Army
 * pour peupler le référentiel qualifications (écoles / unités SOF).
 */
final class QualificationUsArmyIssuerExamples
{
    /**
     * @return list<array{
     *   key: string,
     *   name: string,
     *   short_name: string,
     *   issuer_kind: 'school'|'unit'|'external',
     *   parent_key?: string|null
     * }>
     */
    public static function catalog(): array
    {
        return [
            [
                'key' => 'mcoe',
                'name' => 'Maneuver Center of Excellence',
                'short_name' => 'MCoE',
                'issuer_kind' => 'school',
            ],
            [
                'key' => 'usais',
                'name' => 'U.S. Army Infantry School',
                'short_name' => 'USAIS',
                'issuer_kind' => 'school',
                'parent_key' => 'mcoe',
            ],
            [
                'key' => 'airborne',
                'name' => 'U.S. Army Airborne School',
                'short_name' => 'Airborne School',
                'issuer_kind' => 'school',
                'parent_key' => 'mcoe',
            ],
            [
                'key' => 'ranger-school',
                'name' => 'U.S. Army Ranger School',
                'short_name' => 'Ranger School',
                'issuer_kind' => 'school',
                'parent_key' => 'mcoe',
            ],
            [
                'key' => 'sniper',
                'name' => 'U.S. Army Sniper School',
                'short_name' => 'Sniper School',
                'issuer_kind' => 'school',
                'parent_key' => 'usais',
            ],
            [
                'key' => 'pathfinder',
                'name' => 'U.S. Army Pathfinder School',
                'short_name' => 'Pathfinder',
                'issuer_kind' => 'school',
                'parent_key' => 'mcoe',
            ],
            [
                'key' => 'usasoc',
                'name' => 'U.S. Army Special Operations Command',
                'short_name' => 'USASOC',
                'issuer_kind' => 'unit',
            ],
            [
                'key' => 'swcs',
                'name' => 'John F. Kennedy Special Warfare Center and School',
                'short_name' => 'USAJFKSWCS',
                'issuer_kind' => 'school',
                'parent_key' => 'usasoc',
            ],
            [
                'key' => '1sfc',
                'name' => '1st Special Forces Command (Airborne)',
                'short_name' => '1st SFC',
                'issuer_kind' => 'unit',
                'parent_key' => 'usasoc',
            ],
            [
                'key' => '75rr',
                'name' => '75th Ranger Regiment',
                'short_name' => '75th RR',
                'issuer_kind' => 'unit',
                'parent_key' => 'usasoc',
            ],
            [
                'key' => '160soar',
                'name' => '160th Special Operations Aviation Regiment (Airborne)',
                'short_name' => '160th SOAR',
                'issuer_kind' => 'unit',
                'parent_key' => 'usasoc',
            ],
            [
                'key' => 'mff',
                'name' => 'Military Free Fall School',
                'short_name' => 'MFF',
                'issuer_kind' => 'school',
                'parent_key' => 'swcs',
            ],
            [
                'key' => 'cdqc',
                'name' => 'Special Forces Combat Diver Qualification Course',
                'short_name' => 'CDQC',
                'issuer_kind' => 'school',
                'parent_key' => 'swcs',
            ],
            [
                'key' => 'sere',
                'name' => 'U.S. Army SERE School (Level C)',
                'short_name' => 'SERE-C',
                'issuer_kind' => 'school',
            ],
            [
                'key' => 'cac',
                'name' => 'U.S. Army Combined Arms Center',
                'short_name' => 'CAC',
                'issuer_kind' => 'external',
            ],
        ];
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'school' => 'École',
            'unit' => 'Unité',
            'external' => 'Organisme externe',
            default => $kind,
        };
    }
}
