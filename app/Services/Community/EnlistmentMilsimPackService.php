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
            'full_name' => ['label' => 'Nom et prénom (dossier)', 'placeholder' => 'ex. Jonathan King', 'widget' => 'text', 'options' => []],
            'legal_full_name' => ['label' => 'Nom réel pour le contact (si personnage)', 'placeholder' => 'Nom pour être recontacté — optionnel si déjà indiqué ailleurs', 'widget' => 'text', 'options' => []],
            'age' => ['label' => 'Âge', 'placeholder' => 'Âge minimum requis par la communauté', 'widget' => 'text', 'options' => []],
            'timezone' => ['label' => 'Fuseau horaire', 'placeholder' => 'ex. Paris (UTC+1)', 'widget' => 'text', 'options' => []],
            'weekly_availability' => ['label' => 'Disponibilités dans la semaine', 'placeholder' => 'Jours et créneaux habituels', 'widget' => 'text', 'options' => []],
            'email' => ['label' => 'Adresse e-mail', 'placeholder' => 'email@exemple.fr', 'widget' => 'text', 'options' => []],
            'callsign' => ['label' => 'Indicatif (optionnel)', 'placeholder' => 'ex. Ghost-2-1', 'widget' => 'text', 'options' => []],
            'system_config' => ['label' => 'Configuration PC (processeur, carte graphique, mémoire)', 'placeholder' => 'Décrivez brièvement votre matériel', 'widget' => 'text', 'options' => []],
            'microphone_quality' => ['label' => 'Disposez-vous d’un micro de bonne qualité ?', 'placeholder' => '', 'widget' => 'yesno', 'options' => ['Oui', 'Non']],
            'past_milsim_experience' => ['label' => 'Expériences milsim passées', 'placeholder' => 'Unités, rôles, durées…', 'widget' => 'textarea', 'options' => []],
            'ace_acre_level' => ['label' => 'Niveau ACE / ACRE', 'placeholder' => '', 'widget' => 'select', 'options' => ['Aucune', 'Basique', 'Expérimenté', 'Avancé']],
            'motivation_why_join' => ['label' => 'Pourquoi rejoindre cette communauté ?', 'placeholder' => 'Motivation, engagement, attentes…', 'widget' => 'textarea', 'options' => []],
            'motivation_accountability' => ['label' => 'Que signifie pour vous la responsabilité individuelle dans une unité ?', 'placeholder' => 'Votre lecture de l’accountability…', 'widget' => 'textarea', 'options' => []],
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
            'ref_label' => 'Référence',
            'security_label' => 'Connexion sécurisée',
            'progress_prefix' => 'Complété :',
            'roe_title' => 'Ce que nous attendons',
            'roe_items' => [
                'Réponses détaillées et honnêtes.',
                'Un micro de bonne qualité pour les opérations vocales.',
                'Disponibilité sur les créneaux principaux (souvent mercredi et samedi soir).',
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
            'commitment_q13' => 'Je comprends le temps et l’investissement demandés',
            'availability_q15' => 'Je suis disponible mercredi et samedi soir',
            'ai_checkbox' => 'Je confirme avoir rédigé ce dossier sans assistance par IA',
            'submit_button' => 'Envoyer ma candidature',
            'submit_footer' => 'Vous recevrez une confirmation après l’envoi.',
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
