<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Core\Gate;
use App\Services\Personnel\PersonnelCorrectionRequestService;
use App\Services\Workflow\WorkflowEngine;
use App\Support\EffectifsLmsAccess;

/**
 * File unique « Demandes RH » (back-office/demandes-rh) : met côte à côte les demandes en attente de décision des
 * différents types, avec l'avis du circuit de validation dans la ligne.
 *
 * Ce service ne décide de rien : chaque type garde son écran, ses droits et ses gardes (liens « Décider » vers
 * l'onglet du type). `visibleTypes()` reprend à l'identique le droit de lecture de chaque écran d'origine.
 */
final class HrRequestsDesk
{
    public const TYPE_ELEVATION = 'elevation';

    public const TYPE_CORRECTION = 'correction';

    public const TYPE_MOBILITY = 'mobility';

    /**
     * Type → libellé, onglet (?onglet=) et ancienne adresse de l'écran de décision (App\Support\BackOfficeTabs).
     *
     * @var array<string, array{label: string, tab: string, legacy: string}>
     */
    public const TYPES = [
        self::TYPE_ELEVATION => ['label' => 'Élévation', 'tab' => 'elevations', 'legacy' => 'back-office/ressources/effectifs/elevations'],
        self::TYPE_CORRECTION => ['label' => 'Correction', 'tab' => 'corrections', 'legacy' => 'back-office/personnel/corrections'],
        self::TYPE_MOBILITY => ['label' => 'Mobilité', 'tab' => 'mobilite', 'legacy' => 'back-office/ressources/effectifs/mobilite'],
    ];

    /** Lignes montrées par type sur la page « Tous » (les plus anciennes d'abord ; l'onglet du type montre le reste). */
    public const ROWS_PER_TYPE = 40;

    /**
     * Types que cette personne peut lire aujourd'hui : exactement le droit de l'écran d'origine
     * (élévations : EffectifsLmsAccess + gestion des rôles ou des statuts ; corrections : droits du personnel ;
     * mobilité : EffectifsLmsAccess). Aucun droit nouveau.
     *
     * @return list<string>
     */
    public static function visibleTypes(Gate $gate): array
    {
        $types = [];
        $effectifs = EffectifsLmsAccess::allows($gate);
        if ($effectifs && (EffectifsLmsAccess::canManageRoles($gate) || EffectifsLmsAccess::canManageStatus($gate))) {
            $types[] = self::TYPE_ELEVATION;
        }
        foreach (PersonnelCorrectionRequestService::STAFF_PERMISSIONS as $slug) {
            if ($gate->allows($slug)) {
                $types[] = self::TYPE_CORRECTION;
                break;
            }
        }
        if ($effectifs) {
            $types[] = self::TYPE_MOBILITY;
        }

        return $types;
    }

    /**
     * @param array<string, mixed> $r ligne elevation_requests (+ target_display_name, target_email)
     * @param array{grade?: ?string, role?: ?string, job_role?: ?string, unit?: ?string} $labels
     * @param array<string, mixed>|null $circuit résumé de WorkflowEngine::summaries
     * @return array<string, mixed>
     */
    public static function elevationRow(array $r, array $labels, ?array $circuit): array
    {
        $bits = [];
        foreach (['grade' => 'Grade', 'role' => 'Rôle', 'job_role' => 'Fonction', 'unit' => 'Affectation'] as $key => $caption) {
            if (!empty($labels[$key])) {
                $bits[] = $caption . ' « ' . $labels[$key] . ' »';
            }
        }
        $kind = (string) ($r['kind'] ?? 'general');
        $kindLabel = EffectifsStaffAlertService::ELEVATION_KIND_LABELS[$kind] ?? EffectifsStaffAlertService::ELEVATION_KIND_LABELS['general'];

        return self::row(
            self::TYPE_ELEVATION,
            (int) ($r['id'] ?? 0),
            self::name((string) ($r['target_display_name'] ?? ''), (string) ($r['target_email'] ?? '')),
            $bits !== [] ? implode(' · ', $bits) : $kindLabel,
            (string) ($r['created_at'] ?? ''),
            $circuit
        );
    }

    /**
     * @param array<string, mixed> $r ligne de personnel_correction_requests (hydratée) + target_display_name, target_callsign
     * @param list<string> $diffLines lignes « Champ : « avant » → « après » »
     * @param array<string, mixed>|null $circuit
     * @return array<string, mixed>
     */
    public static function correctionRow(array $r, array $diffLines, ?array $circuit): array
    {
        $text = $diffLines === [] ? 'Correction de la fiche' : $diffLines[0];
        if (count($diffLines) > 1) {
            $text .= ' (+' . (count($diffLines) - 1) . ')';
        }

        return self::row(
            self::TYPE_CORRECTION,
            (int) ($r['id'] ?? 0),
            self::name((string) ($r['target_display_name'] ?? ''), (string) ($r['target_callsign'] ?? '')),
            $text,
            (string) ($r['created_at'] ?? ''),
            $circuit
        );
    }

    /**
     * @param array<string, mixed> $r ligne de personnel_mobility_requests + user_display_name, user_email, target_unit_name
     * @param array<string, string> $typeLabels
     * @param array<string, mixed>|null $circuit
     * @return array<string, mixed>
     */
    public static function mobilityRow(array $r, array $typeLabels, ?array $circuit): array
    {
        $type = (string) ($r['request_type'] ?? '');
        $target = trim((string) ($r['target_label'] ?? ''));
        if ($target === '') {
            $target = trim((string) ($r['target_unit_name'] ?? ''));
        }
        $label = $typeLabels[$type] ?? $type;

        return self::row(
            self::TYPE_MOBILITY,
            (int) ($r['id'] ?? 0),
            self::name((string) ($r['user_display_name'] ?? ''), (string) ($r['user_email'] ?? '')),
            $target !== '' ? $label . ' : ' . $target : $label,
            (string) ($r['created_at'] ?? ''),
            $circuit
        );
    }

    /**
     * Avis du circuit, en phrases (mêmes formulations que l'écran de chaque type). Vide s'il n'y a pas de circuit.
     *
     * @param array<string, mixed>|null $circuit
     * @return list<string>
     */
    public static function opinionLines(?array $circuit): array
    {
        if ($circuit === null) {
            return [];
        }
        $lines = [];
        $opinions = is_array($circuit['opinions'] ?? null) ? $circuit['opinions'] : [];
        foreach ($opinions as $opinion) {
            $favorable = ($opinion['opinion'] ?? '') === 'favorable';
            $label = trim((string) ($opinion['label'] ?? ''));
            $role = (string) ($opinion['role'] ?? '');
            if ($role === 'user') {
                $line = ($favorable ? 'Avis favorable' : 'Avis défavorable') . ($label !== '' ? ' : ' . $label : '');
            } else {
                $who = $role === 'upper' ? 'de l’échelon supérieur' : 'du chef d’unité';
                $line = ($favorable ? 'Avis favorable ' : 'Avis défavorable ') . $who . ($label !== '' ? ' (' . $label . ')' : '');
            }
            $comment = trim((string) ($opinion['comment'] ?? ''));
            $lines[] = $comment !== '' ? $line . ' : « ' . $comment . ' »' : $line;
        }
        if (($circuit['state'] ?? '') === WorkflowEngine::STATE_OPINION) {
            $assignee = trim((string) ($circuit['assignee_label'] ?? ''));
            $lines[] = match ((string) ($circuit['assignee_role'] ?? '')) {
                'upper' => 'Avis attendu de l’échelon supérieur',
                'user' => 'Avis attendu',
                default => 'Avis attendu du chef d’unité',
            } . ($assignee !== '' ? ' : ' . $assignee : '');
        } elseif (($circuit['opinion'] ?? '') === 'skipped') {
            $lines[] = 'Décidé sans attendre l’avis demandé';
        }

        return $lines;
    }

    /**
     * Les plus anciennes demandes d'abord (celles qui attendent depuis le plus longtemps).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function sortOldestFirst(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => [(string) $a['created_at'], $a['type'], $a['id']] <=> [(string) $b['created_at'], $b['type'], $b['id']]);

        return $rows;
    }

    /**
     * @param array<string, mixed>|null $circuit
     * @return array<string, mixed>
     */
    private static function row(string $type, int $id, string $member, string $request, string $createdAt, ?array $circuit): array
    {
        return [
            'type' => $type,
            'type_label' => self::TYPES[$type]['label'],
            'tab' => self::TYPES[$type]['tab'],
            'id' => $id,
            'member' => $member,
            'request' => $request,
            'created_at' => $createdAt,
            'opinion' => self::opinionLines($circuit),
        ];
    }

    private static function name(string $display, string $fallback): string
    {
        $display = trim($display);
        if ($display !== '') {
            return $display;
        }
        $fallback = trim($fallback);

        return $fallback !== '' ? $fallback : 'Membre';
    }
}
