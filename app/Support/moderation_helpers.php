<?php

declare(strict_types=1);

if (!function_exists('moderation_user_can_moderate')) {
    /**
     * Accès à la file des signalements membres (fiches, photos, formations, pages d’aide…).
     */
    function moderation_user_can_moderate(): bool
    {
        $gate = \App\Core\Gate::getInstance();
        if ($gate->allows('admin.system')) {
            return true;
        }
        if ($gate->allows('admin.organization') || $gate->allows('admin.access')) {
            return true;
        }
        if (!function_exists('can')) {
            return false;
        }

        return can('moderation.reports.manage') || can('admin.members.moderate');
    }
}

if (!function_exists('moderation_report_resolve_target_user_id')) {
    /**
     * Membre visé par un signalement (fiche, photo, dossier opérateur) quand l’identifiant
     * n’est pas stocké en colonne dédiée — dérivé du texte du signalement.
     */
    function moderation_report_resolve_target_user_id(array $report): ?int
    {
        if (!empty($report['reported_user_id'])) {
            $u = (int) $report['reported_user_id'];

            return $u > 0 ? $u : null;
        }
        $reason = (string) ($report['reason'] ?? '');
        $kind = (string) ($report['content_kind'] ?? '');
        if ($reason === '') {
            return null;
        }
        if (preg_match('/compte\s+n°\s*(\d+)/iu', $reason, $m)) {
            return (int) $m[1];
        }
        if (in_array($kind, ['member_profile', 'profile_picture', 'operator_visual'], true)
            && preg_match('/\(\s*n°\s*(\d+)\s*\)/u', $reason, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
