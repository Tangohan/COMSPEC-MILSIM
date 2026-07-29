<?php

declare(strict_types=1);

/**
 * @return array{
 *   accent?:string,
 *   accentRgb?:string,
 *   font?:string,
 *   radius?:string,
 *   variant?:string,
 *   openingLoaderImage?:string,
 *   openingLoaderTitle?:string,
 *   openingLoaderBody?:string
 * }
 */
function training_lms_parse_theme(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }
    $d = json_decode($json, true);
    if (!is_array($d)) {
        return [];
    }
    $out = [];
    foreach (['accent', 'accentRgb', 'font', 'radius', 'variant', 'openingLoaderImage', 'openingLoaderTitle', 'openingLoaderBody'] as $k) {
        if (isset($d[$k]) && is_string($d[$k])) {
            $out[$k] = $d[$k];
        }
    }

    return $out;
}

/** Attributs style inline pour :root (échapper les valeurs côté appel). */
function training_lms_theme_css_vars(array $theme): string
{
    $accent = $theme['accent'] ?? '#10b981';
    $rgb = $theme['accentRgb'] ?? '16, 185, 129';
    $font = $theme['font'] ?? 'Inter, system-ui, sans-serif';
    $radius = $theme['radius'] ?? '2rem';

    return '--lms-accent: ' . $accent . '; --lms-accent-rgb: ' . $rgb . '; --lms-font: ' . $font . '; --lms-radius: ' . $radius . ';';
}

/**
 * @param array<string, mixed> $course
 * @return list<string>
 */
function training_lms_learning_objectives(array $course): array
{
    $raw = $course['learning_objectives'] ?? null;
    if ($raw === null || $raw === '') {
        return [];
    }
    if (is_string($raw)) {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            return array_values(array_filter(array_map(static fn ($x) => is_string($x) ? trim($x) : '', $j)));
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $lines)));
    }

    return [];
}

/**
 * Libellés français pour les types de leçon (clé technique = valeur affichée).
 *
 * @return array<string, string>
 */
function training_lesson_type_labels_fr(): array
{
    return [
        'richtext' => 'Texte enrichi',
        'video' => 'Vidéo (URL fichier directe)',
        'video_integrated' => 'Vidéo intégrée (MP4 / WebM)',
        'video_embed' => 'Vidéo intégrée (YouTube / Vimeo)',
        'pdf' => 'Document PDF',
        'audio' => 'Audio',
        'scorm_like' => 'Paquet SCORM',
        'checklist' => 'Liste de contrôle',
        'external_link' => 'Lien externe',
        'canvas' => 'Parcours visuel (slides & modales)',
        'quiz' => 'Quiz',
        'modals' => 'Modales',
        'slideshow' => 'Diaporama',
    ];
}

/**
 * Libellés apprenant (portail) — sans jargon technique d’édition.
 *
 * @return array<string, string>
 */
function training_lesson_type_labels_learner_fr(): array
{
    return [
        'richtext' => 'Fiche de synthèse',
        'video' => 'Vidéo',
        'video_integrated' => 'Vidéo',
        'video_embed' => 'Vidéo',
        'pdf' => 'Document à consulter',
        'audio' => 'Audio',
        'scorm_like' => 'Parcours guidé',
        'checklist' => 'Liste de contrôle',
        'external_link' => 'Ressource externe',
        'canvas' => 'Parcours interactif',
        'quiz' => 'Évaluation',
        'modals' => 'Fiches à explorer',
        'slideshow' => 'Diaporama',
    ];
}

/**
 * Regroupement des types pour les listes déroulantes (ordre d’affichage).
 *
 * @return array<string, list<string>>
 */
function training_lesson_type_optgroups(): array
{
    return [
        'Texte & pages' => ['richtext', 'checklist'],
        'Vidéo & audio' => ['video', 'video_integrated', 'video_embed', 'audio'],
        'Documents & liens' => ['pdf', 'external_link'],
        'Parcours & SCORM' => ['canvas', 'scorm_like'],
        'Interactif (quiz, modales, diaporama)' => ['quiz', 'modals', 'slideshow'],
    ];
}

/**
 * Niveaux de difficulté (alignés sur training_courses.level).
 *
 * @return array<string, string>
 */
function training_course_level_labels_fr(): array
{
    return [
        'initiation' => 'Initiation',
        'intermediaire' => 'Intermédiaire',
        'avance' => 'Avancé',
        'expert' => 'Expert',
    ];
}

/**
 * Libellé métier pour la portée catalogue (Studio / cartes catalogue).
 */
function training_lms_course_scope_label_fr(?string $scope): string
{
    $s = trim((string) $scope);

    return $s === 'platform'
        ? 'Proposé sur toute la plateforme'
        : 'Parcours de la communauté';
}

/**
 * Toutes les leçons du parcours dans l’ordre des modules (liste plate).
 *
 * @param array<string, mixed> $course Résultat de getCourseWithStructure
 * @return list<array<string, mixed>>
 */
function training_lms_ordered_lessons(array $course): array
{
    $out = [];
    foreach ($course['modules'] ?? [] as $mod) {
        foreach ($mod['lessons'] ?? [] as $l) {
            $out[] = $l;
        }
    }

    return $out;
}

/**
 * Première leçon non terminée (progression ≠ completed), ou null si tout est fait.
 *
 * @param list<array<string, mixed>> $orderedLessons
 * @param list<array<string, mixed>> $progressRows training_progress + métadonnées
 */
function training_lms_next_incomplete_lesson(array $orderedLessons, array $progressRows): ?array
{
    $statusByLesson = [];
    foreach ($progressRows as $p) {
        $lid = (int) ($p['lesson_id'] ?? 0);
        if ($lid > 0) {
            $statusByLesson[$lid] = (string) ($p['status'] ?? '');
        }
    }
    foreach ($orderedLessons as $l) {
        $lid = (int) ($l['id'] ?? 0);
        if ($lid < 1) {
            continue;
        }
        $st = $statusByLesson[$lid] ?? 'not_started';
        if ($st !== 'completed') {
            return $l;
        }
    }

    return null;
}

/**
 * Prochaine étape en bas de page leçon : leçon suivante, quiz de module, ou page « Avis & échanges ».
 * Respecte l’ordre du sommaire (leçons du module, puis quiz non finaux du module, puis modules suivants, puis quiz finaux).
 *
 * @param callable(int): bool $quizHasPassingAttempt true si au moins une tentative réussie pour ce quiz
 * @return array{kind: 'lesson', lesson: array<string, mixed>}|array{kind: 'quiz', quiz: array{id: int, title: string}}|array{kind: 'echanges'}|null null si leçon introuvable dans le parcours
 */
function training_lms_footer_next_step(array $course, int $currentLessonId, callable $quizHasPassingAttempt): ?array
{
    $modules = $course['modules'] ?? [];
    if (!is_array($modules) || $modules === []) {
        return ['kind' => 'echanges'];
    }

    $foundMi = null;
    $foundLi = null;
    foreach ($modules as $mi => $mod) {
        if (!is_array($mod)) {
            continue;
        }
        $lessons = $mod['lessons'] ?? [];
        if (!is_array($lessons)) {
            $lessons = [];
        }
        foreach ($lessons as $li => $l) {
            if (!is_array($l)) {
                continue;
            }
            if ((int) ($l['id'] ?? 0) === $currentLessonId) {
                $foundMi = (int) $mi;
                $foundLi = (int) $li;
                break 2;
            }
        }
    }
    if ($foundMi === null || $foundLi === null) {
        return null;
    }

    $mod = $modules[$foundMi];
    $lessons = is_array($mod['lessons'] ?? null) ? $mod['lessons'] : [];

    if ($foundLi + 1 < count($lessons)) {
        $nextLes = $lessons[$foundLi + 1];
        if (is_array($nextLes) && (int) ($nextLes['id'] ?? 0) > 0) {
            return ['kind' => 'lesson', 'lesson' => $nextLes];
        }
    }

    $quizzes = is_array($mod['quizzes'] ?? null) ? $mod['quizzes'] : [];
    foreach ($quizzes as $qz) {
        if (!is_array($qz)) {
            continue;
        }
        if ((int) ($qz['is_final_exam'] ?? 0) === 1) {
            continue;
        }
        $qid = (int) ($qz['id'] ?? 0);
        if ($qid < 1) {
            continue;
        }
        if (!$quizHasPassingAttempt($qid)) {
            return [
                'kind' => 'quiz',
                'quiz' => [
                    'id' => $qid,
                    'title' => trim((string) ($qz['title'] ?? 'Évaluation')) !== ''
                        ? trim((string) ($qz['title'] ?? ''))
                        : 'Évaluation',
                ],
            ];
        }
    }

    $nMod = count($modules);
    for ($mi2 = $foundMi + 1; $mi2 < $nMod; $mi2++) {
        $mod2 = $modules[$mi2];
        if (!is_array($mod2)) {
            continue;
        }
        $lessons2 = is_array($mod2['lessons'] ?? null) ? $mod2['lessons'] : [];
        foreach ($lessons2 as $l2) {
            if (!is_array($l2)) {
                continue;
            }
            $lid = (int) ($l2['id'] ?? 0);
            if ($lid > 0) {
                return ['kind' => 'lesson', 'lesson' => $l2];
            }
        }
        $quizzes2 = is_array($mod2['quizzes'] ?? null) ? $mod2['quizzes'] : [];
        foreach ($quizzes2 as $qz) {
            if (!is_array($qz)) {
                continue;
            }
            if ((int) ($qz['is_final_exam'] ?? 0) === 1) {
                continue;
            }
            $qid = (int) ($qz['id'] ?? 0);
            if ($qid < 1) {
                continue;
            }
            if (!$quizHasPassingAttempt($qid)) {
                return [
                    'kind' => 'quiz',
                    'quiz' => [
                        'id' => $qid,
                        'title' => trim((string) ($qz['title'] ?? 'Évaluation')) !== ''
                            ? trim((string) ($qz['title'] ?? ''))
                            : 'Évaluation',
                    ],
                ];
            }
        }
    }

    foreach ($modules as $modF) {
        if (!is_array($modF)) {
            continue;
        }
        $qzf = is_array($modF['quizzes'] ?? null) ? $modF['quizzes'] : [];
        foreach ($qzf as $qz) {
            if (!is_array($qz)) {
                continue;
            }
            if ((int) ($qz['is_final_exam'] ?? 0) !== 1) {
                continue;
            }
            $qid = (int) ($qz['id'] ?? 0);
            if ($qid < 1) {
                continue;
            }
            if (!$quizHasPassingAttempt($qid)) {
                return [
                    'kind' => 'quiz',
                    'quiz' => [
                        'id' => $qid,
                        'title' => trim((string) ($qz['title'] ?? 'Évaluation finale')) !== ''
                            ? trim((string) ($qz['title'] ?? ''))
                            : 'Évaluation finale',
                    ],
                ];
            }
        }
    }

    return ['kind' => 'echanges'];
}

/**
 * Statuts pour lesquels l’inscription n’est plus considérée comme active côté membre (fiche formation, API).
 *
 * @return list<string>
 */
function training_enrollment_inactive_for_member_ui_statuses(): array
{
    return ['revoked', 'expired', 'withdrawn'];
}

/**
 * Le membre peut-il demander l’annulation de son inscription (contrôler séparément l’attestation délivrée).
 *
 * @param array<string, mixed> $enrollment
 */
function training_enrollment_can_withdraw_by_member(array $enrollment): bool
{
    $st = (string) ($enrollment['status'] ?? '');

    return in_array($st, ['assigned', 'in_progress', 'pending_approval', 'failed'], true);
}

/**
 * Libellé français pour une action du journal d’audit formations (training_audit_log.action).
 */
function training_audit_action_label_fr(string $action): string
{
    static $map = [
        'course_created' => 'Formation créée',
        'course_updated' => 'Formation modifiée',
        'course_published' => 'Formation publiée',
        'enrollment_assigned' => 'Inscription assignée',
        'enrollment_withdrawn' => 'Inscription annulée par le membre',
        'lesson_completed' => 'Leçon terminée',
        'quiz_attempt_submitted' => 'Tentative de quiz soumise',
        'certificate_issued' => 'Certificat délivré',
        'certificate_revoked' => 'Certificat révoqué',
    ];

    return $map[$action] ?? $action;
}

/**
 * Libellé français pour un type de cible (training_audit_log.target_type).
 */
function training_audit_target_type_label_fr(string $targetType): string
{
    static $map = [
        'training_course' => 'Formation',
        'training_enrollment' => 'Inscription',
        'training_progress' => 'Progression',
        'training_quiz_attempt' => 'Tentative de quiz',
        'training_certificate' => 'Certificat',
    ];

    return $map[$targetType] ?? $targetType;
}

/**
 * Libellé lisible pour l’acteur d’une ligne d’audit (nom affiché ou e-mail).
 */
function training_audit_actor_label_fr(?string $displayName, ?string $email): string
{
    $d = trim((string) $displayName);
    if ($d !== '') {
        return $d;
    }
    $e = trim((string) $email);
    if ($e !== '') {
        return $e;
    }

    return 'Automatique ou compte inconnu';
}

/**
 * Libellé pour le créateur / référent du parcours (colonne « Référent pédagogique »).
 */
function training_audit_course_author_label_fr(?string $displayName, ?string $email): string
{
    $d = trim((string) $displayName);
    if ($d !== '') {
        return $d;
    }
    $e = trim((string) $email);
    if ($e !== '') {
        return $e;
    }

    return '—';
}

/**
 * Résumé métier d’une ligne d’audit (évite d’afficher du JSON brut).
 *
 * @param array<string, mixed> $logRow ligne enrichie (new_value déjà tableau si possible)
 */
function training_audit_detail_summary_fr(array $logRow): string
{
    $new = $logRow['new_value'] ?? null;
    if (!is_array($new)) {
        $new = [];
    }

    $action = (string) ($logRow['action'] ?? '');

    if ($action === 'lesson_completed') {
        return isset($new['lesson_id']) && (int) $new['lesson_id'] > 0
            ? 'Une leçon du parcours a été validée.'
            : 'Progression enregistrée.';
    }

    if ($action === 'quiz_attempt_submitted') {
        $parts = [];
        if (array_key_exists('score', $new) && $new['score'] !== null && $new['score'] !== '') {
            $parts[] = 'Score : ' . round((float) $new['score'], 1) . ' %';
        }
        if (array_key_exists('passed', $new)) {
            $parts[] = !empty($new['passed']) ? 'Réussite' : 'Non validé';
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Tentative enregistrée.';
    }

    if ($action === 'enrollment_assigned') {
        $typeMap = [
            'manual' => 'Assignation manuelle',
            'role' => 'Par rôle',
            'unit' => 'Par unité',
            'campaign' => 'Campagne',
            'self_enroll' => 'Inscription spontanée',
        ];
        $type = $typeMap[(string) ($new['assignment_type'] ?? '')] ?? 'Assignation';
        $statusMap = [
            'assigned' => 'Statut : assigné',
            'in_progress' => 'Statut : en cours',
            'completed' => 'Statut : terminé',
            'failed' => 'Statut : non validé',
            'expired' => 'Statut : expiré',
            'revoked' => 'Statut : retiré',
            'withdrawn' => 'Statut : inscription annulée par le membre',
            'pending_approval' => 'Statut : en attente de validation',
        ];
        $st = $statusMap[(string) ($new['status'] ?? '')] ?? '';
        $mot = !empty($new['motivation_provided']) ? 'Motivation transmise.' : '';
        if (!empty($new['declined_from_pending'])) {
            return 'Demande d’inscription refusée.';
        }

        return trim($type . ($st !== '' ? ' — ' . $st : '') . ($mot !== '' ? ' ' . $mot : ''));
    }

    if ($action === 'enrollment_withdrawn') {
        $title = trim((string) ($new['course_title'] ?? ''));
        $prevSt = (string) ($new['previous_status'] ?? '');
        $phase = match ($prevSt) {
            'assigned' => 'avant le démarrage du parcours',
            'in_progress' => 'alors que le parcours était en cours',
            'pending_approval' => 'pendant l’attente de validation de la demande',
            'failed' => 'après un parcours non validé',
            default => '',
        };
        $head = 'Le membre a annulé son inscription';
        if ($title !== '') {
            $head .= ' à « ' . $title . ' »';
        }
        if ($phase !== '') {
            $head .= ' ' . $phase;
        }

        return $head . '.';
    }

    if ($action === 'certificate_issued') {
        $num = trim((string) ($new['certificate_number'] ?? ''));

        return $num !== '' ? 'Référence du document : ' . $num : 'Certificat enregistré.';
    }

    if ($action === 'certificate_revoked') {
        return 'Certificat retiré.';
    }

    if ($action === 'course_created') {
        return 'Nouvelle formation enregistrée.';
    }

    if ($action === 'course_published') {
        return 'Parcours rendu visible dans le catalogue.';
    }

    if ($action === 'course_updated') {
        if (!empty($new['enrollment_share_code_regenerated'])) {
            return 'Lien d’inscription par code renouvelé.';
        }
        if (isset($new['module_created'])) {
            return 'Nouveau module ajouté au parcours.';
        }

        return 'Parcours mis à jour.';
    }

    return '—';
}

/**
 * Objet métier : type de cible + formation concernée si connue.
 *
 * @param array<string, mixed> $logRow
 */
function training_audit_object_label_fr(array $logRow): string
{
    $targetSlug = (string) ($logRow['target_type'] ?? '');
    $targetLabel = function_exists('training_audit_target_type_label_fr')
        ? training_audit_target_type_label_fr($targetSlug)
        : $targetSlug;
    $courseTitle = trim((string) ($logRow['ctx_course_title'] ?? ''));
    if ($courseTitle !== '') {
        return $targetLabel . ' — ' . $courseTitle;
    }

    return $targetLabel;
}

/** Masque une URL pour l’aperçu caviardé (aucune fuite de cible réelle). */
function training_preview_redact_url(?string $url): string
{
    if ($url === null || trim($url) === '') {
        return '—';
    }

    return 'https://•••••••••••••••••••••••••••••••';
}

/**
 * Objectifs pédagogiques stockés en JSON, texte multi-lignes ou vide.
 *
 * @return list<string>
 */
function training_lms_objectives_list_from_storage(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $raw = trim($raw);
    $j = json_decode($raw, true);
    if (is_array($j)) {
        return array_values(array_filter(array_map(static fn ($x) => is_string($x) ? trim($x) : '', $j), static fn (string $s): bool => $s !== ''));
    }
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

    return array_values(array_filter(array_map(static fn (string $s): string => trim($s), $lines), static fn (string $s): bool => $s !== ''));
}

/**
 * @return array<string, string> clé technique => valeur CSS font-family
 */
function training_lms_theme_font_presets(): array
{
    return [
        'inter' => 'Inter, system-ui, sans-serif',
        'system' => 'system-ui, sans-serif',
        'serif' => 'Georgia, Cambria, "Times New Roman", serif',
        'mono' => 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
    ];
}

/**
 * @return array<string, string> clé technique => libellé français (studio)
 */
function training_lms_theme_font_labels_fr(): array
{
    return [
        'inter' => 'Sans empattement (Inter)',
        'system' => 'Police du système',
        'serif' => 'Avec empattement',
        'mono' => 'Style technique (monospace)',
    ];
}

/**
 * @return array<string, string> clé => valeur CSS (border-radius global du parcours)
 */
function training_lms_theme_radius_presets(): array
{
    return [
        'generous' => '2rem',
        'medium' => '1rem',
        'tight' => '0.5rem',
    ];
}

/**
 * @return array<string, string>
 */
function training_lms_theme_radius_labels_fr(): array
{
    return [
        'generous' => 'Très arrondi',
        'medium' => 'Modéré',
        'tight' => 'Discret',
    ];
}

/**
 * @return array<string, string> clé => libellé (variante d’ambiance, extension future du thème)
 */
function training_lms_theme_variant_labels_fr(): array
{
    return [
        'default' => 'Standard',
        'soft' => 'Plus doux',
    ];
}

/** Convertit #RRGGBB (ou RRGGBB) en « r, g, b » pour les variables CSS. */
function training_lms_hex_to_rgb_csv(string $hex): string
{
    $hex = trim($hex);
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '16, 185, 129';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    return $r . ', ' . $g . ', ' . $b;
}

function training_lms_theme_font_key_from_css(?string $css): string
{
    $css = trim((string) $css);
    foreach (training_lms_theme_font_presets() as $k => $v) {
        if ($v === $css) {
            return $k;
        }
    }

    return 'inter';
}

function training_lms_theme_radius_key_from_value(?string $radius): string
{
    $radius = trim((string) $radius);
    foreach (training_lms_theme_radius_presets() as $k => $v) {
        if ($v === $radius) {
            return $k;
        }
    }

    return 'generous';
}

/**
 * Statuts de compte (users.status) proposés pour la politique d’auto-inscription — valeurs BDD en clé.
 *
 * @return array<string, string>
 */
function training_lms_enrollment_user_status_labels_fr(): array
{
    return [
        'active' => 'Compte actif',
        'pending_verification' => 'En attente de confirmation de l’e-mail',
        'pending' => 'Compte en attente (autre)',
    ];
}

/**
 * Types de ressources pédagogiques (training_resources.resource_type) — libellés studio / apprenant.
 *
 * @return array<string, string>
 */
function training_lms_resource_type_labels_fr(): array
{
    return [
        'link' => 'Lien web',
        'pdf' => 'Document PDF',
        'video' => 'Vidéo',
        'audio' => 'Audio',
        'image' => 'Image',
        'zip' => 'Archive',
        'attachment' => 'Fichier joint',
        'library_document' => 'Document du centre',
    ];
}

/**
 * Libellé affiché dans le studio pour une ligne de ressource (bibliothèque vs type fichier / lien).
 *
 * @param array<string, mixed> $row
 */
function training_lms_studio_resource_kind_label_fr(array $row): string
{
    if (!empty($row['document_id']) || ($row['resource_type'] ?? '') === 'library_document') {
        return 'Document du centre';
    }

    $t = (string) ($row['resource_type'] ?? 'link');
    $map = training_lms_resource_type_labels_fr();

    return $map[$t] ?? $t;
}

/**
 * Indique si une ressource de leçon doit s’afficher comme image (aperçu / inline).
 *
 * @param array<string, mixed> $row
 */
function training_lms_resource_is_image(array $row): bool
{
    if ((string) ($row['resource_type'] ?? '') === 'image') {
        return true;
    }
    $mime = strtolower(trim((string) ($row['mime_type'] ?? '')));

    return $mime !== '' && str_starts_with($mime, 'image/');
}

/**
 * @return array<string, string>
 */
function training_lms_document_status_labels_fr(): array
{
    return [
        'draft' => 'Brouillon',
        'review' => 'En relecture',
        'approval' => 'À valider',
        'published' => 'Publié',
        'suspended' => 'Suspendu',
        'archived' => 'Archivé',
        'obsolete' => 'Obsolète',
    ];
}

/**
 * @param array<string, mixed> $policy Décodage de enrollment_policy_json
 */
function training_lms_policy_comments_enabled(array $policy): bool
{
    if (!array_key_exists('comments_enabled', $policy)) {
        return true;
    }

    return $policy['comments_enabled'] === true || $policy['comments_enabled'] === 1 || $policy['comments_enabled'] === '1';
}

/**
 * @param array<string, mixed> $policy
 */
function training_lms_policy_self_enroll_requires_approval(array $policy): bool
{
    return !empty($policy['self_enroll_requires_approval']);
}

/** Normalise un code saisi (majuscules, alphanumérique). */
function training_lms_normalize_share_code(string $raw): string
{
    $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');

    return $s;
}

/** Code court unique (évite O/0 et I/1 pour la lecture à voix). */
function training_lms_generate_enrollment_share_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 10; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $out;
}

/**
 * Lien web de ressource pédagogique : même site en direct, sinon interstitiel /leave signé.
 */
function training_lms_resource_external_href(string $rawUrl): ?string
{
    $svc = new \App\Services\Forum\ExternalLeaveService();
    $clean = $svc->sanitizeHttpUrl(trim($rawUrl));
    if ($clean === null) {
        return null;
    }
    if ($svc->isInternalUrl($clean)) {
        return $clean;
    }

    return $svc->buildSignedLeaveUrl($clean);
}

/**
 * Parcours guidé plat : Préambule → leçons du module → évaluation(s) du module → module suivant → examens finaux → avis.
 * Ne crée aucune fausse leçon : uniquement la structure déjà en base.
 *
 * @return list<array{
 *   kind: 'preamble'|'lesson'|'quiz'|'echanges',
 *   key: string,
 *   label: string,
 *   phase: string,
 *   module_id?: int,
 *   module_title?: string,
 *   lesson?: array<string, mixed>,
 *   quiz?: array{id: int, title: string, is_final: bool}
 * }>
 */
function training_lms_build_guided_sequence(array $course): array
{
    $steps = [];
    $steps[] = [
        'kind' => 'preamble',
        'key' => 'preamble',
        'label' => 'Préambule',
        'phase' => 'Avant de commencer',
    ];

    $modules = $course['modules'] ?? [];
    if (!is_array($modules)) {
        $modules = [];
    }

    $moduleIndex = 0;
    foreach ($modules as $mod) {
        if (!is_array($mod)) {
            continue;
        }
        $moduleIndex++;
        $modId = (int) ($mod['id'] ?? 0);
        $modTitle = trim((string) ($mod['title'] ?? ''));
        if ($modTitle === '') {
            $modTitle = 'Module ' . $moduleIndex;
        }
        $phase = 'Module ' . $moduleIndex . ' — ' . $modTitle;

        $lessons = is_array($mod['lessons'] ?? null) ? $mod['lessons'] : [];
        $lessonOrdinal = 0;
        foreach ($lessons as $les) {
            if (!is_array($les)) {
                continue;
            }
            $lid = (int) ($les['id'] ?? 0);
            if ($lid < 1) {
                continue;
            }
            $lessonOrdinal++;
            $ltitle = trim((string) ($les['title'] ?? ''));
            if ($ltitle === '') {
                $ltitle = 'Leçon ' . $lessonOrdinal;
            }
            $steps[] = [
                'kind' => 'lesson',
                'key' => 'lesson:' . $lid,
                'label' => $ltitle,
                'phase' => $phase,
                'module_id' => $modId,
                'module_title' => $modTitle,
                'lesson' => $les,
            ];
        }

        $quizzes = is_array($mod['quizzes'] ?? null) ? $mod['quizzes'] : [];
        foreach ($quizzes as $qz) {
            if (!is_array($qz)) {
                continue;
            }
            if ((int) ($qz['is_final_exam'] ?? 0) === 1) {
                continue;
            }
            $qid = (int) ($qz['id'] ?? 0);
            if ($qid < 1) {
                continue;
            }
            $qtitle = trim((string) ($qz['title'] ?? ''));
            if ($qtitle === '') {
                $qtitle = 'Évaluation du module';
            }
            $steps[] = [
                'kind' => 'quiz',
                'key' => 'quiz:' . $qid,
                'label' => $qtitle,
                'phase' => $phase . ' — évaluation',
                'module_id' => $modId,
                'module_title' => $modTitle,
                'quiz' => [
                    'id' => $qid,
                    'title' => $qtitle,
                    'is_final' => false,
                ],
            ];
        }
    }

    foreach ($modules as $mod) {
        if (!is_array($mod)) {
            continue;
        }
        $quizzes = is_array($mod['quizzes'] ?? null) ? $mod['quizzes'] : [];
        foreach ($quizzes as $qz) {
            if (!is_array($qz) || (int) ($qz['is_final_exam'] ?? 0) !== 1) {
                continue;
            }
            $qid = (int) ($qz['id'] ?? 0);
            if ($qid < 1) {
                continue;
            }
            $qtitle = trim((string) ($qz['title'] ?? ''));
            if ($qtitle === '') {
                $qtitle = 'Évaluation finale';
            }
            $steps[] = [
                'kind' => 'quiz',
                'key' => 'quiz:' . $qid,
                'label' => $qtitle,
                'phase' => 'Évaluation finale',
                'quiz' => [
                    'id' => $qid,
                    'title' => $qtitle,
                    'is_final' => true,
                ],
            ];
        }
    }

    $steps[] = [
        'kind' => 'echanges',
        'key' => 'echanges',
        'label' => 'Avis & échanges',
        'phase' => 'Fin de parcours',
    ];

    return $steps;
}

/**
 * Position dans la séquence guidée (étape courante + suivante).
 *
 * @param list<array<string, mixed>> $steps
 * @param 'preamble'|'lesson'|'quiz'|'echanges' $context
 * @return array{
 *   index: int,
 *   total: int,
 *   current: array<string, mixed>|null,
 *   next: array<string, mixed>|null,
 *   previous: array<string, mixed>|null
 * }
 */
function training_lms_sequence_position(
    array $steps,
    string $context,
    ?int $lessonId = null,
    ?int $quizId = null
): array {
    $total = count($steps);
    $index = 0;
    if ($context === 'lesson' && $lessonId !== null && $lessonId > 0) {
        foreach ($steps as $i => $s) {
            if (($s['kind'] ?? '') === 'lesson' && (int) (($s['lesson']['id'] ?? 0)) === $lessonId) {
                $index = (int) $i;
                break;
            }
        }
    } elseif ($context === 'quiz' && $quizId !== null && $quizId > 0) {
        foreach ($steps as $i => $s) {
            if (($s['kind'] ?? '') === 'quiz' && (int) (($s['quiz']['id'] ?? 0)) === $quizId) {
                $index = (int) $i;
                break;
            }
        }
    } elseif ($context === 'echanges') {
        foreach ($steps as $i => $s) {
            if (($s['kind'] ?? '') === 'echanges') {
                $index = (int) $i;
                break;
            }
        }
    } else {
        // preamble / fiche parcours
        $index = 0;
    }

    $current = $steps[$index] ?? null;
    $next = ($index + 1 < $total) ? $steps[$index + 1] : null;
    $previous = ($index > 0) ? $steps[$index - 1] : null;

    return [
        'index' => $index,
        'total' => $total,
        'current' => is_array($current) ? $current : null,
        'next' => is_array($next) ? $next : null,
        'previous' => is_array($previous) ? $previous : null,
    ];
}

/**
 * Libellé humain pour une étape de séquence (CTA / bandeau).
 *
 * @param array<string, mixed>|null $step
 */
function training_lms_sequence_step_human_label(?array $step): string
{
    if ($step === null) {
        return '';
    }
    $kind = (string) ($step['kind'] ?? '');
    $label = trim((string) ($step['label'] ?? ''));
    return match ($kind) {
        'preamble' => 'Préambule du parcours',
        'lesson' => $label !== '' ? 'Leçon — ' . $label : 'Leçon suivante',
        'quiz' => $label !== ''
            ? ((stripos($label, 'évaluation') !== false || stripos($label, 'quiz') !== false) ? $label : 'Évaluation — ' . $label)
            : 'Évaluation',
        'echanges' => 'Avis et échanges de fin de parcours',
        default => $label,
    };
}

/**
 * Indique si une étape leçon est terminée d’après la progression.
 *
 * @param array<int, true> $completedLessonIds
 * @param array<int, true> $passedQuizIds
 */
function training_lms_sequence_step_done(array $step, array $completedLessonIds, array $passedQuizIds): bool
{
    $kind = (string) ($step['kind'] ?? '');
    if ($kind === 'preamble') {
        // Le préambule est « passé » dès qu’au moins une leçon est terminée ou démarrée via continue.
        return $completedLessonIds !== [];
    }
    if ($kind === 'lesson') {
        $lid = (int) ($step['lesson']['id'] ?? 0);

        return $lid > 0 && isset($completedLessonIds[$lid]);
    }
    if ($kind === 'quiz') {
        $qid = (int) ($step['quiz']['id'] ?? 0);

        return $qid > 0 && isset($passedQuizIds[$qid]);
    }

    return false;
}

