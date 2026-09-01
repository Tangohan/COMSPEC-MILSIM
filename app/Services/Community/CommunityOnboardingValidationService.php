<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Authorization\TenantPermissionCatalog;

/**
 * Valide le payload wizard de création de communauté (avant persistance).
 */
final class CommunityOnboardingValidationService
{
    public const GRADE_SYSTEMS = ['FR_CLASSIC', 'US_CLASSIC'];

    /** @return list<string> */
    public static function wizardAssignableSlugs(): array
    {
        return TenantPermissionCatalog::allSlugs();
    }

    /** Rôles système : identifiants réservés (pas de doublon). */
    public const RESERVED_ROLE_SLUGS = [
        'community_owner',
        'tenant_admin',
        'member',
        'officer',
        'forum_moderator',
        'hr',
        'invite',
    ];

    public const MAX_CUSTOM_WIZARD_ROLES = 15;

    /**
     * Libellés pour cases à cocher (assistant création communauté).
     *
     * @return array<string, list<array{slug: string, label: string}>>
     */
    public static function wizardPermissionFieldGroups(): array
    {
        $moduleLabels = [
            'admin' => 'Administration',
            'forum' => 'Forum',
            'documents' => 'Documents',
            'training' => 'Formations',
            'personnel' => 'Membres / RH',
            'comms' => 'Communication / notifications',
            'courrier' => 'Courrier',
        ];
        $out = [];
        foreach (TenantPermissionCatalog::definitions() as $d) {
            $label = $moduleLabels[$d['module']] ?? ucfirst((string) $d['module']);
            $out[$label] ??= [];
            $out[$label][] = ['slug' => $d['slug'], 'label' => $d['name']];
        }

        return $out;
    }

    /** @return array{ok: bool, errors: list<string>, step?: string, normalized?: array<string, mixed>} */
    public function validate(array $wizard): array
    {
        $errors = [];
        $step = null;

        $code = strtoupper(trim((string) ($wizard['grade_system_code'] ?? '')));
        if ($code === 'CUSTOM') {
            $code = 'FR_CLASSIC';
            $wizard['grade_system_code'] = $code;
        }
        if (!in_array($code, self::GRADE_SYSTEMS, true)) {
            $errors[] = 'Choisissez un référentiel de grades (modèle français ou américain).';
            $step = $step ?? 'grades';
        }

        $tz = trim((string) ($wizard['timezone'] ?? ''));
        if ($tz === '') {
            $errors[] = 'Le fuseau horaire est obligatoire.';
            $step = $step ?? 'identity';
        } elseif (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $errors[] = 'Fuseau horaire invalide.';
            $step = $step ?? 'identity';
        }

        $locale = trim((string) ($wizard['default_locale'] ?? 'fr'));
        if ($locale === '' || strlen($locale) > 10) {
            $errors[] = 'Langue par défaut invalide.';
            $step = $step ?? 'identity';
        }

        $affErr = $this->validateUnitAffiliation($wizard);
        if ($affErr !== []) {
            $errors = array_merge($errors, $affErr);
            $step = $step ?? 'identity';
        }

        $orbat = trim((string) ($wizard['orbat_visibility'] ?? 'members'));
        $allowedVis = ['public', 'members', 'command'];
        if (!in_array($orbat, $allowedVis, true)) {
            $errors[] = 'Visibilité ORBAT invalide.';
            $step = $step ?? 'review';
        }

        $units = $wizard['units'] ?? null;
        if (!is_array($units)) {
            $errors[] = 'Structure organisationnelle manquante ou invalide.';
            $step = $step ?? 'organization';

            return ['ok' => false, 'errors' => $errors, 'step' => $step];
        }

        $unitErr = $this->validateUnits($units);
        if ($unitErr !== []) {
            $errors = array_merge($errors, $unitErr);
            $step = $step ?? 'organization';
        }

        $founderGradeId = (int) ($wizard['founder_grade_id'] ?? 0);
        if ($founderGradeId < 1) {
            $errors[] = 'Sélectionnez un grade pour le compte fondateur.';
            $step = $step ?? 'grades';
        }

        $rolesTemplate = trim((string) ($wizard['roles_template'] ?? 'quick'));
        if (!in_array($rolesTemplate, ['quick', 'standard'], true)) {
            $errors[] = 'Modèle de rôles invalide.';
            $step = $step ?? 'roles';
        }

        $customRoles = $wizard['custom_roles'] ?? [];
        if (!is_array($customRoles)) {
            $customRoles = [];
        }
        $customErr = $this->validateCustomRoles($customRoles);
        if ($customErr !== []) {
            $errors = array_merge($errors, $customErr);
            $step = $step ?? 'roles';
        }

        $kitCode = trim((string) ($wizard['catalog_kit_code'] ?? $wizard['wizard_catalog_kit_code'] ?? ''));
        if ($kitCode === 'none') {
            $kitCode = '';
        }
        $allowedKits = \App\Services\OrganizationCatalog\OrganizationKitDefinitions::officialCodes();
        if ($kitCode !== '' && !in_array($kitCode, $allowedKits, true)) {
            $errors[] = 'Choisissez un modèle d’organisation reconnu, ou aucun.';
            $step = $step ?? 'roles';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'step' => $step];
        }

        $normalized = [
            'grade_system_code' => $code,
            'timezone' => $tz,
            'default_locale' => $locale,
            'orbat_visibility' => $orbat,
            'units' => $this->normalizeUnits($units),
            'founder_grade_id' => $founderGradeId,
            'roles_template' => $rolesTemplate,
            'catalog_kit_code' => $kitCode,
            'grade_overrides' => $this->normalizeGradeOverrides($wizard['grade_overrides'] ?? []),
            'community_profile' => $this->normalizeCommunityProfile($wizard),
            'custom_roles' => $this->normalizeCustomRoles($customRoles),
        ];

        return ['ok' => true, 'errors' => [], 'normalized' => $normalized];
    }

    /**
     * @param list<mixed> $roles
     * @return list<string>
     */
    private function validateCustomRoles(array $roles): array
    {
        $errors = [];
        if (count($roles) > self::MAX_CUSTOM_WIZARD_ROLES) {
            $errors[] = 'Trop de rôles supplémentaires (maximum ' . self::MAX_CUSTOM_WIZARD_ROLES . ').';

            return $errors;
        }
        $allowedPerm = array_flip(self::wizardAssignableSlugs());
        $reserved = array_flip(self::RESERVED_ROLE_SLUGS);
        $seenSlugs = [];
        foreach ($roles as $i => $r) {
            if (!is_array($r)) {
                continue;
            }
            $name = trim((string) ($r['name'] ?? ''));
            $slug = trim((string) ($r['slug'] ?? ''));
            if ($name === '' && $slug === '') {
                continue;
            }
            if ($name === '' || mb_strlen($name) > 80) {
                $errors[] = 'Rôle supplémentaire #' . ($i + 1) . ' : nom requis (80 caractères max).';
            }
            if ($slug === '' || !preg_match('/^[a-z][a-z0-9_]{1,48}$/', $slug)) {
                $errors[] = 'Rôle supplémentaire « ' . $name . ' » : identifiant technique invalide (lettres minuscules, chiffres, tirets bas).';
            } elseif (isset($reserved[$slug])) {
                $errors[] = 'L’identifiant « ' . $slug . ' » est réservé par le système.';
            } elseif (isset($seenSlugs[$slug])) {
                $errors[] = 'Identifiant de rôle dupliqué : ' . $slug . '.';
            } else {
                $seenSlugs[$slug] = true;
            }
            $perms = $r['permission_slugs'] ?? [];
            if (!is_array($perms)) {
                $perms = [];
            }
            foreach ($perms as $ps) {
                $p = is_string($ps) ? trim($ps) : '';
                if ($p === '') {
                    continue;
                }
                if (!isset($allowedPerm[$p])) {
                    $errors[] = 'Permission inconnue ou non autorisée pour le rôle « ' . $name . ' » : ' . $p . '.';
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<mixed> $roles
     * @return list<array{name: string, slug: string, permission_slugs: list<string>}>
     */
    private function normalizeCustomRoles(array $roles): array
    {
        $allowedPerm = array_flip(self::wizardAssignableSlugs());
        $out = [];
        foreach ($roles as $r) {
            if (!is_array($r)) {
                continue;
            }
            $name = trim((string) ($r['name'] ?? ''));
            $slug = trim((string) ($r['slug'] ?? ''));
            if ($name === '' || $slug === '') {
                continue;
            }
            $perms = $r['permission_slugs'] ?? [];
            if (!is_array($perms)) {
                $perms = [];
            }
            $clean = [];
            foreach ($perms as $ps) {
                $p = is_string($ps) ? trim($ps) : '';
                if ($p !== '' && isset($allowedPerm[$p])) {
                    $clean[$p] = true;
                }
            }

            $out[] = [
                'name' => $name,
                'slug' => $slug,
                'permission_slugs' => array_keys($clean),
            ];
        }

        return $out;
    }

    /**
     * Identité / registre / pack recrutement (fusion dans settings.community + logo tenant).
     *
     * @return array<string, mixed>
     */
    private function normalizeCommunityProfile(array $wizard): array
    {
        $out = [];

        $logo = trim((string) ($wizard['wizard_logo_url'] ?? ''));
        if ($logo !== '') {
            if (strlen($logo) > 500) {
                $logo = substr($logo, 0, 500);
            }
            if (filter_var($logo, FILTER_VALIDATE_URL)) {
                $out['logo_url'] = $logo;
            }
        }

        $banner = trim((string) ($wizard['wizard_public_banner_url'] ?? ''));
        if ($banner !== '') {
            if (strlen($banner) > 500) {
                $banner = substr($banner, 0, 500);
            }
            if (filter_var($banner, FILTER_VALIDATE_URL)) {
                $out['public_banner_url'] = $banner;
            }
        }

        $pm = (string) ($wizard['wizard_presentation_mode'] ?? 'simple');
        $out['presentation_mode'] = $pm === 'military' ? 'military' : 'simple';

        $badges = $wizard['wizard_style_badges'] ?? [];
        if (!is_array($badges)) {
            $badges = [];
        }
        $allowed = array_flip(TenantCommunityProfileService::allowedBadgeSlugs());
        $out['style_badges'] = array_values(array_filter(array_map(static function ($s) use ($allowed) {
            $k = is_string($s) ? strtolower(trim($s)) : '';

            return isset($allowed[$k]) ? $k : null;
        }, $badges)));

        $out['simple_body'] = $this->clip((string) ($wizard['wizard_simple_body'] ?? ''), 8000);
        $out['public_about_body'] = $this->clip((string) ($wizard['wizard_public_about_body'] ?? ''), 8000);
        $out['expectations'] = $this->clip((string) ($wizard['wizard_expectations'] ?? ''), 8000);
        $out['game_label'] = $this->clip((string) ($wizard['wizard_game_label'] ?? ''), 120);

        $wm = $wizard['wizard_milsim'] ?? null;
        $frag = \App\Services\Community\EnlistmentMilsimPackService::mergeWizardMilsimInput(is_array($wm) ? $wm : null);
        if ($frag !== null) {
            $out['enlistment_milsim'] = $frag;
        }
        $json = trim((string) ($wizard['wizard_enlistment_milsim_json'] ?? ''));
        if ($json !== '') {
            $d = json_decode($json, true);
            if (is_array($d)) {
                $out['enlistment_milsim'] = array_merge(is_array($out['enlistment_milsim'] ?? null) ? $out['enlistment_milsim'] : [], $d);
            }
        }

        $aff = $this->normalizeUnitAffiliation($wizard);
        if ($aff !== null) {
            $out['unit_affiliation'] = $aff;
            if (!empty($aff['is_real'])) {
                $tags = is_array($out['registry_tags'] ?? null) ? $out['registry_tags'] : [];
                if (!in_array('soar', $tags, true)) {
                    $tags[] = 'soar';
                }
                $out['registry_tags'] = array_values(array_unique($tags));
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function validateUnitAffiliation(array $wizard): array
    {
        $errors = [];
        $raw = $wizard['wizard_represents_real_unit'] ?? null;
        if ($raw === null || $raw === '') {
            $errors[] = 'Indiquez si votre communauté représente une unité réelle.';

            return $errors;
        }
        $isReal = in_array((string) $raw, ['1', 'yes', 'true', 'on'], true);
        if (!$isReal) {
            $label = trim((string) ($wizard['wizard_fictional_unit_label'] ?? ''));
            if ($label === '' || mb_strlen($label) < 2) {
                $errors[] = 'Précisez quelle unité fictive votre communauté représente.';
            } elseif (mb_strlen($label) > 200) {
                $errors[] = 'Le nom de l’unité fictive est trop long (200 caractères max).';
            }

            return $errors;
        }

        $country = strtoupper(trim((string) ($wizard['wizard_real_unit_country'] ?? '')));
        if (!in_array($country, RealUnitAffiliationCatalog::allowedCountryCodes(), true)) {
            $errors[] = 'Sélectionnez le pays de l’unité réelle représentée.';

            return $errors;
        }

        $ids = $wizard['wizard_real_unit_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $cleanIds = [];
        foreach ($ids as $id) {
            if (is_string($id) && trim($id) !== '') {
                $cleanIds[] = trim($id);
            }
        }
        $cleanIds = array_values(array_unique($cleanIds));
        if ($cleanIds === []) {
            $errors[] = 'Sélectionnez au moins une unité réelle dans la liste proposée.';

            return $errors;
        }

        $resolved = RealUnitAffiliationCatalog::resolveSelectedUnits($country, $cleanIds);
        if ($resolved === []) {
            $errors[] = 'Les unités sélectionnées ne sont pas valides pour le pays choisi.';
        } elseif (count($resolved) !== count($cleanIds)) {
            $errors[] = 'Certaines unités sélectionnées ne correspondent pas au pays choisi.';
        }

        return $errors;
    }

    /**
     * @return array{is_real: bool, fictional_label: ?string, country: ?string, country_label: ?string, unit_ids: list<string>, unit_labels: list<string>}|null
     */
    private function normalizeUnitAffiliation(array $wizard): ?array
    {
        $raw = $wizard['wizard_represents_real_unit'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        $isReal = in_array((string) $raw, ['1', 'yes', 'true', 'on'], true);
        if (!$isReal) {
            $label = trim((string) ($wizard['wizard_fictional_unit_label'] ?? ''));

            return [
                'is_real' => false,
                'fictional_label' => $this->clip($label, 200),
                'country' => null,
                'country_label' => null,
                'unit_ids' => [],
                'unit_labels' => [],
            ];
        }

        $country = strtoupper(trim((string) ($wizard['wizard_real_unit_country'] ?? '')));
        $ids = $wizard['wizard_real_unit_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $cleanIds = [];
        foreach ($ids as $id) {
            if (is_string($id) && trim($id) !== '') {
                $cleanIds[] = trim($id);
            }
        }
        $resolved = RealUnitAffiliationCatalog::resolveSelectedUnits($country, $cleanIds);
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

    private function clip(string $s, int $max): string
    {
        if (function_exists('mb_strlen') && mb_strlen($s) > $max) {
            return mb_substr($s, 0, $max);
        }
        if (strlen($s) > $max) {
            return substr($s, 0, $max);
        }

        return $s;
    }

    /** @param list<array<string, mixed>> $units */
    private function validateUnits(array $units): array
    {
        $errors = [];
        if ($units === []) {
            return ['Ajoutez au moins une unité racine (organisme, groupe ou équivalent).'];
        }

        $allowedTypes = ['group', 'section', 'team', 'squad'];
        $keys = [];
        $slugs = [];
        foreach ($units as $i => $u) {
            if (!is_array($u)) {
                $errors[] = 'Unité #' . ($i + 1) . ' : format invalide.';

                continue;
            }
            $key = trim((string) ($u['key'] ?? ''));
            if ($key === '') {
                $errors[] = 'Chaque unité doit avoir un identifiant interne (key).';

                continue;
            }
            if (isset($keys[$key])) {
                $errors[] = 'Identifiant d’unité dupliqué : ' . $key . '.';
            }
            $keys[$key] = true;

            $name = trim((string) ($u['name'] ?? ''));
            if ($name === '') {
                $errors[] = 'Nom d’unité requis pour « ' . $key . ' ».';
            }
            $slug = trim((string) ($u['slug'] ?? ''));
            if ($slug === '' || !preg_match('/^[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?$/', $slug)) {
                $errors[] = 'Slug d’unité invalide pour « ' . $key . ' » (minuscules, chiffres, tirets).';
            } elseif (isset($slugs[$slug])) {
                $errors[] = 'Slug d’unité dupliqué : ' . $slug . '.';
            } else {
                $slugs[$slug] = true;
            }
            $type = trim((string) ($u['type'] ?? ''));
            if (!in_array($type, $allowedTypes, true)) {
                $errors[] = 'Type d’unité non reconnu pour « ' . $key . ' » (group, section, team, squad).';
            }
        }

        $roots = 0;
        $byKey = [];
        foreach ($units as $u) {
            if (!is_array($u)) {
                continue;
            }
            $k = trim((string) ($u['key'] ?? ''));
            $pk = trim((string) ($u['parent_key'] ?? ''));
            if ($pk === '') {
                $roots++;
            }
            $byKey[$k] = $u;
        }
        if ($roots < 1) {
            $errors[] = 'Définissez au moins une unité sans parent (racine ORBAT).';
        }

        foreach ($units as $u) {
            if (!is_array($u)) {
                continue;
            }
            $pk = trim((string) ($u['parent_key'] ?? ''));
            if ($pk === '') {
                continue;
            }
            if (!isset($byKey[$pk])) {
                $errors[] = 'Unité parente inconnue : « ' . $pk . ' ».';

                continue;
            }
        }

        if ($this->hasCycle($units)) {
            $errors[] = 'La hiérarchie des unités contient une boucle ; vérifiez les parents.';
        }

        return $errors;
    }

    /** @param list<array<string, mixed>> $units */
    private function hasCycle(array $units): bool
    {
        $byKey = [];
        foreach ($units as $u) {
            if (!is_array($u)) {
                continue;
            }
            $k = trim((string) ($u['key'] ?? ''));
            if ($k !== '') {
                $byKey[$k] = trim((string) ($u['parent_key'] ?? ''));
            }
        }
        foreach ($byKey as $start => $_) {
            $seen = [];
            $cur = $start;
            for ($n = 0; $n <= count($byKey) + 1; $n++) {
                if ($cur === '' || !isset($byKey[$cur])) {
                    break;
                }
                if (isset($seen[$cur])) {
                    return true;
                }
                $seen[$cur] = true;
                $cur = $byKey[$cur];
            }
        }

        return false;
    }

    /** @param list<array<string, mixed>> $units */
    private function normalizeUnits(array $units): array
    {
        $out = [];
        foreach ($units as $u) {
            if (!is_array($u)) {
                continue;
            }
            $out[] = [
                'key' => trim((string) ($u['key'] ?? '')),
                'parent_key' => trim((string) ($u['parent_key'] ?? '')),
                'name' => trim((string) ($u['name'] ?? '')),
                'slug' => trim((string) ($u['slug'] ?? '')),
                'type' => trim((string) ($u['type'] ?? 'group')),
                'display_order' => (int) ($u['display_order'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<array{grade_id: int, label_short: ?string, label_long: ?string, sort_order: ?int, is_enabled: bool}>
     */
    private function normalizeGradeOverrides($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $gid = (int) ($row['grade_id'] ?? 0);
            if ($gid < 1) {
                continue;
            }
            $out[] = [
                'grade_id' => $gid,
                'label_short' => isset($row['label_short']) ? trim((string) $row['label_short']) : null,
                'label_long' => isset($row['label_long']) ? trim((string) $row['label_long']) : null,
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : null,
                'is_enabled' => !isset($row['is_enabled']) || (bool) $row['is_enabled'],
            ];
        }

        return $out;
    }
}
