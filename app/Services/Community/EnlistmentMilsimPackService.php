<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Request;

/**
 * Pack d’affichage du formulaire de recrutement MilSim (/enlistment).
 * Données stockées dans settings.community.enlistment_milsim (partiellement surchargé).
 */
final class EnlistmentMilsimPackService
{
    /** Marque barre navigation : fixe plateforme (non personnalisable par communauté). */
    public const PLATFORM_NAV_BRAND = 'Athena';

    /** Nombre max de créneaux de disponibilité par communauté. */
    public const MAX_AVAILABILITY_SLOTS = 12;

    /** @return list<string> */
    public static function milsimFieldKeys(): array
    {
        return array_keys(self::defaultFieldLabels());
    }

    /**
     * Catalogue de créneaux proposés à la création / en back-office (cases à cocher).
     * Clés stables pour la persistance ; libellés français affichés aux candidats.
     *
     * @return array<string, string> id => label
     */
    public static function suggestedAvailabilitySlots(): array
    {
        return [
            'wed_evening' => 'Mercredi soir',
            'thu_evening' => 'Jeudi soir',
            'fri_evening' => 'Vendredi soir',
            'sat_afternoon' => 'Samedi après-midi',
            'sat_evening' => 'Samedi soir',
            'sun_afternoon' => 'Dimanche après-midi',
            'sun_evening' => 'Dimanche soir',
            'weekday_evening' => 'Soirs en semaine',
            'weekend' => 'Week-end',
        ];
    }

    /**
     * Lignes d’ambiance du rail « Avancement » (affichage uniquement).
     *
     * @param mixed $raw
     * @return list<array{label: string, value: string}>
     */
    public static function normalizeRailMetaRows(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (count($out) >= 8) {
                break;
            }
            if (!is_array($row)) {
                continue;
            }
            $label = isset($row['label']) && is_string($row['label']) ? trim($row['label']) : '';
            $value = isset($row['value']) && is_string($row['value']) ? trim($row['value']) : '';
            if ($label === '' || $value === '') {
                continue;
            }
            if (mb_strlen($label) > 48) {
                $label = mb_substr($label, 0, 48);
            }
            if (mb_strlen($value) > 64) {
                $value = mb_substr($value, 0, 64);
            }
            $out[] = ['label' => $label, 'value' => $value];
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<array{id: string, label: string}>
     */
    public static function normalizeAvailabilitySlots(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $suggested = self::suggestedAvailabilitySlots();
        $out = [];
        $seenLabels = [];
        foreach ($raw as $row) {
            if (count($out) >= self::MAX_AVAILABILITY_SLOTS) {
                break;
            }
            $id = '';
            $label = '';
            if (is_string($row)) {
                $label = trim($row);
            } elseif (is_array($row)) {
                $id = isset($row['id']) && is_string($row['id']) ? trim($row['id']) : '';
                $label = isset($row['label']) && is_string($row['label']) ? trim($row['label']) : '';
                if ($label === '' && $id !== '' && isset($suggested[$id])) {
                    $label = $suggested[$id];
                }
            }
            if ($label === '') {
                continue;
            }
            if (mb_strlen($label) > 80) {
                $label = mb_substr($label, 0, 80);
            }
            $labelKey = mb_strtolower($label);
            if (isset($seenLabels[$labelKey])) {
                continue;
            }
            $seenLabels[$labelKey] = true;
            if ($id === '' || !preg_match('/^[a-z][a-z0-9_]{0,40}$/', $id)) {
                $id = 'slot_' . substr(sha1($labelKey), 0, 10);
            }
            $out[] = ['id' => $id, 'label' => $label];
        }

        return $out;
    }

    /**
     * Construit la liste à partir des cases catalogue + libellés personnalisés (UI admin / wizard).
     *
     * @param mixed $selectedIds
     * @param mixed $customLabels
     * @return list<array{id: string, label: string}>
     */
    public static function availabilitySlotsFromSelection(mixed $selectedIds, mixed $customLabels = null): array
    {
        $suggested = self::suggestedAvailabilitySlots();
        $rows = [];
        if (is_array($selectedIds)) {
            foreach ($selectedIds as $id) {
                $key = is_string($id) ? trim($id) : '';
                if ($key !== '' && isset($suggested[$key])) {
                    $rows[] = ['id' => $key, 'label' => $suggested[$key]];
                }
            }
        }
        if (is_array($customLabels)) {
            $i = 0;
            foreach ($customLabels as $lab) {
                if (!is_string($lab)) {
                    continue;
                }
                $label = trim($lab);
                if ($label === '') {
                    continue;
                }
                $i++;
                $rows[] = ['id' => 'custom_' . $i, 'label' => $label];
            }
        }

        return self::normalizeAvailabilitySlots($rows);
    }

    /**
     * Libellé de question d’engagement dérivé des créneaux (si non personnalisé).
     *
     * @param list<array{id: string, label: string}> $slots
     */
    public static function defaultAvailabilityQuestion(array $slots): string
    {
        if ($slots === []) {
            return 'Je suis disponible sur les créneaux principaux de la communauté';
        }
        $labels = array_map(static fn (array $s): string => (string) ($s['label'] ?? ''), $slots);
        $labels = array_values(array_filter($labels, static fn (string $l): bool => $l !== ''));
        if ($labels === []) {
            return 'Je suis disponible sur les créneaux principaux de la communauté';
        }
        if (count($labels) === 1) {
            return 'Je suis disponible : ' . $labels[0];
        }
        $last = array_pop($labels);

        return 'Je suis disponible : ' . implode(', ', $labels) . ' et ' . $last;
    }

    /**
     * Filtre les créneaux cochés par le candidat contre le catalogue du tenant.
     *
     * @param list<array{id: string, label: string}> $configured
     * @param mixed $posted
     * @return list<string> libellés retenus
     */
    public static function filterCandidateSlotSelection(array $configured, mixed $posted): array
    {
        if ($configured === [] || !is_array($posted)) {
            return [];
        }
        $byId = [];
        $byLabel = [];
        foreach ($configured as $slot) {
            $id = (string) ($slot['id'] ?? '');
            $label = (string) ($slot['label'] ?? '');
            if ($id !== '') {
                $byId[$id] = $label;
            }
            if ($label !== '') {
                $byLabel[mb_strtolower($label)] = $label;
            }
        }
        $out = [];
        $seen = [];
        foreach ($posted as $raw) {
            if (!is_string($raw)) {
                continue;
            }
            $v = trim($raw);
            if ($v === '') {
                continue;
            }
            $label = $byId[$v] ?? ($byLabel[mb_strtolower($v)] ?? null);
            if ($label === null || $label === '') {
                continue;
            }
            $key = mb_strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $label;
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, placeholder: string, widget: string, options: list<string>}>
     */
    public static function defaultFieldLabels(): array
    {
        return [
            'full_name' => ['label' => 'Nom et prénom (dossier)', 'placeholder' => 'ex. Jonathan King', 'widget' => 'text', 'options' => []],
            'legal_full_name' => ['label' => 'Nom réel pour le contact (si personnage)', 'placeholder' => 'Nom pour être recontacté — optionnel si déjà indiqué ailleurs', 'widget' => 'text', 'options' => []],
            'age' => ['label' => 'Âge', 'placeholder' => 'Âge minimum requis par la communauté', 'widget' => 'text', 'options' => []],
            'timezone' => ['label' => 'Fuseau horaire', 'placeholder' => 'Choisissez votre pays / ville', 'widget' => 'select', 'options' => []],
            'weekly_availability' => ['label' => 'Disponibilités dans la semaine', 'placeholder' => 'Jours et créneaux habituels', 'widget' => 'text', 'options' => []],
            'email' => ['label' => 'Adresse e-mail', 'placeholder' => 'email@exemple.fr', 'widget' => 'text', 'options' => []],
            'callsign' => ['label' => 'Indicatif (optionnel)', 'placeholder' => 'ex. Ghost-2-1', 'widget' => 'text', 'options' => []],
            'system_config' => [
                'label' => 'Niveau de votre PC',
                'placeholder' => '',
                'widget' => 'select',
                'options' => ['Excellent', 'Bon', 'Correct', 'Limité', 'Insuffisant'],
            ],
            'microphone_quality' => ['label' => 'Disposez-vous d’un micro de bonne qualité ?', 'placeholder' => '', 'widget' => 'yesno', 'options' => ['Oui', 'Non']],
            'past_milsim_experience' => ['label' => 'Expériences milsim passées', 'placeholder' => 'Unités, rôles, durées…', 'widget' => 'textarea', 'options' => []],
            'ace_acre_level' => ['label' => 'Niveau ACE / ACRE', 'placeholder' => '', 'widget' => 'select', 'options' => ['Aucune', 'Basique', 'Expérimenté', 'Avancé']],
            'motivation_why_join' => ['label' => 'Pourquoi rejoindre cette communauté ?', 'placeholder' => 'Motivation, engagement, attentes…', 'widget' => 'textarea', 'options' => []],
            'motivation_accountability' => ['label' => 'Que signifie pour vous la responsabilité individuelle dans une unité ?', 'placeholder' => 'Votre lecture de la responsabilité individuelle…', 'widget' => 'textarea', 'options' => []],
        ];
    }

    /**
     * Section Motivation du dossier (titre, intro, questions).
     * Clés internes why_join / accountability mappent les colonnes existantes.
     *
     * @return array{
     *   title: string,
     *   intro: string,
     *   why_join: array{enabled: bool, required: bool, label: string, placeholder: string, help: string},
     *   accountability: array{enabled: bool, required: bool, label: string, placeholder: string, help: string}
     * }
     */
    public static function defaultMotivationSection(): array
    {
        $fields = self::defaultFieldLabels();

        return [
            'title' => 'Motivation',
            'intro' => '',
            'why_join' => [
                'enabled' => true,
                'required' => false,
                'label' => (string) ($fields['motivation_why_join']['label'] ?? 'Pourquoi rejoindre cette communauté ?'),
                'placeholder' => (string) ($fields['motivation_why_join']['placeholder'] ?? ''),
                'help' => '',
            ],
            'accountability' => [
                'enabled' => true,
                'required' => false,
                'label' => (string) ($fields['motivation_accountability']['label'] ?? 'Que signifie pour vous la responsabilité individuelle dans une unité ?'),
                'placeholder' => (string) ($fields['motivation_accountability']['placeholder'] ?? ''),
                'help' => '',
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $raw
     * @return array{
     *   title: string,
     *   intro: string,
     *   why_join: array{enabled: bool, required: bool, label: string, placeholder: string, help: string},
     *   accountability: array{enabled: bool, required: bool, label: string, placeholder: string, help: string}
     * }
     */
    public static function normalizeMotivationSection(?array $raw, ?array $fieldsFallback = null): array
    {
        $defaults = self::defaultMotivationSection();
        if ($fieldsFallback !== null) {
            if (is_array($fieldsFallback['motivation_why_join'] ?? null)) {
                $f = $fieldsFallback['motivation_why_join'];
                if (isset($f['label']) && is_string($f['label']) && trim($f['label']) !== '') {
                    $defaults['why_join']['label'] = trim($f['label']);
                }
                if (isset($f['placeholder']) && is_string($f['placeholder'])) {
                    $defaults['why_join']['placeholder'] = $f['placeholder'];
                }
            }
            if (is_array($fieldsFallback['motivation_accountability'] ?? null)) {
                $f = $fieldsFallback['motivation_accountability'];
                if (isset($f['label']) && is_string($f['label']) && trim($f['label']) !== '') {
                    $defaults['accountability']['label'] = trim($f['label']);
                }
                if (isset($f['placeholder']) && is_string($f['placeholder'])) {
                    $defaults['accountability']['placeholder'] = $f['placeholder'];
                }
            }
        }

        if ($raw === null || $raw === []) {
            return $defaults;
        }

        $out = $defaults;
        if (isset($raw['title']) && is_string($raw['title']) && trim($raw['title']) !== '') {
            $out['title'] = trim($raw['title']);
        }
        if (isset($raw['intro']) && is_string($raw['intro'])) {
            $out['intro'] = trim($raw['intro']);
        }

        foreach (['why_join', 'accountability'] as $qk) {
            $src = is_array($raw[$qk] ?? null) ? $raw[$qk] : [];
            $base = $defaults[$qk];
            $enabled = array_key_exists('enabled', $src)
                ? filter_var($src['enabled'], FILTER_VALIDATE_BOOLEAN)
                : $base['enabled'];
            $required = array_key_exists('required', $src)
                ? filter_var($src['required'], FILTER_VALIDATE_BOOLEAN)
                : $base['required'];
            $label = isset($src['label']) && is_string($src['label']) && trim($src['label']) !== ''
                ? trim($src['label']) : $base['label'];
            $placeholder = isset($src['placeholder']) && is_string($src['placeholder'])
                ? $src['placeholder'] : $base['placeholder'];
            $help = isset($src['help']) && is_string($src['help']) ? trim($src['help']) : $base['help'];
            $out[$qk] = [
                'enabled' => $enabled,
                'required' => $required,
                'label' => $label,
                'placeholder' => $placeholder,
                'help' => $help,
            ];
        }

        // Question principale toujours affichée (candidature courte / compacte).
        $out['why_join']['enabled'] = true;

        return $out;
    }

    /**
     * Applique les libellés Motivation sur fields[] (compat rendu widget existant).
     *
     * @param array<string, mixed> $pack
     * @return array<string, mixed>
     */
    public static function syncMotivationIntoFields(array $pack): array
    {
        $mot = self::normalizeMotivationSection(
            is_array($pack['motivation'] ?? null) ? $pack['motivation'] : null,
            is_array($pack['fields'] ?? null) ? $pack['fields'] : null
        );
        $pack['motivation'] = $mot;
        if (trim($mot['title']) !== '') {
            $pack['section_3'] = $mot['title'];
        }
        if (! is_array($pack['fields'] ?? null)) {
            $pack['fields'] = self::defaultFieldLabels();
        }
        $def = self::defaultFieldLabels();
        foreach ([
            'motivation_why_join' => 'why_join',
            'motivation_accountability' => 'accountability',
        ] as $fk => $qk) {
            $base = $def[$fk] ?? ['label' => '', 'placeholder' => '', 'widget' => 'textarea', 'options' => []];
            $prev = is_array($pack['fields'][$fk] ?? null) ? $pack['fields'][$fk] : $base;
            $pack['fields'][$fk] = self::normalizeFieldDef($base, [
                'label' => $mot[$qk]['label'],
                'placeholder' => $mot[$qk]['placeholder'],
                'widget' => $prev['widget'] ?? 'textarea',
                'options' => $prev['options'] ?? [],
            ]);
        }

        return $pack;
    }

    /**
     * @param array{label?: string, placeholder?: string, widget?: string, options?: mixed} $base
     * @param array<string, mixed> $ov
     * @return array{label: string, placeholder: string, widget: string, options: list<string>}
     */
    public static function normalizeFieldDef(array $base, array $ov): array
    {
        $label = isset($ov['label']) && is_string($ov['label']) && trim($ov['label']) !== ''
            ? trim($ov['label']) : (string) ($base['label'] ?? '');
        $placeholder = isset($ov['placeholder']) && is_string($ov['placeholder']) ? $ov['placeholder'] : (string) ($base['placeholder'] ?? '');
        $allowedW = ['text', 'textarea', 'select', 'yesno'];
        $widget = isset($ov['widget']) && is_string($ov['widget']) && in_array($ov['widget'], $allowedW, true)
            ? $ov['widget'] : (string) ($base['widget'] ?? 'text');
        $defOpts = is_array($base['options'] ?? null) ? $base['options'] : [];
        $options = $defOpts;
        if (isset($ov['options'])) {
            if (is_array($ov['options'])) {
                $options = array_values(array_filter(array_map(static fn ($x) => is_string($x) ? trim($x) : '', $ov['options'])));
            } elseif (is_string($ov['options']) && trim($ov['options']) !== '') {
                $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $ov['options'])));
                $options = array_values($lines);
            }
        }
        if ($widget === 'yesno' && $options === []) {
            $options = ['Oui', 'Non'];
        }
        return ['label' => $label, 'placeholder' => $placeholder, 'widget' => $widget, 'options' => $options];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultPack(): array
    {
        return [
            'logo_letter' => 'A',
            'portal_title' => 'Recrutement',
            'portal_subtitle' => 'Athena — candidature communauté',
            'preamble_title' => 'Avant de commencer',
            'preamble_lead' => 'Ce dossier permet à l’équipe de recrutement d’évaluer votre candidature. Prenez le temps de répondre avec soin : la qualité des réponses compte autant que la motivation.',
            'preamble_status_lines' => [
                'Vos réponses sont lues par l’équipe de la communauté.',
                'Vous pourrez suivre votre dossier après envoi.',
                'Les champs marqués comme obligatoires doivent être remplis.',
            ],
            'preamble_cta' => 'Commencer ma candidature',
            'preamble_footer' => 'En poursuivant, vous acceptez le traitement de vos données pour le recrutement.',
            'nav_brand' => self::PLATFORM_NAV_BRAND,
            'session_block_title' => 'Avancement',
            'ref_label' => 'Réf. dossier',
            'security_label' => 'Canal sécurisé',
            'progress_prefix' => 'Saisie :',
            /** Métadonnées d’ambiance (rail Avancement) — purement affichage, non fonctionnelles. */
            'rail_classification' => 'DIFFUSION RESTREINTE',
            'rail_meta_rows' => [
                ['label' => 'Bureau émetteur', 'value' => 'S1 — RECRUTEMENT'],
                ['label' => 'Circuit', 'value' => 'PORTAIL → CELLULE RH'],
                ['label' => 'Priorité', 'value' => 'ROUTINE'],
                ['label' => 'Statut saisie', 'value' => 'EN COURS'],
                ['label' => 'Imprimé', 'value' => 'F-CAND / ATHENA'],
            ],
            'roe_title' => 'Ce que nous attendons',
            'roe_items' => [
                'Réponses détaillées et honnêtes.',
                'Un micro de bonne qualité pour les opérations vocales.',
                'Disponibilité sur les créneaux principaux de la communauté.',
                'Patience après l’envoi : l’équipe vous recontactera si besoin.',
            ],
            'watermark' => 'ATHENA',
            'doc_control' => 'Dossier de candidature',
            'queue_label' => 'Recrutement ouvert',
            'candidate_prefix' => 'Rejoindre',
            'classified_badge' => 'Candidature',
            'op_note_title' => 'Comment ça se passe',
            'op_col1' => 'Chaque dossier est examiné par l’équipe de recrutement de la communauté.',
            'op_ai_warning' => 'Rédigez vous-même vos réponses : l’assistance par IA n’est pas autorisée.',
            'op_col2' => 'Si votre candidature est retenue, vous serez contacté directement.',
            'archive_note' => 'Un dossier incomplet ou généré automatiquement peut être classé sans suite.',
            'section_0' => 'Comment candidater',
            'section_1' => 'Identité et contact',
            'section_2' => 'Matériel et expérience',
            'section_3' => 'Motivation',
            'section_4' => 'Engagement',
            'fields' => self::defaultFieldLabels(),
            'motivation' => self::defaultMotivationSection(),
            'commitment_q13' => 'Je comprends le temps et l’investissement demandés',
            /** Créneaux proposés aux candidats (vide = saisie libre côté formulaire). Configurés par le tenant. */
            'availability_slots' => [],
            'availability_q15' => self::defaultAvailabilityQuestion([]),
            'ai_checkbox' => 'Je confirme avoir rédigé ce dossier sans assistance par IA',
            'submit_button' => 'Envoyer ma candidature',
            'submit_footer' => 'Vous recevrez une confirmation après l’envoi.',
            /** Questions supplémentaires créées en back-office (listes déroulantes, etc.). */
            'custom_questions' => [],
            /** Règles de refus automatique : réponse X → dossier refusé. */
            'auto_refuse_rules' => [],
            /**
             * Avertissements recrutement (communauté) — une ligne = un paragraphe.
             * Les mentions plateforme (Athena ≠ décisions d’unité, analyse des formulations) sont gérées côté vue.
             */
            'disclaimer_recruitment_lines' => self::defaultRecruitmentDisclaimerLines(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultRecruitmentDisclaimerLines(): array
    {
        return [
            'Ce n’est pas une inscription automatique.',
            'Toute candidature est examinée formellement par la cellule de recrutement et le commandement de l’unité.',
            'Toute assistance par intelligence artificielle dans la rédaction de ce dossier est strictement interdite et entraîne une disqualification immédiate.',
            'Si votre dossier est retenu pour la suite, vous serez contacté directement. Merci de ne pas écrire au staff ni au commandement pour demander l’état d’avancement.',
            'Avant d’envoyer votre dossier, ouvrez un ticket de recrutement officiel sur Discord. Les candidatures sans ticket actif ne seront pas examinées.',
        ];
    }

    /**
     * @param mixed $raw
     * @return list<array{id: string, label: string, widget: string, options: list<string>, required: bool, section: string}>
     */
    public static function normalizeCustomQuestions(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $allowedW = ['text', 'textarea', 'select', 'yesno'];
        $allowedSec = ['identity', 'gear', 'motivation', 'commitment'];
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '' || !preg_match('/^[a-zA-Z0-9_\-]{2,64}$/', $id)) {
                $id = 'cq_' . substr(bin2hex(random_bytes(6)), 0, 12);
            }
            $widget = (string) ($row['widget'] ?? 'select');
            if (!in_array($widget, $allowedW, true)) {
                $widget = 'select';
            }
            $options = [];
            if (isset($row['options'])) {
                if (is_array($row['options'])) {
                    $options = array_values(array_filter(array_map(
                        static fn ($x) => is_string($x) ? trim($x) : '',
                        $row['options']
                    )));
                } elseif (is_string($row['options'])) {
                    $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $row['options']) ?: [])));
                }
            }
            if ($widget === 'yesno' && $options === []) {
                $options = ['Oui', 'Non'];
            }
            if ($widget === 'select' && $options === []) {
                continue;
            }
            $section = (string) ($row['section'] ?? 'commitment');
            if (!in_array($section, $allowedSec, true)) {
                $section = 'commitment';
            }
            $out[] = [
                'id' => $id,
                'label' => mb_substr($label, 0, 240),
                'widget' => $widget,
                'options' => array_slice($options, 0, 40),
                'required' => !empty($row['required']),
                'section' => $section,
            ];
            if (count($out) >= 30) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<array{field_key: string, match_value: string, candidate_message: string, staff_note: string}>
     */
    public static function normalizeAutoRefuseRules(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $fieldKey = trim((string) ($row['field_key'] ?? ''));
            $matchValue = trim((string) ($row['match_value'] ?? ''));
            if ($fieldKey === '' || $matchValue === '') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_\-]{2,64}$/', $fieldKey)) {
                continue;
            }
            $candidateMsg = trim((string) ($row['candidate_message'] ?? ''));
            if ($candidateMsg === '') {
                $candidateMsg = 'Votre candidature ne peut pas être retenue au regard des réponses fournies.';
            }
            $staffNote = trim((string) ($row['staff_note'] ?? ''));
            if ($staffNote === '') {
                $staffNote = 'Refus automatique : réponse « ' . $matchValue . ' » sur le champ « ' . $fieldKey . ' ».';
            }
            $out[] = [
                'field_key' => $fieldKey,
                'match_value' => mb_substr($matchValue, 0, 200),
                'candidate_message' => mb_substr($candidateMsg, 0, 600),
                'staff_note' => mb_substr($staffNote, 0, 600),
            ];
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * Évalue les règles de refus automatique contre les réponses du formulaire.
     *
     * @param array<string, mixed> $pack
     * @param array<string, string> $answers clé = field_key ou id question perso
     * @return array{field_key: string, match_value: string, candidate_message: string, staff_note: string}|null
     */
    public static function evaluateAutoRefuse(array $pack, array $answers): ?array
    {
        $rules = self::normalizeAutoRefuseRules($pack['auto_refuse_rules'] ?? []);
        foreach ($rules as $rule) {
            $key = $rule['field_key'];
            $got = trim((string) ($answers[$key] ?? ''));
            if ($got === '') {
                continue;
            }
            if (mb_strtolower($got) === mb_strtolower($rule['match_value'])) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Libellés FR des champs pouvant servir de cible à un refus automatique.
     *
     * @param array<string, mixed> $pack
     * @return array<string, string> field_key => label
     */
    public static function refuseTargetFieldLabels(array $pack): array
    {
        $out = [];
        $fields = is_array($pack['fields'] ?? null) ? $pack['fields'] : self::defaultFieldLabels();
        foreach (['microphone_quality', 'system_config', 'ace_acre_level', 'commitment_effort', 'availability_wed_sat'] as $fk) {
            $lab = trim((string) ($fields[$fk]['label'] ?? ''));
            if ($fk === 'commitment_effort') {
                $lab = trim((string) ($pack['commitment_q13'] ?? '')) ?: 'Engagement (temps et investissement)';
            } elseif ($fk === 'availability_wed_sat') {
                $lab = trim((string) ($pack['availability_q15'] ?? '')) ?: 'Confirmation des créneaux';
            }
            if ($lab === '') {
                $lab = $fk;
            }
            $out[$fk] = $lab;
        }
        foreach (self::normalizeCustomQuestions($pack['custom_questions'] ?? []) as $q) {
            if (in_array($q['widget'], ['select', 'yesno'], true)) {
                $out[$q['id']] = $q['label'];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $community bloc settings.community
     * @return array<string, mixed>
     */
    public static function forCommunity(array $community): array
    {
        $defaults = self::defaultPack();
        $raw = $community['enlistment_milsim'] ?? null;
        if (! is_array($raw)) {
            $defaults['nav_brand'] = self::PLATFORM_NAV_BRAND;

            return self::syncMotivationIntoFields($defaults);
        }

        $out = $defaults;
        foreach ([
            'logo_letter', 'portal_title', 'portal_subtitle', 'preamble_title', 'preamble_lead',
            'preamble_cta', 'preamble_footer', 'session_block_title', 'ref_label',
            'security_label', 'progress_prefix', 'roe_title', 'watermark', 'doc_control',
            'queue_label', 'candidate_prefix', 'classified_badge', 'op_note_title', 'op_col1',
            'op_ai_warning', 'op_col2', 'archive_note', 'section_0', 'section_1', 'section_2', 'section_3', 'section_4',
            'commitment_q13', 'availability_q15', 'ai_checkbox', 'submit_button', 'submit_footer',
            'rail_classification',
        ] as $k) {
            if (isset($raw[$k]) && is_string($raw[$k]) && trim($raw[$k]) !== '') {
                $out[$k] = $raw[$k];
            }
        }
        $out['nav_brand'] = self::PLATFORM_NAV_BRAND;

        if (array_key_exists('rail_meta_rows', $raw) && is_array($raw['rail_meta_rows'])) {
            $rows = self::normalizeRailMetaRows($raw['rail_meta_rows']);
            if ($rows !== []) {
                $out['rail_meta_rows'] = $rows;
            }
        }

        // Clé absente = non configuré (état vide). Clé présente (même []) = choix explicite du tenant.
        if (array_key_exists('availability_slots', $raw)) {
            $out['availability_slots'] = self::normalizeAvailabilitySlots($raw['availability_slots']);
        } else {
            $out['availability_slots'] = [];
        }

        if ($out['availability_slots'] !== []
            && (!isset($raw['availability_q15']) || !is_string($raw['availability_q15']) || trim($raw['availability_q15']) === '')) {
            $out['availability_q15'] = self::defaultAvailabilityQuestion($out['availability_slots']);
        }

        if (! empty($raw['preamble_status_lines']) && is_array($raw['preamble_status_lines'])) {
            $lines = array_values(array_filter(array_map(static fn ($s) => is_string($s) ? trim($s) : '', $raw['preamble_status_lines'])));
            if ($lines !== []) {
                $out['preamble_status_lines'] = $lines;
            }
        }

        if (! empty($raw['roe_items']) && is_array($raw['roe_items'])) {
            $roe = array_values(array_filter(array_map(static fn ($s) => is_string($s) ? trim($s) : '', $raw['roe_items'])));
            if ($roe !== []) {
                $out['roe_items'] = array_slice($roe, 0, 12);
            }
        }

        $defFields = self::defaultFieldLabels();
        $out['fields'] = $defFields;
        if (! empty($raw['fields']) && is_array($raw['fields'])) {
            foreach ($defFields as $fk => $fv) {
                if (! isset($raw['fields'][$fk]) || ! is_array($raw['fields'][$fk])) {
                    continue;
                }
                $out['fields'][$fk] = self::normalizeFieldDef($fv, $raw['fields'][$fk]);
            }
        } else {
            foreach ($defFields as $fk => $fv) {
                $out['fields'][$fk] = self::normalizeFieldDef($fv, $fv);
            }
        }

        // Niveau PC : toujours une liste fermée (pas de saisie libre), même si un vieux pack disait « texte ».
        $pcBase = $defFields['system_config'];
        $pcOv = is_array($raw['fields']['system_config'] ?? null) ? $raw['fields']['system_config'] : [];
        $pcMerged = self::normalizeFieldDef($pcBase, $pcOv);
        $pcMerged['widget'] = 'select';
        if ($pcMerged['options'] === []) {
            $pcMerged['options'] = $pcBase['options'];
        }
        $out['fields']['system_config'] = $pcMerged;

        $motRaw = is_array($raw['motivation'] ?? null) ? $raw['motivation'] : null;
        $seed = $motRaw;
        if ($seed === null) {
            $seed = [];
            if (isset($out['section_3']) && is_string($out['section_3']) && trim($out['section_3']) !== '') {
                $seed['title'] = trim($out['section_3']);
            }
        }
        $out['motivation'] = self::normalizeMotivationSection($seed === [] ? null : $seed, $out['fields']);
        if ($motRaw !== null) {
            $out = self::syncMotivationIntoFields($out);
        } elseif (trim((string) ($out['motivation']['title'] ?? '')) !== '') {
            $out['section_3'] = $out['motivation']['title'];
        }

        $out['custom_questions'] = self::normalizeCustomQuestions($raw['custom_questions'] ?? []);
        $out['auto_refuse_rules'] = self::normalizeAutoRefuseRules($raw['auto_refuse_rules'] ?? []);
        if (array_key_exists('disclaimer_recruitment_lines', $raw) && is_array($raw['disclaimer_recruitment_lines'])) {
            $lines = array_values(array_filter(array_map(
                static fn ($s) => is_string($s) ? trim($s) : '',
                $raw['disclaimer_recruitment_lines']
            )));
            $out['disclaimer_recruitment_lines'] = $lines !== []
                ? array_slice($lines, 0, 12)
                : self::defaultRecruitmentDisclaimerLines();
        } else {
            $out['disclaimer_recruitment_lines'] = self::defaultRecruitmentDisclaimerLines();
        }

        return $out;
    }

    /**
     * Fragment settings.community.enlistment_milsim depuis l’assistant (wizard_milsim[...]).
     *
     * @param array<string, mixed>|null $wm
     * @return array<string, mixed>|null
     */
    public static function mergeWizardMilsimInput(?array $wm): ?array
    {
        if ($wm === null || ! is_array($wm)) {
            return null;
        }
        $out = [];
        foreach ([
            'portal_title', 'portal_subtitle', 'preamble_title', 'preamble_lead', 'preamble_cta', 'preamble_footer',
            'watermark', 'roe_title', 'submit_button', 'logo_letter',
        ] as $k) {
            if (isset($wm[$k]) && is_string($wm[$k]) && trim($wm[$k]) !== '') {
                $out[$k] = trim($wm[$k]);
            }
        }
        if (! empty($wm['preamble_status']) && is_string($wm['preamble_status'])) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $wm['preamble_status']))));
            if ($lines !== []) {
                $out['preamble_status_lines'] = array_slice($lines, 0, 10);
            }
        }
        if (! empty($wm['roe_lines']) && is_string($wm['roe_lines'])) {
            $roe = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $wm['roe_lines']))));
            if ($roe !== []) {
                $out['roe_items'] = array_slice($roe, 0, 12);
            }
        }
        $defF = self::defaultFieldLabels();
        if (! empty($wm['fields']) && is_array($wm['fields'])) {
            $fields = [];
            foreach ($defF as $fk => $fv) {
                if (! isset($wm['fields'][$fk]) || ! is_array($wm['fields'][$fk])) {
                    continue;
                }
                $fields[$fk] = self::normalizeFieldDef($fv, $wm['fields'][$fk]);
            }
            if ($fields !== []) {
                $out['fields'] = $fields;
            }
        }

        $slots = self::availabilitySlotsFromSelection(
            $wm['availability_slot_ids'] ?? null,
            $wm['availability_slot_custom'] ?? null
        );
        if ($slots === [] && isset($wm['availability_slots'])) {
            $slots = self::normalizeAvailabilitySlots($wm['availability_slots']);
        }
        // Toujours enregistrer la clé si le wizard a soumis une sélection (même vide via flag).
        if (isset($wm['availability_slot_ids']) || isset($wm['availability_slot_custom']) || isset($wm['availability_slots']) || isset($wm['availability_slots_configured'])) {
            $out['availability_slots'] = $slots;
            if ($slots !== [] && (!isset($wm['availability_q15']) || !is_string($wm['availability_q15']) || trim((string) $wm['availability_q15']) === '')) {
                $out['availability_q15'] = self::defaultAvailabilityQuestion($slots);
            }
        }

        if (isset($wm['motivation']) && is_array($wm['motivation'])) {
            $mot = self::normalizeMotivationSection($wm['motivation'], $out['fields'] ?? null);
            $out['motivation'] = $mot;
            $synced = self::syncMotivationIntoFields(array_merge(['fields' => $out['fields'] ?? self::defaultFieldLabels()], ['motivation' => $mot]));
            $out['fields'] = $synced['fields'];
            $out['section_3'] = $synced['section_3'];
            $out['motivation'] = $synced['motivation'];
        }

        $out['nav_brand'] = self::PLATFORM_NAV_BRAND;

        return $out === [] ? null : $out;
    }

    /**
     * Extrait la section Motivation depuis un tableau POST (admin ou wizard).
     *
     * @param array<string, mixed>|null $src
     * @return array<string, mixed>|null
     */
    public static function motivationFromPostedArray(?array $src): ?array
    {
        if ($src === null || $src === []) {
            return null;
        }
        $hasSignal = isset($src['title']) || isset($src['intro'])
            || isset($src['why_join']) || isset($src['accountability']);
        if (!$hasSignal) {
            return null;
        }

        return self::normalizeMotivationSection($src);
    }

    /**
     * Fusion partielle depuis « Paramètres d’inscription » :
     * créneaux de disponibilité + section Motivation, sans écraser le reste du pack.
     *
     * @param array<string, mixed> $existingPack
     * @return array<string, mixed>
     */
    public static function mergePartialFromCommunitySettingsRequest(Request $request, array $existingPack): array
    {
        $out = $existingPack;
        $configured = (string) $request->input('em_settings_enlistment_partial', '0') === '1';
        if (!$configured) {
            return $out;
        }

        $c = static function (string $s, int $max): string {
            if (mb_strlen($s) <= $max) {
                return $s;
            }

            return mb_substr($s, 0, $max);
        };

        $slots = self::availabilitySlotsFromSelection(
            $request->input('em_availability_slot_ids'),
            $request->input('em_availability_slot_custom')
        );
        $out['availability_slots'] = $slots;

        $q15 = $c(trim((string) $request->input('em_availability_q15', '')), 400);
        if ($q15 === '' && $slots !== []) {
            $q15 = self::defaultAvailabilityQuestion($slots);
        }
        $out['availability_q15'] = $q15;

        $motPosted = $request->input('em_motivation');
        $mot = self::motivationFromPostedArray(is_array($motPosted) ? $motPosted : null);
        if ($mot !== null) {
            $mot['title'] = $c((string) $mot['title'], 200);
            $mot['intro'] = $c((string) $mot['intro'], 2000);
            foreach (['why_join', 'accountability'] as $qk) {
                $mot[$qk]['label'] = $c((string) $mot[$qk]['label'], 240);
                $mot[$qk]['placeholder'] = $c((string) $mot[$qk]['placeholder'], 400);
                $mot[$qk]['help'] = $c((string) $mot[$qk]['help'], 600);
            }
            $out['motivation'] = $mot;
            $synced = self::syncMotivationIntoFields(array_merge($out, ['motivation' => $mot]));
            $out['fields'] = $synced['fields'];
            $out['section_3'] = $synced['section_3'];
            $out['motivation'] = $synced['motivation'];
        }

        return $out;
    }

    /**
     * Saisie back-office « Fiche registre » — enregistre le pack MilSim complet.
     *
     * @return array<string, mixed>
     */
    public static function buildFromRequest(Request $request): array
    {
        $c = static function (string $s, int $max): string {
            if (mb_strlen($s) <= $max) {
                return $s;
            }

            return mb_substr($s, 0, $max);
        };

        $roeRaw = (string) $request->input('em_roe_lines', '');
        $roeItems = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $roeRaw))));
        $roeItems = array_slice(array_map(static fn (string $line) => $c($line, 600), $roeItems), 0, 12);

        $statusRaw = (string) $request->input('em_preamble_status', '');
        $statusLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $statusRaw))));
        $statusLines = array_slice(array_map(static fn (string $line) => $c($line, 400), $statusLines), 0, 10);

        $fields = self::defaultFieldLabels();
        $emFld = $request->input('em_fld');
        if (is_array($emFld)) {
            foreach ($fields as $fk => $fv) {
                if (! isset($emFld[$fk]) || ! is_array($emFld[$fk])) {
                    continue;
                }
                $row = $emFld[$fk];
                $norm = self::normalizeFieldDef($fv, [
                    'label' => $row['label'] ?? '',
                    'placeholder' => $row['placeholder'] ?? '',
                    'widget' => $row['widget'] ?? '',
                    'options' => $row['options'] ?? '',
                ]);
                $fields[$fk] = $norm;
            }
        }

        $jsonFields = trim((string) $request->input('em_fields_json', ''));
        if ($jsonFields !== '') {
            $decoded = json_decode($jsonFields, true);
            if (is_array($decoded)) {
                foreach ($fields as $fk => $fv) {
                    if (! isset($decoded[$fk]) || ! is_array($decoded[$fk])) {
                        continue;
                    }
                    $row = $decoded[$fk];
                    $fields[$fk] = self::normalizeFieldDef($fv, $row);
                }
            }
        }

        $pack = [
            'logo_letter' => $c(trim((string) $request->input('em_logo_letter', 'F')), 3),
            'portal_title' => $c((string) $request->input('em_portal_title', ''), 200),
            'portal_subtitle' => $c((string) $request->input('em_portal_subtitle', ''), 400),
            'preamble_title' => $c((string) $request->input('em_preamble_title', ''), 200),
            'preamble_lead' => $c((string) $request->input('em_preamble_lead', ''), 2000),
            'preamble_status_lines' => $statusLines,
            'preamble_cta' => $c((string) $request->input('em_preamble_cta', ''), 120),
            'preamble_footer' => $c((string) $request->input('em_preamble_footer', ''), 600),
            'nav_brand' => self::PLATFORM_NAV_BRAND,
            'session_block_title' => $c((string) $request->input('em_session_block_title', ''), 120),
            'ref_label' => $c((string) $request->input('em_ref_label', ''), 40),
            'security_label' => $c((string) $request->input('em_security_label', ''), 40),
            'progress_prefix' => $c((string) $request->input('em_progress_prefix', ''), 80),
            'roe_title' => $c((string) $request->input('em_roe_title', ''), 160),
            'roe_items' => $roeItems,
            'watermark' => $c((string) $request->input('em_watermark', ''), 40),
            'doc_control' => $c((string) $request->input('em_doc_control', ''), 120),
            'queue_label' => $c((string) $request->input('em_queue_label', ''), 120),
            'candidate_prefix' => $c((string) $request->input('em_candidate_prefix', ''), 80),
            'classified_badge' => $c((string) $request->input('em_classified_badge', ''), 40),
            'op_note_title' => $c((string) $request->input('em_op_note_title', ''), 160),
            'op_col1' => $c((string) $request->input('em_op_col1', ''), 1200),
            'op_ai_warning' => $c((string) $request->input('em_op_ai_warning', ''), 600),
            'op_col2' => $c((string) $request->input('em_op_col2', ''), 1200),
            'archive_note' => $c((string) $request->input('em_archive_note', ''), 1200),
            'section_0' => $c((string) $request->input('em_section_0', ''), 200),
            'section_1' => $c((string) $request->input('em_section_1', ''), 200),
            'section_2' => $c((string) $request->input('em_section_2', ''), 200),
            'section_3' => $c((string) $request->input('em_section_3', ''), 200),
            'section_4' => $c((string) $request->input('em_section_4', ''), 200),
            'commitment_q13' => $c((string) $request->input('em_commitment_q13', ''), 400),
            'availability_q15' => $c((string) $request->input('em_availability_q15', ''), 400),
            'ai_checkbox' => $c((string) $request->input('em_ai_checkbox', ''), 400),
            'submit_button' => $c((string) $request->input('em_submit_button', ''), 120),
            'submit_footer' => $c((string) $request->input('em_submit_footer', ''), 200),
            'fields' => $fields,
            'availability_slots' => self::availabilitySlotsFromSelection(
                $request->input('em_availability_slot_ids'),
                $request->input('em_availability_slot_custom')
            ),
            'custom_questions' => self::normalizeCustomQuestions($request->input('em_custom_questions')),
            'auto_refuse_rules' => self::normalizeAutoRefuseRules($request->input('em_auto_refuse')),
            'disclaimer_recruitment_lines' => (static function () use ($request, $c): array {
                $raw = (string) $request->input('em_disclaimer_recruitment', '');
                $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
                $lines = array_slice(array_map(static fn (string $line) => $c($line, 500), $lines), 0, 12);

                return $lines !== [] ? $lines : self::defaultRecruitmentDisclaimerLines();
            })(),
        ];

        $motPosted = $request->input('em_motivation');
        $mot = self::motivationFromPostedArray(is_array($motPosted) ? $motPosted : null);
        if ($mot === null) {
            $mot = self::normalizeMotivationSection(
                $pack['section_3'] !== '' ? ['title' => $pack['section_3']] : null,
                $fields
            );
        } else {
            $mot['title'] = $c($mot['title'], 200);
            $mot['intro'] = $c($mot['intro'], 2000);
            foreach (['why_join', 'accountability'] as $qk) {
                $mot[$qk]['label'] = $c((string) $mot[$qk]['label'], 240);
                $mot[$qk]['placeholder'] = $c((string) $mot[$qk]['placeholder'], 400);
                $mot[$qk]['help'] = $c((string) $mot[$qk]['help'], 600);
            }
        }
        $pack['motivation'] = $mot;

        if ($pack['availability_q15'] === '' && $pack['availability_slots'] !== []) {
            $pack['availability_q15'] = self::defaultAvailabilityQuestion($pack['availability_slots']);
        }

        return self::syncMotivationIntoFields($pack);
    }
}
