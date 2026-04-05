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

    /** @return list<string> */
    public static function milsimFieldKeys(): array
    {
        return array_keys(self::defaultFieldLabels());
    }

    /**
     * @return array<string, array{label: string, placeholder: string, widget: string, options: list<string>}>
     */
    public static function defaultFieldLabels(): array
    {
        return [
            'full_name' => ['label' => '01 Nom & Prénom (identité dossier)', 'placeholder' => 'ex: Jonathan King', 'widget' => 'text', 'options' => []],
            'legal_full_name' => ['label' => 'Contact IRL (si personnage RP)', 'placeholder' => 'Nom légal pour recontact — optionnel si déjà indiqué ailleurs', 'widget' => 'text', 'options' => []],
            'age' => ['label' => '02 Âge', 'placeholder' => 'Âge minimum requis', 'widget' => 'text', 'options' => []],
            'timezone' => ['label' => '03 Fuseau Horaire', 'placeholder' => 'ex: Paris (UTC+1)', 'widget' => 'text', 'options' => []],
            'weekly_availability' => ['label' => '04 Disponibilités Hebdomadaires', 'placeholder' => 'Jours de la semaine', 'widget' => 'text', 'options' => []],
            'email' => ['label' => 'Email (obligatoire)', 'placeholder' => 'email@exemple.fr', 'widget' => 'text', 'options' => []],
            'callsign' => ['label' => 'Indicatif / callsign (optionnel)', 'placeholder' => 'ex: Ghost-2-1', 'widget' => 'text', 'options' => []],
            'system_config' => ['label' => '05 Configuration (CPU/GPU/RAM)', 'placeholder' => 'Configuration système', 'widget' => 'text', 'options' => []],
            'microphone_quality' => ['label' => '06 Microphone de Haute Qualité ?', 'placeholder' => '', 'widget' => 'yesno', 'options' => ['Oui', 'Non']],
            'past_milsim_experience' => ['label' => '07 Expériences MilSim Passées', 'placeholder' => 'Unités, rôles, durées...', 'widget' => 'textarea', 'options' => []],
            'ace_acre_level' => ['label' => '08 Maîtrise ACE / ACRE', 'placeholder' => '', 'widget' => 'select', 'options' => ['Aucune', 'Basique', 'Expérimenté', 'Avancé']],
            'motivation_why_join' => ['label' => '09 Pourquoi rejoindre ?', 'placeholder' => 'Motivation, engagement...', 'widget' => 'textarea', 'options' => []],
            'motivation_accountability' => ['label' => '10 Qu\'est-ce que l\'Accountability ?', 'placeholder' => 'Responsabilité individuelle dans une unité...', 'widget' => 'textarea', 'options' => []],
        ];
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
            'logo_letter' => 'F',
            'portal_title' => 'Portail de Recrutement',
            'portal_subtitle' => 'Infrastructure sécurisée — Athena COMSPEC',
            'preamble_title' => 'Accès Contrôlé',
            'preamble_lead' => 'Vous allez accéder à l’interface de candidature. Ce formulaire constitue un dossier d’évaluation préalable.',
            'preamble_status_lines' => [
                'Vérification de session : conforme',
                'Canal de transmission : sécurisé',
                'Journalisation des accès : active',
            ],
            'preamble_cta' => 'Accéder au Formulaire',
            'preamble_footer' => 'La poursuite vaut prise de connaissance des conditions de traitement des données.',
            'nav_brand' => self::PLATFORM_NAV_BRAND,
            'session_block_title' => 'Statut Session',
            'ref_label' => 'Réf.',
            'security_label' => 'Encrypted',
            'progress_prefix' => 'FORMULAIRE :',
            'roe_title' => 'Règles d\'Engagement (ROE)',
            'roe_items' => [
                'Réponses détaillées obligatoires.',
                'Microphone de qualité requis.',
                'Disponibilité mercredi et samedi soir attendue.',
                'Ne pas relancer l\'état-major après soumission.',
            ],
            'watermark' => 'OLYMPUS',
            'doc_control' => 'Document Control',
            'queue_label' => 'File d\'attente active',
            'candidate_prefix' => 'Candidature',
            'classified_badge' => 'CLASSIFIED',
            'op_note_title' => 'Note Opérationnelle',
            'op_col1' => 'Toute soumission est examinée par la cellule de recrutement.',
            'op_ai_warning' => 'L\'utilisation de l\'IA est strictement interdite.',
            'op_col2' => 'Les candidats retenus seront contactés directement.',
            'archive_note' => 'Chaque réponse incomplète ou assistée par IA entraîne l\'archivage du dossier.',
            'section_0' => 'Mode de candidature',
            'section_1' => 'Section I — Cadre administratif & contact',
            'section_2' => 'Section II — Matériel & expérience de jeu',
            'section_3' => 'Section III — Motivation & intention',
            'section_4' => 'Section IV — Engagement',
            'fields' => self::defaultFieldLabels(),
            'commitment_q13' => '13 Je comprends l\'investissement temps/effort requis',
            'availability_q15' => '15 Disponible mercredi & samedi soir',
            'ai_checkbox' => '20 Je confirme l\'absence d\'IA dans ce rapport',
            'submit_button' => 'Soumettre au Commandement',
            'submit_footer' => 'Transmission sécurisée',
        ];
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
            return $defaults;
        }

        $out = $defaults;
        foreach ([
            'logo_letter', 'portal_title', 'portal_subtitle', 'preamble_title', 'preamble_lead',
            'preamble_cta', 'preamble_footer', 'session_block_title', 'ref_label',
            'security_label', 'progress_prefix', 'roe_title', 'watermark', 'doc_control',
            'queue_label', 'candidate_prefix', 'classified_badge', 'op_note_title', 'op_col1',
            'op_ai_warning', 'op_col2', 'archive_note', 'section_0', 'section_1', 'section_2', 'section_3', 'section_4',
            'commitment_q13', 'availability_q15', 'ai_checkbox', 'submit_button', 'submit_footer',
        ] as $k) {
            if (isset($raw[$k]) && is_string($raw[$k]) && trim($raw[$k]) !== '') {
                $out[$k] = $raw[$k];
            }
        }
        $out['nav_brand'] = self::PLATFORM_NAV_BRAND;

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
        $out['nav_brand'] = self::PLATFORM_NAV_BRAND;

        return $out === [] ? null : $out;
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

        return [
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
        ];
    }
}
