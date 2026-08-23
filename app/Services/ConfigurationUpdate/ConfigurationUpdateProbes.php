<?php

declare(strict_types=1);

namespace App\Services\ConfigurationUpdate;

use App\Core\Database;
use App\Repositories\TenantRepository;
use App\Services\Community\TenantCommunityProfileService;
use App\Services\Community\TenantTypeConfig;
use PDO;

/**
 * Éligibilité basée sur l’état réel des données (pas seulement la date de création).
 */
final class ConfigurationUpdateProbes
{
    public function __construct(
        private ?TenantRepository $tenants = null,
        private ?PDO $pdo = null,
    ) {
        $this->tenants ??= new TenantRepository();
        $this->pdo ??= Database::getPdo();
    }

    public function hasMilitaryAffiliation(int $tenantId): bool
    {
        $settings = $this->tenants->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $aff = $community['unit_affiliation'] ?? null;

        if (is_array($aff)) {
            // Format actuel : is_real + unit_ids / fictional_label
            if (array_key_exists('is_real', $aff)) {
                if (!empty($aff['is_real'])) {
                    $ids = $aff['unit_ids'] ?? [];
                    if (is_array($ids) && $ids !== []) {
                        return true;
                    }
                } else {
                    if (trim((string) ($aff['fictional_label'] ?? '')) !== '') {
                        return true;
                    }
                }
            }
            // Anciens formats éventuels
            $mode = strtolower(trim((string) ($aff['mode'] ?? $aff['type'] ?? '')));
            if (in_array($mode, ['real', 'fictional', 'fiction', 'independant', 'independent'], true)) {
                if ($mode === 'real') {
                    $ids = $aff['unit_ids'] ?? [];
                    if (is_array($ids) && $ids !== []) {
                        return true;
                    }
                } else {
                    return trim((string) ($aff['fictional_label'] ?? $aff['label'] ?? '')) !== '';
                }
            }
        } elseif (is_string($aff) && trim($aff) !== '') {
            return true;
        }

        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM tenant_military_unit_affiliations WHERE tenant_id = ? LIMIT 1'
            );
            $st->execute([$tenantId]);
            if ($st->fetchColumn()) {
                return true;
            }
        } catch (\Throwable) {
            // table absente → non satisfait
        }

        return false;
    }

    public function hasTimezone(int $tenantId): bool
    {
        $settings = $this->tenants->getSettings($tenantId);
        if (trim((string) ($settings['timezone'] ?? '')) !== '') {
            return true;
        }
        $tenant = $this->tenants->findById($tenantId) ?? [];

        return trim((string) ($tenant['default_timezone'] ?? '')) !== '';
    }

    public function hasGradeSystem(int $tenantId): bool
    {
        $settings = $this->tenants->getSettings($tenantId);

        return trim((string) ($settings['grade_system_code'] ?? '')) !== '';
    }

    public function hasRootUnit(int $tenantId): bool
    {
        try {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM units WHERE tenant_id = ? AND parent_id IS NULL');
            $st->execute([$tenantId]);

            return (int) $st->fetchColumn() >= 1;
        } catch (\Throwable) {
            return true; // schéma absent → ne pas harceler
        }
    }

    public function hasPublicProfileBasics(int $tenantId): bool
    {
        $settings = $this->tenants->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $welcome = trim((string) ($community['welcome_text'] ?? ''));
        $publicDoctrine = trim((string) ($community['public_doctrine'] ?? $community['doctrine_summary'] ?? ''));
        $tagline = trim((string) ($community['public_tagline'] ?? $community['tagline'] ?? ''));

        return $welcome !== '' || $publicDoctrine !== '' || $tagline !== '';
    }

    public function atakApplicable(int $tenantId): bool
    {
        $type = TenantTypeConfig::normalizeType(
            (string) ($this->tenants->getTenantType($tenantId) ?? TenantTypeConfig::TYPE_FULL)
        );
        if ($type === TenantTypeConfig::TYPE_EFFECTIFS) {
            return false;
        }
        if ($type === TenantTypeConfig::TYPE_ATAK) {
            return true;
        }
        // Communauté complète : seulement si une config ATAK existe déjà ou a été amorcée.
        // Ne pas proposer ATAK à toutes les communautés full par défaut.
        return $this->hasAtakConfig($tenantId);
    }

    public function hasAtakConfig(int $tenantId): bool
    {
        try {
            $st = $this->pdo->prepare('SELECT 1 FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * SSE applicable uniquement si ATAK l’est déjà pour ce tenant.
     * Module pont explicitement désactivé → non applicable.
     */
    public function ssePersonsApplicable(int $tenantId): bool
    {
        if (!$this->atakApplicable($tenantId)) {
            return false;
        }
        try {
            $modules = new \App\Services\Tactical\AtakBridgeModulesService();
            $state = $modules->get($tenantId);
            if (array_key_exists('sse_person', $state['modules'] ?? [])
                && empty($state['modules']['sse_person'])) {
                return false;
            }
        } catch (\Throwable) {
            // ignore
        }

        return true;
    }

    /**
     * Satisfait dès que le module est actif (défaut) — prêt sans action humaine.
     * Une fiche existante satisfait aussi (tenant historique déjà en usage).
     */
    public function hasSsePersonsConfig(int $tenantId): bool
    {
        try {
            $st = $this->pdo->prepare('SELECT 1 FROM sse_persons WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            if ((bool) $st->fetchColumn()) {
                return true;
            }
        } catch (\Throwable) {
            // schéma absent
        }

        try {
            $modules = new \App\Services\Tactical\AtakBridgeModulesService();
            $state = $modules->get($tenantId);

            return !empty($state['modules']['sse_person']);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Portail SSE classifié : même périmètre que les fiches personnes (ATAK + module).
     */
    public function ssePortalApplicable(int $tenantId): bool
    {
        return $this->ssePersonsApplicable($tenantId);
    }

    /**
     * Satisfait si un dossier ou un code d’accès a déjà été créé (ou markSatisfied pour tenant neuf).
     */
    public function hasSsePortalConfig(int $tenantId): bool
    {
        try {
            $st = $this->pdo->prepare('SELECT 1 FROM sse_cases WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            if ((bool) $st->fetchColumn()) {
                return true;
            }
        } catch (\Throwable) {
        }
        try {
            $st = $this->pdo->prepare('SELECT 1 FROM sse_access_codes WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            if ((bool) $st->fetchColumn()) {
                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /** Laboratoire numérique : même périmètre que le portail SSE. */
    public function sseDigitalLabApplicable(int $tenantId): bool
    {
        return $this->ssePortalApplicable($tenantId);
    }

    /**
     * Satisfait si un support a déjà été enregistré (prise en main du module)
     * ou si le tenant neuf a été marqué via markSatisfiedForNewTenant.
     */
    public function hasSseDigitalLabConfig(int $tenantId): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM sse_digital_devices WHERE tenant_id = ? AND deleted_at IS NULL LIMIT 1'
            );
            $st->execute([$tenantId]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            // Table absente avant migration : ne pas bloquer / ne pas afficher en erreur.
            return true;
        }
    }

    /**
     * Décision humaine prise : roleplay sauvegardé (reviewed), scramble activé, ou domaine seedé (tenant neuf).
     */
    public function hasAtakIntelScrambleDecision(int $tenantId): bool
    {
        try {
            $cfg = (new \App\Repositories\TenantAtakConfigRepository())->getRoleplayConfig($tenantId);
            if (!empty($cfg['intel_scramble_enabled']) || !empty($cfg['intel_scramble_reviewed'])) {
                return true;
            }
        } catch (\Throwable) {
        }
        try {
            $realism = new \App\Repositories\AtakRealismRepository();
            if ($realism->listCryptoDomains($tenantId) !== []) {
                return true;
            }
        } catch (\Throwable) {
            return true;
        }

        return false;
    }

    public function hasAtakPhotoHudReviewed(int $tenantId): bool
    {
        try {
            return (new \App\Services\Media\ReconPhotoHudService())->isReviewed($tenantId);
        } catch (\Throwable) {
            return true;
        }
    }

    public function registrationConfigured(int $tenantId): bool
    {
        $settings = $this->tenants->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $mode = TenantCommunityProfileService::normalizeRegistrationMode($community['registration_mode'] ?? null);

        return $mode !== '';
    }

    public function isMilsimRegistrationMode(int $tenantId): bool
    {
        $settings = $this->tenants->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $mode = TenantCommunityProfileService::normalizeRegistrationMode(
            $community['registration_mode'] ?? TenantCommunityProfileService::REGISTRATION_MODE_MILSIM
        );

        return $mode === TenantCommunityProfileService::REGISTRATION_MODE_MILSIM;
    }

    /**
     * Satisfait si le pack contient déjà des questions perso ou des règles de refus,
     * ou si le tenant a enregistré explicitement ces clés (même vides) après découverte.
     */
    public function hasReviewedEnlistmentCustomQuestions(int $tenantId): bool
    {
        $settings = $this->tenants->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $pack = is_array($community['enlistment_milsim'] ?? null) ? $community['enlistment_milsim'] : [];
        if ($pack === []) {
            return false;
        }
        if (array_key_exists('custom_questions', $pack) || array_key_exists('auto_refuse_rules', $pack)) {
            return true;
        }

        return false;
    }
}
