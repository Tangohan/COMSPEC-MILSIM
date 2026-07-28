<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Request;

/**
 * Lecture / écriture du bloc settings.community (CV registre, contact, accès public).
 */
final class TenantCommunityProfileService
{
    private const HERO_LEAD_MAX_LENGTH = 320;

    /** Modes d’inscription publique supportés (candidature / enrôlement). */
    public const REGISTRATION_MODE_MILSIM = 'milsim';
    public const REGISTRATION_MODE_SIMPLE = 'simple';
    public const REGISTRATION_MODE_DISCORD = 'discord';

    /**
     * @return list<string>
     */
    public static function allowedRegistrationModes(): array
    {
        return [
            self::REGISTRATION_MODE_MILSIM,
            self::REGISTRATION_MODE_SIMPLE,
            self::REGISTRATION_MODE_DISCORD,
        ];
    }

    public static function normalizeRegistrationMode(mixed $raw): string
    {
        $mode = strtolower(trim((string) ($raw ?? '')));
        if ($mode === self::REGISTRATION_MODE_SIMPLE || $mode === self::REGISTRATION_MODE_DISCORD) {
            return $mode;
        }

        return self::REGISTRATION_MODE_MILSIM;
    }

    /** Libellé métier pour l’UI (jamais la valeur technique brute). */
    public static function registrationModeLabel(mixed $raw): string
    {
        return match (self::normalizeRegistrationMode($raw)) {
            self::REGISTRATION_MODE_SIMPLE => 'Formulaire court',
            self::REGISTRATION_MODE_DISCORD => 'Recrutement via Discord',
            default => 'Dossier MilSim complet',
        };
    }

    /**
     * True si le recrutement Discord est actif sans lien d’invitation renseigné.
     *
     * @param array<string, mixed> $community
     */
    public static function needsDiscordInviteAlert(array $community): bool
    {
        if (self::normalizeRegistrationMode($community['registration_mode'] ?? null) !== self::REGISTRATION_MODE_DISCORD) {
            return false;
        }

        return trim((string) ($community['contact_discord_url'] ?? '')) === '';
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array{is_real: bool, fictional_label: ?string, country: ?string, country_label: ?string, unit_ids: list<string>, unit_labels: list<string>}|null
     */
    public static function normalizeUnitAffiliationFromRequest(Request $request, ?array $existing = null): ?array
    {
        $raw = $request->input('unit_affiliation_mode', '');
        $mode = is_string($raw) ? strtolower(trim($raw)) : '';
        if (!in_array($mode, ['real', 'fictional'], true)) {
            return $existing;
        }

        if ($mode === 'fictional') {
            $label = trim((string) $request->input('unit_affiliation_fictional_label', ''));

            return [
                'is_real' => false,
                'fictional_label' => $label !== '' ? self::clipStatic($label, 200) : null,
                'country' => null,
                'country_label' => null,
                'unit_ids' => [],
                'unit_labels' => [],
            ];
        }

        $country = strtoupper(trim((string) $request->input('unit_affiliation_country', '')));
        if (!in_array($country, RealUnitAffiliationCatalog::allowedCountryCodes(), true)) {
            return [
                'is_real' => true,
                'fictional_label' => null,
                'country' => null,
                'country_label' => null,
                'unit_ids' => [],
                'unit_labels' => [],
            ];
        }

        $rawIds = $request->input('unit_affiliation_unit_ids', []);
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        $ids = [];
        foreach ($rawIds as $rawId) {
            if (!is_string($rawId)) {
                continue;
            }
            $id = trim($rawId);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        $resolved = RealUnitAffiliationCatalog::resolveSelectedUnits($country, array_keys($ids));
        $labels = RealUnitAffiliationCatalog::countryLabels();

        return [
            'is_real' => true,
            'fictional_label' => null,
            'country' => $country,
            'country_label' => $labels[$country] ?? $country,
            'unit_ids' => array_column($resolved, 'id'),
            'unit_labels' => array_column($resolved, 'name'),
        ];
    }

    /** Modèle de page publique : classique (carte) ou vitrine (pleine page). */
    public static function resolvePublicPageLayout(mixed $raw): string
    {
        $s = strtolower(trim((string) ($raw ?? '')));
        if ($s === 'legacy' || $s === 'classic' || $s === 'card') {
            return 'legacy';
        }
        if ($raw === true || $raw === 1 || $raw === '1') {
            return 'showcase';
        }
        if (in_array($s, ['showcase', 'vitrine', 'full', 'wide'], true)) {
            return 'showcase';
        }
        // Par défaut : vitrine dynamique (fiches /c/{slug})
        return 'showcase';
    }

    /**
     * Unité de jeu (défaut) vs portail plateforme (moins d’emphase recrutement).
     * Si `public_audience` est absent en base, on peut dériver via PLATFORM_PUBLIC_TENANT_SLUGS (.env).
     */
    public static function resolvePublicAudience(array $community, ?string $tenantSlug = null): string
    {
        if (array_key_exists('public_audience', $community)) {
            return (($community['public_audience'] ?? '') === 'platform') ? 'platform' : 'unit';
        }
        $slug = strtolower(trim((string) ($tenantSlug ?? '')));
        if ($slug === '') {
            return 'unit';
        }
        $list = trim((string) env('PLATFORM_PUBLIC_TENANT_SLUGS', ''));
        if ($list === '') {
            return 'unit';
        }
        foreach (array_map('trim', explode(',', $list)) as $s) {
            if ($s !== '' && strtolower($s) === $slug) {
                return 'platform';
            }
        }

        return 'unit';
    }

    public const BADGE_MILSIM = 'milsim';
    public const BADGE_SEMI_MILSIM = 'semi_milsim';
    public const BADGE_CASUAL = 'casual';

    /** Prérequis catalogue (choix fermés) — clé technique → libellé public. */
    public const PREREQ_AGE_16 = 'age_16';
    public const PREREQ_DISCORD_MIC = 'discord_mic';
    public const PREREQ_ANDROID_ATAK = 'android_atak';
    public const PREREQ_REGULAR_PRESENCE = 'regular_presence';
    public const PREREQ_MILSIM_XP = 'milsim_experience';
    public const PREREQ_PERSONAL_GEAR = 'personal_gear';
    public const PREREQ_FRENCH = 'french_spoken';
    public const PREREQ_ARMA = 'arma_owned';

    /** Statuts d’un prérequis (jamais exposés bruts côté UI). */
    public const PREREQ_STATUS_REQUIRED = 'required';
    public const PREREQ_STATUS_OPTIONAL = 'optional';
    public const PREREQ_STATUS_NOT_REQUIRED = 'not_required';

    /** @return array<string, string> slug => libellé affiché */
    public static function badgeLabels(): array
    {
        return [
            self::BADGE_MILSIM => 'Milsim',
            self::BADGE_SEMI_MILSIM => 'Semi-milsim',
            self::BADGE_CASUAL => 'Casual',
        ];
    }

    /**
     * Catalogue des prérequis proposés aux administrateurs (cases à cocher).
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public static function prerequisiteCatalog(): array
    {
        return [
            self::PREREQ_AGE_16 => [
                'label' => '16 ans minimum',
                'hint' => 'Autorisation parentale demandée avant 18 ans.',
            ],
            self::PREREQ_DISCORD_MIC => [
                'label' => 'Micro correct et Discord',
                'hint' => 'La communication vocale est la base de tout.',
            ],
            self::PREREQ_ANDROID_ATAK => [
                'label' => 'Un smartphone Android',
                'hint' => 'Pour ATAK. iOS n’est souvent pas compatible avec le serveur de l’unité.',
            ],
            self::PREREQ_REGULAR_PRESENCE => [
                'label' => 'Une présence régulière',
                'hint' => 'Un terrain par semaine suffit en général à rester actif.',
            ],
            self::PREREQ_MILSIM_XP => [
                'label' => 'Expérience milsim',
                'hint' => 'Souvent non exigée : l’instruction est faite pour les débutants.',
            ],
            self::PREREQ_PERSONAL_GEAR => [
                'label' => 'Matériel personnel',
                'hint' => 'Souvent non exigé pour la période d’essai.',
            ],
            self::PREREQ_FRENCH => [
                'label' => 'Français oral',
                'hint' => 'Les briefings et la radio se font en français.',
            ],
            self::PREREQ_ARMA => [
                'label' => 'Jeu de base installé',
                'hint' => 'Selon le jeu principal de la communauté (ex. Arma).',
            ],
        ];
    }

    /** @return list<string> */
    public static function allowedPrerequisiteKeys(): array
    {
        return array_keys(self::prerequisiteCatalog());
    }

    public static function prerequisiteStatusLabel(string $status): string
    {
        return match ($status) {
            self::PREREQ_STATUS_REQUIRED => 'Exigé',
            self::PREREQ_STATUS_OPTIONAL => 'Souhaité',
            self::PREREQ_STATUS_NOT_REQUIRED => 'Non exigé',
            default => 'À préciser',
        };
    }

    public static function normalizePrerequisiteStatus(mixed $raw): string
    {
        $s = strtolower(trim((string) ($raw ?? '')));
        if ($s === self::PREREQ_STATUS_OPTIONAL || $s === 'souhaite' || $s === 'souhaité') {
            return self::PREREQ_STATUS_OPTIONAL;
        }
        if ($s === self::PREREQ_STATUS_NOT_REQUIRED || $s === 'non' || $s === 'no' || $s === 'none') {
            return self::PREREQ_STATUS_NOT_REQUIRED;
        }

        return self::PREREQ_STATUS_REQUIRED;
    }

    /** @return list<string> */
    public static function allowedBadgeSlugs(): array
    {
        return array_keys(self::badgeLabels());
    }

    /** @return list<string> Slugs de badges autorisés (alias plan « badgesAllowed »). */
    public static function badgesAllowed(): array
    {
        return self::allowedBadgeSlugs();
    }

    /**
     * Normalisation / validation depuis le formulaire back-office (alias sémantique de buildCommunityFromRequest).
     *
     * @param array<string, mixed> $existing bloc community existant
     * @return array<string, mixed> bloc à fusionner dans settings
     */
    public function normalizeFromRequest(Request $request, array $existing): array
    {
        return $this->buildCommunityFromRequest($request, $existing);
    }

    /**
     * Données d’affichage pour la fiche publique /c/{slug} (évite la logique métier dans le template).
     *
     * @param array<string, mixed> $community bloc settings.community
     * @return array<string, mixed>
     */
    public static function getPublicViewModel(array $community, ?string $tenantSlug = null): array
    {
        $labels = self::badgeLabels();
        $styleBadgeLabels = [];
        foreach (is_array($community['style_badges'] ?? null) ? $community['style_badges'] : [] as $slug) {
            if (is_string($slug) && isset($labels[$slug])) {
                $styleBadgeLabels[] = $labels[$slug];
            }
        }
        $presentationMode = ($community['presentation_mode'] ?? 'simple') === 'military' ? 'military' : 'simple';
        $simpleBody = trim((string) ($community['simple_body'] ?? ''));
        $expectations = trim((string) ($community['expectations'] ?? ''));
        $gameLabel = trim((string) ($community['game_label'] ?? ''));
        $mainMods = trim((string) ($community['main_mods'] ?? ''));
        $modpackSize = $community['modpack_size_gb'] ?? null;
        $militarySections = is_array($community['military_sections'] ?? null) ? $community['military_sections'] : [];
        $welcomeText = trim((string) ($community['welcome_text'] ?? ''));
        $registrationMode = self::normalizeRegistrationMode($community['registration_mode'] ?? self::REGISTRATION_MODE_MILSIM);
        $publicAudience = self::resolvePublicAudience($community, $tenantSlug);

        $contactEmail = trim((string) ($community['contact_email'] ?? ''));

        return [
            'presentationMode' => $presentationMode,
            'gameLabel' => $gameLabel,
            'mainMods' => $mainMods,
            'modpackSize' => $modpackSize !== null && (string) $modpackSize !== '' ? (string) $modpackSize : null,
            'simpleBody' => $simpleBody,
            'expectations' => $expectations,
            'militarySections' => $militarySections,
            'styleBadgeLabels' => $styleBadgeLabels,
            'welcomeText' => $welcomeText,
            'registrationMode' => $registrationMode,
            'registrationModeLabel' => self::registrationModeLabel($registrationMode),
            'isLocked' => !empty($community['community_locked']),
            'discordUrl' => trim((string) ($community['contact_discord_url'] ?? '')),
            'contactEmail' => $contactEmail,
            'contactIntro' => trim((string) ($community['contact_intro'] ?? '')),
            'contactFormEnabled' => !empty($community['contact_form_enabled']) && $contactEmail !== '',
            'publicAudience' => $publicAudience,
        ];
    }

    /**
     * Tags affichés sur le registre /communities (sélection admin).
     *
     * @return array<string, string> slug => libellé
     */
    public static function registryTagLabels(): array
    {
        return [
            'infantry' => 'Infanterie',
            'armor' => 'Blindés',
            'air' => 'Aérien / hélico',
            'soar' => 'Forces spéciales',
            'logistics' => 'Logistique',
            'training' => 'Entraînement / école',
            'campaign' => 'Campagne longue durée',
            'one_shot' => 'OP ponctuelles',
            'rp' => 'Roleplay',
            'tactical' => 'Jeu tactique',
            'international' => 'International',
            'fr_speaking' => 'Francophone',
        ];
    }

    /** @return list<string> */
    public static function allowedRegistryTagSlugs(): array
    {
        return array_keys(self::registryTagLabels());
    }

    /** @return list<string> */
    public static function allowedNavAccents(): array
    {
        return ['sky', 'amber', 'emerald', 'violet', 'rose', 'slate'];
    }

    /** @return list<string> */
    public static function allowedNavSubmenuStyles(): array
    {
        return ['standard', 'cards', 'minimal'];
    }

    /**
     * @param array<string, mixed> $existing bloc community existant (ou [])
     * @return array<string, mixed> bloc community complet à fusionner dans settings
     */
    public function buildCommunityFromRequest(Request $request, array $existing): array
    {
        $c = $existing;

        $c['registration_mode'] = self::normalizeRegistrationMode($request->input('registration_mode', self::REGISTRATION_MODE_MILSIM));

        $c['registry_listed'] = (string) $request->input('registry_listed', '1') !== '0';
        $c['forum_members_only'] = (string) $request->input('forum_members_only', '0') === '1';

        $c['game_label'] = $this->clip((string) $request->input('game_label', ''), 120);
        $c['main_mods'] = $this->clip((string) $request->input('main_mods', ''), 4000);
        $modpack = trim((string) $request->input('modpack_size_gb', ''));
        $c['modpack_size_gb'] = $modpack === '' ? null : $this->clip($modpack, 32);

        $mode = (string) $request->input('presentation_mode', 'simple');
        $c['presentation_mode'] = $mode === 'military' ? 'military' : 'simple';
        $c['simple_body'] = $this->clip((string) $request->input('simple_body', ''), 8000);
        $c['expectations'] = $this->clip((string) $request->input('expectations', ''), 8000);

        $c['military_sections'] = $this->parseMilitarySections($request);

        $badges = $request->input('style_badges', []);
        if (!is_array($badges)) {
            $badges = [];
        }
        $allowed = array_flip(self::allowedBadgeSlugs());
        $c['style_badges'] = array_values(array_filter(array_map(static function ($s) use ($allowed) {
            $k = is_string($s) ? strtolower(trim($s)) : '';

            return isset($allowed[$k]) ? $k : null;
        }, $badges)));

        $regTags = $request->input('registry_tags', []);
        if (!is_array($regTags)) {
            $regTags = [];
        }
        $allowedReg = array_flip(self::allowedRegistryTagSlugs());
        $c['registry_tags'] = array_values(array_filter(array_map(static function ($s) use ($allowedReg) {
            $k = is_string($s) ? strtolower(trim($s)) : '';

            return isset($allowedReg[$k]) ? $k : null;
        }, $regTags)));

        $c['contact_discord_url'] = $this->sanitizeUrl((string) $request->input('contact_discord_url', ''), 500);
        $c['contact_email'] = $this->sanitizeEmail((string) $request->input('contact_email', ''));
        $c['contact_form_enabled'] = (string) $request->input('contact_form_enabled', '0') === '1';
        $c['contact_intro'] = $this->clip((string) $request->input('contact_intro', ''), 500);
        $c['portal_nav'] = $this->parsePortalNav($request, is_array($existing['portal_nav'] ?? null) ? $existing['portal_nav'] : []);

        $c['enlistment_milsim'] = EnlistmentMilsimPackService::buildFromRequest($request);

        $layout = (string) $request->input('public_page_layout', 'legacy');
        $c['public_page_layout'] = $layout === 'showcase' ? 'showcase' : 'legacy';
        $aud = (string) $request->input('public_audience', 'unit');
        $c['public_audience'] = $aud === 'platform' ? 'platform' : 'unit';
        $c['public_hero_subtitle'] = $this->clip((string) $request->input('public_hero_subtitle', ''), 600);
        $c['public_doctrine'] = $this->clip((string) $request->input('public_doctrine', ''), 200);
        $c['public_access_label'] = $this->clip((string) $request->input('public_access_label', ''), 120);
        $c['public_mission'] = $this->clip((string) $request->input('public_mission', ''), 4000);
        $c['public_region_badges'] = $this->parseStringList($request->input('public_region_badges', ''), 8, 48);
        $c['public_specialties'] = $this->parseStringList($request->input('public_specialties', ''), 24, 64);
        $c['public_stats_mode'] = ((string) $request->input('public_stats_mode', 'manual')) === 'computed' ? 'computed' : 'manual';
        $c['public_stats_manual'] = [
            'effectif' => $this->clip((string) $request->input('public_stats_effectif', ''), 12),
            'unites' => $this->clip((string) $request->input('public_stats_unites', ''), 12),
            'activite_percent' => $this->clip((string) $request->input('public_stats_activite', ''), 12),
            'theatre' => $this->clip((string) $request->input('public_stats_theatre', ''), 120),
        ];
        $c['public_command_chain'] = $this->parseCommandChain($request);
        $c['public_roster_enabled'] = (string) $request->input('public_roster_enabled', '0') === '1';
        $c['public_recruitment_badge_open'] = (string) $request->input('public_recruitment_badge_open', '0') === '1';
        $mods = [
            'forum' => (string) $request->input('public_mod_forum', '0') === '1',
            'documents' => (string) $request->input('public_mod_documents', '0') === '1',
            'events' => (string) $request->input('public_mod_events', '0') === '1',
            'roster' => (string) $request->input('public_mod_roster', '0') === '1',
            'training' => (string) $request->input('public_mod_training', '0') === '1',
            'analytics' => (string) $request->input('public_mod_analytics', '0') === '1',
        ];
        $c['public_modules'] = $mods;

        $c['public_hero_headline'] = $this->clip((string) $request->input('public_hero_headline', ''), 220);
        $c['public_founded_year'] = $this->clip((string) $request->input('public_founded_year', ''), 12);
        $c['public_recruitment_session_label'] = $this->clip((string) $request->input('public_recruitment_session_label', ''), 80);
        $c['public_about_title'] = $this->clip((string) $request->input('public_about_title', ''), 160);
        $c['public_about_body'] = $this->clip((string) $request->input('public_about_body', ''), 8000);
        $c['public_about_body_secondary'] = $this->clip((string) $request->input('public_about_body_secondary', ''), 4000);
        $c['public_sections_title'] = $this->clip((string) $request->input('public_sections_title', ''), 160);
        $c['public_sections_lead'] = $this->clip((string) $request->input('public_sections_lead', ''), 240);

        $c['public_video_url'] = $this->sanitizeUrl((string) $request->input('public_video_url', ''), 1024);
        $c['public_video_title'] = $this->clip((string) $request->input('public_video_title', ''), 160);
        $c['public_video_body'] = $this->clip((string) $request->input('public_video_body', ''), 800);
        $c['public_video_chapters'] = $this->parsePairList(
            $request->input('public_video_chapter_time', []),
            $request->input('public_video_chapter_label', []),
            'time',
            'label',
            8,
            16,
            120
        );

        $c['public_pitch'] = $this->parsePairList(
            $request->input('public_pitch_title', []),
            $request->input('public_pitch_body', []),
            'title',
            'body',
            8,
            120,
            400
        );
        $c['public_prerequisites'] = $this->parsePrerequisitesFromRequest($request);
        $c['public_process_steps'] = $this->parseProcessStepsFromRequest($request);
        $c['public_faq'] = $this->parsePairList(
            $request->input('public_faq_q', []),
            $request->input('public_faq_a', []),
            'q',
            'a',
            12,
            200,
            800
        );
        $c['public_partners'] = $this->parseStringList((string) $request->input('public_partners', ''), 12, 80);
        $c['public_testimonials'] = $this->parseTestimonialsFromRequest($request);
        $c['public_cta_kicker'] = $this->clip((string) $request->input('public_cta_kicker', ''), 80);
        $c['public_cta_title'] = $this->clip((string) $request->input('public_cta_title', ''), 160);
        $c['public_cta_body'] = $this->clip((string) $request->input('public_cta_body', ''), 500);

        // Conserver clés existantes non gérées par ce formulaire
        foreach ([
            'community_locked', 'welcome_text', 'require_ai_ack',
            'default_locale', 'orbat_visibility', 'default_guest_role_slug',
        ] as $preserve) {
            if (!array_key_exists($preserve, $c) && array_key_exists($preserve, $existing)) {
                $c[$preserve] = $existing[$preserve];
            }
        }

        return $c;
    }

    /**
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function parsePortalNav(Request $request, array $existing): array
    {
        $out = $existing;
        foreach (['operations', 'resources'] as $slot) {
            $acc = strtolower(trim((string) $request->input('nav_' . $slot . '_accent', '')));
            if (!in_array($acc, self::allowedNavAccents(), true)) {
                $acc = ($slot === 'operations') ? 'sky' : 'amber';
            }

            $style = strtolower(trim((string) $request->input('nav_' . $slot . '_submenu_style', '')));
            if (!in_array($style, self::allowedNavSubmenuStyles(), true)) {
                $style = 'standard';
            }

            $out[$slot] = [
                'accent' => $acc,
                'image_enabled' => (string) $request->input('nav_' . $slot . '_image_enabled', '1') === '1',
                'submenu_style' => $style,
            ];
        }

        return $out;
    }

    /**
     * Modèle vitrine + stats (manuelles ou calculées côté contrôleur).
     *
     * @param array<string, mixed> $community
     * @param array<string, mixed> $computed effectif_actifs, unites_public, activite_pct, roster_public_count, roster_rows_count
     * @param array<string, mixed> $tenant row tenant (name, community_code, …)
     * @param array<string, mixed> $tenantMerge settings racine (timezone, …)
     * @return array<string, mixed>
     */
    public static function getShowcaseViewModel(array $community, array $computed, array $tenant, array $tenantMerge = []): array
    {
        $manual = is_array($community['public_stats_manual'] ?? null) ? $community['public_stats_manual'] : [];
        $mode = ($community['public_stats_mode'] ?? 'manual') === 'computed' ? 'computed' : 'manual';
        $eff = $mode === 'computed'
            ? (string) ($computed['effectif_actifs'] ?? '')
            : (string) ($manual['effectif'] ?? '');
        if ($eff === '' && isset($computed['effectif_actifs']) && (string) $computed['effectif_actifs'] !== '') {
            $eff = (string) $computed['effectif_actifs'];
        }
        $uni = $mode === 'computed'
            ? (string) ($computed['unites_public'] ?? '')
            : (string) ($manual['unites'] ?? '');
        if ($uni === '' && isset($computed['unites_public']) && (string) $computed['unites_public'] !== '') {
            $uni = (string) $computed['unites_public'];
        }
        if ($mode === 'computed') {
            $ap = $computed['activite_pct'] ?? null;
            $act = $ap !== null && $ap !== '' ? (string) $ap . '%' : '';
        } else {
            $act = (string) ($manual['activite_percent'] ?? '');
            if ($act !== '' && !str_contains($act, '%')) {
                $act .= '%';
            }
            if ($act === '' && isset($computed['activite_pct']) && $computed['activite_pct'] !== null && $computed['activite_pct'] !== '') {
                $act = (string) $computed['activite_pct'] . '%';
            }
        }
        $theatre = $mode === 'computed'
            ? (string) ($computed['theatre_default'] ?? '')
            : (string) ($manual['theatre'] ?? '');
        if ($theatre === '' && isset($computed['theatre_default'])) {
            $theatre = (string) $computed['theatre_default'];
        }

        $regionBadges = is_array($community['public_region_badges'] ?? null) ? $community['public_region_badges'] : [];
        $specialties = is_array($community['public_specialties'] ?? null) ? $community['public_specialties'] : [];
        $commandChain = [];
        foreach (is_array($community['public_command_chain'] ?? null) ? $community['public_command_chain'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rl = trim((string) ($row['role_label'] ?? ''));
            $dn = trim((string) ($row['display_name'] ?? ''));
            $hint = trim((string) ($row['hint'] ?? ''));
            if ($rl === '' && $dn === '' && $hint === '') {
                continue;
            }
            $commandChain[] = ['role_label' => $rl, 'display_name' => $dn, 'hint' => $hint];
        }
        $mods = is_array($community['public_modules'] ?? null) ? $community['public_modules'] : [];
        $modules = [
            'forum' => !empty($mods['forum']),
            'documents' => !empty($mods['documents']),
            'events' => !empty($mods['events']),
            'roster' => !empty($mods['roster']),
            'training' => !empty($mods['training']),
            'analytics' => !empty($mods['analytics']),
        ];

        $heroLead = trim((string) ($community['public_hero_subtitle'] ?? ''));
        if ($heroLead === '') {
            $heroLead = self::excerptText(
                trim((string) ($community['simple_body'] ?? '')),
                self::HERO_LEAD_MAX_LENGTH
            );
        }
        if ($heroLead === '') {
            $heroLead = self::excerptText(
                trim((string) ($community['welcome_text'] ?? '')),
                self::HERO_LEAD_MAX_LENGTH
            );
        }
        if ($heroLead === '') {
            $heroLead = self::excerptText(
                trim((string) ($community['public_about_body'] ?? '')),
                self::HERO_LEAD_MAX_LENGTH
            );
        }
        if ($heroLead === '') {
            $heroLead = self::excerptText(
                trim((string) ($community['public_mission'] ?? '')),
                self::HERO_LEAD_MAX_LENGTH
            );
        }

        $aboutBody = trim((string) ($community['public_about_body'] ?? ''));
        if ($aboutBody === '') {
            $aboutBody = trim((string) ($community['simple_body'] ?? ''));
        }
        if ($aboutBody === '') {
            $aboutBody = trim((string) ($community['public_mission'] ?? ''));
        }

        $prereqs = [];
        $catalog = self::prerequisiteCatalog();
        foreach (is_array($community['public_prerequisites'] ?? null) ? $community['public_prerequisites'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = strtolower(trim((string) ($row['key'] ?? '')));
            if ($key !== '' && !isset($catalog[$key])) {
                $key = '';
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '' && $key !== '') {
                $label = $catalog[$key]['label'];
            }
            if ($label === '') {
                continue;
            }
            $status = self::normalizePrerequisiteStatus($row['status'] ?? self::PREREQ_STATUS_REQUIRED);
            $detail = trim((string) ($row['detail'] ?? ''));
            if ($detail === '' && $key !== '') {
                $detail = $catalog[$key]['hint'];
            }
            $prereqs[] = [
                'key' => $key,
                'label' => $label,
                'detail' => $detail,
                'status' => $status,
                'statusLabel' => self::prerequisiteStatusLabel($status),
                'required' => $status === self::PREREQ_STATUS_REQUIRED,
            ];
        }

        $pitch = [];
        foreach (is_array($community['public_pitch'] ?? null) ? $community['public_pitch'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $t = trim((string) ($row['title'] ?? ''));
            $b = trim((string) ($row['body'] ?? ''));
            if ($t === '' && $b === '') {
                continue;
            }
            $pitch[] = ['title' => $t, 'body' => $b];
        }

        $steps = [];
        foreach (is_array($community['public_process_steps'] ?? null) ? $community['public_process_steps'] : [] as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $t = trim((string) ($row['title'] ?? ''));
            $b = trim((string) ($row['body'] ?? ''));
            $delay = trim((string) ($row['delay'] ?? ''));
            if ($t === '' && $b === '') {
                continue;
            }
            $steps[] = [
                'n' => (string) ($i + 1),
                'title' => $t,
                'body' => $b,
                'delay' => $delay,
                'highlight' => !empty($row['highlight']),
            ];
        }

        $faq = [];
        foreach (is_array($community['public_faq'] ?? null) ? $community['public_faq'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = trim((string) ($row['q'] ?? ''));
            $a = trim((string) ($row['a'] ?? ''));
            if ($q === '' && $a === '') {
                continue;
            }
            $faq[] = ['q' => $q, 'a' => $a];
        }

        $partners = [];
        foreach (is_array($community['public_partners'] ?? null) ? $community['public_partners'] : [] as $p) {
            if (is_string($p) && trim($p) !== '') {
                $partners[] = trim($p);
            }
        }

        $testimonials = [];
        foreach (is_array($community['public_testimonials'] ?? null) ? $community['public_testimonials'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $text = trim((string) ($row['text'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($text === '' && $name === '') {
                continue;
            }
            $initials = trim((string) ($row['initials'] ?? ''));
            if ($initials === '' && $name !== '') {
                $parts = preg_split('/\s+/', $name) ?: [];
                $initials = '';
                foreach (array_slice($parts, 0, 2) as $p) {
                    $initials .= mb_strtoupper(mb_substr((string) $p, 0, 1));
                }
            }
            $testimonials[] = [
                'text' => $text,
                'name' => $name,
                'meta' => trim((string) ($row['meta'] ?? '')),
                'initials' => $initials !== '' ? $initials : '·',
            ];
        }

        $videoChapters = [];
        foreach (is_array($community['public_video_chapters'] ?? null) ? $community['public_video_chapters'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $time = trim((string) ($row['time'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($time === '' && $label === '') {
                continue;
            }
            $videoChapters[] = ['time' => $time, 'label' => $label];
        }

        $heroFacts = [];
        if ($eff !== '') {
            $heroFacts[] = ['v' => $eff, 'k' => 'MEMBRES ACTIFS'];
        }
        if ($uni !== '') {
            $heroFacts[] = ['v' => $uni, 'k' => 'UNITÉS'];
        }
        if ($act !== '') {
            $heroFacts[] = ['v' => $act, 'k' => 'ACTIVITÉ'];
        }
        $founded = trim((string) ($community['public_founded_year'] ?? ''));
        if ($founded !== '') {
            $heroFacts[] = ['v' => $founded, 'k' => 'FONDÉE'];
        }

        return [
            'publicPageLayout' => self::resolvePublicPageLayout($community['public_page_layout'] ?? null),
            'heroSubtitle' => $heroLead,
            'heroHeadline' => trim((string) ($community['public_hero_headline'] ?? '')),
            'foundedYear' => $founded,
            'recruitmentSessionLabel' => trim((string) ($community['public_recruitment_session_label'] ?? '')),
            'publicDoctrine' => trim((string) ($community['public_doctrine'] ?? '')),
            'publicAccessLabel' => trim((string) ($community['public_access_label'] ?? '')),
            'publicMission' => trim((string) ($community['public_mission'] ?? '')),
            'aboutTitle' => trim((string) ($community['public_about_title'] ?? '')),
            'aboutBody' => $aboutBody,
            'aboutBodySecondary' => trim((string) ($community['public_about_body_secondary'] ?? '')),
            'sectionsTitle' => trim((string) ($community['public_sections_title'] ?? '')),
            'sectionsLead' => trim((string) ($community['public_sections_lead'] ?? '')),
            'regionBadges' => $regionBadges,
            'specialties' => $specialties,
            'stats' => [
                'effectif' => $eff,
                'unites' => $uni,
                'activite' => $act,
                'theatre' => $theatre,
            ],
            'statsMode' => $mode,
            'heroFacts' => $heroFacts,
            'commandChain' => $commandChain,
            'publicRosterEnabled' => !empty($community['public_roster_enabled']),
            'recruitmentBadgeOpen' => !empty($community['public_recruitment_badge_open']),
            'publicModules' => $modules,
            'timezoneLabel' => (string) ($tenantMerge['timezone'] ?? ''),
            'rosterPublicCount' => (int) ($computed['roster_public_count'] ?? 0),
            'videoUrl' => trim((string) ($community['public_video_url'] ?? '')),
            'videoTitle' => trim((string) ($community['public_video_title'] ?? '')),
            'videoBody' => trim((string) ($community['public_video_body'] ?? '')),
            'videoChapters' => $videoChapters,
            'pitch' => $pitch,
            'prerequisites' => $prereqs,
            'processSteps' => $steps,
            'faq' => $faq,
            'partners' => $partners,
            'testimonials' => $testimonials,
            'ctaKicker' => trim((string) ($community['public_cta_kicker'] ?? '')),
            'ctaTitle' => trim((string) ($community['public_cta_title'] ?? '')),
            'ctaBody' => trim((string) ($community['public_cta_body'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $community
     * @return array{
     *   tagline: string,
     *   style_badge_labels: list<string>,
     *   registry_tag_labels: list<string>,
     *   unit_affiliation_label: string
     * }
     */
    public static function registryCardMeta(array $community): array
    {
        $styleBadgeLabels = [];
        if (!empty($community['style_badges']) && is_array($community['style_badges'])) {
            $labels = self::badgeLabels();
            foreach ($community['style_badges'] as $slug) {
                if (is_string($slug) && isset($labels[$slug])) {
                    $styleBadgeLabels[] = $labels[$slug];
                }
            }
        }
        $registryTagLabels = [];
        if (!empty($community['registry_tags']) && is_array($community['registry_tags'])) {
            $reg = self::registryTagLabels();
            foreach ($community['registry_tags'] as $slug) {
                if (is_string($slug) && isset($reg[$slug])) {
                    $registryTagLabels[] = $reg[$slug];
                }
            }
        }
        $tagline = '';
        if (($community['presentation_mode'] ?? 'simple') === 'simple') {
            $t = trim((string) ($community['simple_body'] ?? ''));
            $tagline = $t !== '' ? mb_substr(preg_replace('/\s+/', ' ', $t), 0, 220) : '';
        } else {
            $sections = $community['military_sections'] ?? [];
            if (is_array($sections) && $sections !== []) {
                $first = $sections[0];
                if (is_array($first)) {
                    $tagline = trim((string) ($first['body'] ?? ''));
                    $tagline = $tagline !== '' ? mb_substr(preg_replace('/\s+/', ' ', $tagline), 0, 220) : '';
                }
            }
        }
        if ($tagline === '' && !empty($community['game_label'])) {
            $tagline = (string) $community['game_label'];
        }

        $unitAffiliationLabel = self::unitAffiliationSummary($community);

        return [
            'tagline' => $tagline,
            'style_badge_labels' => $styleBadgeLabels,
            'registry_tag_labels' => $registryTagLabels,
            'unit_affiliation_label' => $unitAffiliationLabel,
        ];
    }

    /**
     * @param array<string, mixed> $community
     */
    public static function unitAffiliationSummary(array $community): string
    {
        $aff = $community['unit_affiliation'] ?? null;
        if (!is_array($aff)) {
            return '';
        }
        if (!empty($aff['is_real'])) {
            $labels = $aff['unit_labels'] ?? [];
            if (!is_array($labels) || $labels === []) {
                return '';
            }
            $country = trim((string) ($aff['country_label'] ?? ''));
            $prefix = $country !== '' ? $country . ' — ' : '';

            return $prefix . implode(', ', array_map('strval', $labels));
        }
        $fict = trim((string) ($aff['fictional_label'] ?? ''));

        return $fict !== '' ? 'Unité fictive : ' . $fict : '';
    }

    private function clip(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max);
    }

    private static function clipStatic(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max);
    }

    private static function excerptText(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, max(1, $max - 1))) . '…';
    }

    private function sanitizeUrl(string $url, int $maxLen): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (strlen($url) > $maxLen) {
            return '';
        }
        if (stripos($url, 'javascript:') !== false || preg_match('#^\s*data:#i', $url)) {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return '';
    }

    private function sanitizeEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || strlen($email) > 255) {
            return '';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $email;
    }

    /** @return list<array{label: string, title: string, body: string}> */
    private function parseMilitarySections(Request $request): array
    {
        $labels = $request->input('military_label', []);
        $titles = $request->input('military_title', []);
        $bodies = $request->input('military_body', []);
        if (!is_array($labels) || !is_array($titles) || !is_array($bodies)) {
            return [];
        }
        $out = [];
        $n = max(count($labels), count($titles), count($bodies));
        $defaults = ['PRIMO', 'SECUNDO', 'TERTIO', 'QUARTO', 'QUINTO', 'SEXTO'];
        for ($i = 0; $i < $n && $i < 12; $i++) {
            $label = isset($labels[$i]) ? $this->clip((string) $labels[$i], 32) : '';
            if ($label === '') {
                $label = $defaults[$i] ?? ('POINT ' . ($i + 1));
            }
            $title = isset($titles[$i]) ? $this->clip((string) $titles[$i], 200) : '';
            $body = isset($bodies[$i]) ? $this->clip((string) $bodies[$i], 4000) : '';
            if ($title === '' && $body === '') {
                continue;
            }
            $out[] = ['label' => $label, 'title' => $title, 'body' => $body];
        }

        return $out;
    }

    /** @return list<string> */
    private function parseStringList(mixed $raw, int $maxItems, int $maxLen): array
    {
        if (!is_string($raw)) {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n|,/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $t = trim((string) $line);
            if ($t === '') {
                continue;
            }
            $out[] = $this->clip($t, $maxLen);
            if (count($out) >= $maxItems) {
                break;
            }
        }

        return $out;
    }

    /** @return list<array{role_label: string, display_name: string, hint: string}> */
    private function parseCommandChain(Request $request): array
    {
        $roles = $request->input('cmd_role_label', []);
        $names = $request->input('cmd_display_name', []);
        $hints = $request->input('cmd_hint', []);
        if (!is_array($roles) || !is_array($names) || !is_array($hints)) {
            return [];
        }
        $n = min(max(count($roles), count($names), count($hints)), 8);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $rl = isset($roles[$i]) ? $this->clip((string) $roles[$i], 80) : '';
            $dn = isset($names[$i]) ? $this->clip((string) $names[$i], 120) : '';
            $hint = isset($hints[$i]) ? $this->clip((string) $hints[$i], 200) : '';
            if ($rl === '' && $dn === '' && $hint === '') {
                continue;
            }
            $out[] = ['role_label' => $rl, 'display_name' => $dn, 'hint' => $hint];
        }

        return $out;
    }

    /**
     * @param mixed $keys
     * @param mixed $vals
     * @return list<array<string, string>>
     */
    private function parsePairList(mixed $keys, mixed $vals, string $keyA, string $keyB, int $max, int $maxA, int $maxB): array
    {
        if (!is_array($keys) || !is_array($vals)) {
            return [];
        }
        $n = min(max(count($keys), count($vals)), $max);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $a = isset($keys[$i]) ? $this->clip((string) $keys[$i], $maxA) : '';
            $b = isset($vals[$i]) ? $this->clip((string) $vals[$i], $maxB) : '';
            if ($a === '' && $b === '') {
                continue;
            }
            $out[] = [$keyA => $a, $keyB => $b];
        }

        return $out;
    }

    /**
     * Liste ordonnée de prérequis (libellé libre + catégorie catalogue optionnelle).
     *
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    private function parsePrerequisitesFromRequest(Request $request): array
    {
        $labels = $request->input('public_prereq_label', []);
        $keys = $request->input('public_prereq_key', []);
        $statuses = $request->input('public_prereq_status', []);
        $details = $request->input('public_prereq_detail', []);

        // Ancien format (cases à cocher catalogue) — migration douce si le nouveau formulaire n’est pas envoyé
        if (!is_array($labels) || $labels === []) {
            return $this->parsePrerequisitesLegacyCheckbox($request);
        }

        if (!is_array($keys)) {
            $keys = [];
        }
        if (!is_array($statuses)) {
            $statuses = [];
        }
        if (!is_array($details)) {
            $details = [];
        }

        $allowed = array_flip(self::allowedPrerequisiteKeys());
        $catalog = self::prerequisiteCatalog();
        $n = min(max(count($labels), count($keys), count($statuses), count($details)), 16);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $label = isset($labels[$i]) ? $this->clip(trim((string) $labels[$i]), 160) : '';
            $k = isset($keys[$i]) ? strtolower(trim((string) $keys[$i])) : '';
            if ($k !== '' && !isset($allowed[$k])) {
                $k = '';
            }
            if ($label === '' && $k !== '') {
                $label = $catalog[$k]['label'] ?? '';
            }
            if ($label === '' && $k === '') {
                continue;
            }
            $statusRaw = $statuses[$i] ?? self::PREREQ_STATUS_REQUIRED;
            // Compat : anciens formulaires indexés par clé catalogue
            if (is_array($statuses) && $k !== '' && isset($statuses[$k]) && !isset($statuses[$i])) {
                $statusRaw = $statuses[$k];
            }
            $status = self::normalizePrerequisiteStatus($statusRaw);
            $detail = isset($details[$i]) ? $this->clip((string) $details[$i], 240) : '';
            if ($detail === '' && $k !== '' && is_array($details) && isset($details[$k]) && !isset($details[$i])) {
                $detail = $this->clip((string) $details[$k], 240);
            }
            $out[] = [
                'key' => $k,
                'label' => $label,
                'status' => $status,
                'detail' => $detail,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    private function parsePrerequisitesLegacyCheckbox(Request $request): array
    {
        $enabled = $request->input('public_prereq_enabled', []);
        if (!is_array($enabled) || $enabled === []) {
            return [];
        }
        $allowed = array_flip(self::allowedPrerequisiteKeys());
        $catalog = self::prerequisiteCatalog();
        $statuses = $request->input('public_prereq_status', []);
        $details = $request->input('public_prereq_detail', []);
        if (!is_array($statuses)) {
            $statuses = [];
        }
        if (!is_array($details)) {
            $details = [];
        }
        $out = [];
        foreach ($enabled as $key) {
            $k = is_string($key) ? strtolower(trim($key)) : '';
            if ($k === '' || !isset($allowed[$k])) {
                continue;
            }
            $status = self::normalizePrerequisiteStatus($statuses[$k] ?? self::PREREQ_STATUS_REQUIRED);
            $detail = isset($details[$k]) ? $this->clip((string) $details[$k], 240) : '';
            $out[] = [
                'key' => $k,
                'label' => $catalog[$k]['label'] ?? $k,
                'status' => $status,
                'detail' => $detail,
            ];
            if (count($out) >= 12) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<array{title: string, delay: string, body: string, highlight: bool}>
     */
    private function parseProcessStepsFromRequest(Request $request): array
    {
        $titles = $request->input('public_step_title', []);
        $delays = $request->input('public_step_delay', []);
        $bodies = $request->input('public_step_body', []);
        $highlights = $request->input('public_step_highlight', []);
        if (!is_array($titles)) {
            return [];
        }
        if (!is_array($delays)) {
            $delays = [];
        }
        if (!is_array($bodies)) {
            $bodies = [];
        }
        if (!is_array($highlights)) {
            $highlights = [];
        }
        $n = min(max(count($titles), count($delays), count($bodies), count($highlights)), 12);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $t = isset($titles[$i]) ? $this->clip((string) $titles[$i], 120) : '';
            $d = isset($delays[$i]) ? $this->clip((string) $delays[$i], 40) : '';
            $b = isset($bodies[$i]) ? $this->clip((string) $bodies[$i], 500) : '';
            if ($t === '' && $b === '') {
                continue;
            }
            $hl = $highlights[$i] ?? '0';
            $out[] = [
                'title' => $t,
                'delay' => $d,
                'body' => $b,
                'highlight' => $hl === true || $hl === 1 || (string) $hl === '1',
            ];
        }

        return $out;
    }

    /**
     * @return list<array{text: string, name: string, meta: string, initials: string}>
     */
    private function parseTestimonialsFromRequest(Request $request): array
    {
        $texts = $request->input('public_quote_text', []);
        $names = $request->input('public_quote_name', []);
        $metas = $request->input('public_quote_meta', []);
        $initials = $request->input('public_quote_initials', []);
        if (!is_array($texts) || !is_array($names)) {
            return [];
        }
        if (!is_array($metas)) {
            $metas = [];
        }
        if (!is_array($initials)) {
            $initials = [];
        }
        $n = min(max(count($texts), count($names)), 6);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $text = isset($texts[$i]) ? $this->clip((string) $texts[$i], 600) : '';
            $name = isset($names[$i]) ? $this->clip((string) $names[$i], 80) : '';
            $meta = isset($metas[$i]) ? $this->clip((string) $metas[$i], 120) : '';
            $ini = isset($initials[$i]) ? $this->clip((string) $initials[$i], 4) : '';
            if ($text === '' && $name === '') {
                continue;
            }
            $out[] = ['text' => $text, 'name' => $name, 'meta' => $meta, 'initials' => $ini];
        }

        return $out;
    }
}
