<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\MemberOnboardingService;
use App\Services\Community\TenantCommunityProfileService;
use App\Services\Community\TenantOnboardingHealthService;

final class OrganizationCommunityController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private TenantCommunityProfileService $communityProfileService
    ) {}

    public function settings(Request $request, array $params = []): Response
    {
        return Response::redirect(url('back-office/community'));
    }

    public function settingsUpdate(Request $request, array $params = []): Response
    {
        return Response::redirect(url('back-office/community'));
    }

    public function presentation(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        $registryCoverUrl = null;
        $coverFs = $slug !== '' ? base_path('public/assets/img/communities/' . $slug . '-cover.jpg') : '';
        if ($coverFs !== '' && is_file($coverFs)) {
            $registryCoverUrl = url('assets/img/communities/' . $slug . '-cover.jpg') . '?v=' . (int) filemtime($coverFs);
        }
        $navOpsImageUrl = null;
        $navResImageUrl = null;
        $opsFs = $slug !== '' ? base_path('public/assets/img/communities/' . $slug . '-nav-operations.jpg') : '';
        if ($opsFs !== '' && is_file($opsFs)) {
            $navOpsImageUrl = url('assets/img/communities/' . $slug . '-nav-operations.jpg') . '?v=' . (int) filemtime($opsFs);
        }
        $resFs = $slug !== '' ? base_path('public/assets/img/communities/' . $slug . '-nav-resources.jpg') : '';
        if ($resFs !== '' && is_file($resFs)) {
            $navResImageUrl = url('assets/img/communities/' . $slug . '-nav-resources.jpg') . '?v=' . (int) filemtime($resFs);
        }

        return Response::view('layout.main', [
            'title' => 'Fiche registre & contact',
            'content' => 'admin.organization.community_presentation',
            'tenant' => $tenant,
            'community' => $community,
            'registryCoverUrl' => $registryCoverUrl,
            'navOpsImageUrl' => $navOpsImageUrl,
            'navResImageUrl' => $navResImageUrl,
        ]);
    }

    public function presentationUpdate(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/community/presentation'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $existing = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $built = $this->communityProfileService->normalizeFromRequest($request, $existing);
        $this->tenantRepository->mergeSettings($tenantId, ['community' => $built]);

        $coverOutcome = $this->processRegistryCoverUpload($tenant, $request);
        $navOpsOutcome = $this->processNavigationImageUpload($tenant, $request, 'operations');
        $navResOutcome = $this->processNavigationImageUpload($tenant, $request, 'resources');
        if (is_string($coverOutcome) && str_starts_with($coverOutcome, '!!')) {
            Session::flash('error', 'Les réglages ont été enregistrés. ' . trim(substr($coverOutcome, 2)));

            return Response::redirect(url('back-office/community/presentation'));
        }
        foreach ([$navOpsOutcome, $navResOutcome] as $navOutcome) {
            if (is_string($navOutcome) && str_starts_with($navOutcome, '!!')) {
                Session::flash('error', 'Les réglages ont été enregistrés. ' . trim(substr($navOutcome, 2)));

                return Response::redirect(url('back-office/community/presentation'));
            }
        }
        $msg = 'Fiche registre et contact enregistrées.';
        if ($coverOutcome === 'uploaded') {
            $msg .= ' Image de carte du registre mise à jour.';
        } elseif ($coverOutcome === 'removed') {
            $msg .= ' Image de carte du registre retirée.';
        }
        if ($navOpsOutcome === 'uploaded') {
            $msg .= ' Image du menu Opérations mise à jour.';
        } elseif ($navOpsOutcome === 'removed') {
            $msg .= ' Image du menu Opérations retirée.';
        }
        if ($navResOutcome === 'uploaded') {
            $msg .= ' Image du menu Ressources mise à jour.';
        } elseif ($navResOutcome === 'removed') {
            $msg .= ' Image du menu Ressources retirée.';
        }
        Session::flash('success', $msg);

        return Response::redirect(url('back-office/community/presentation'));
    }

    /**
     * @return ''|'uploaded'|'removed'|string préfixée par « !! » (erreur utilisateur)
     */
    private function processRegistryCoverUpload(array $tenant, Request $request): string
    {
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        $dir = base_path('public/assets/img/communities');
        $dest = $dir . '/' . $slug . '-cover.jpg';

        $file = $_FILES['registry_cover'] ?? null;
        if (is_array($file)) {
            $fe = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fe !== UPLOAD_ERR_NO_FILE && $fe !== UPLOAD_ERR_OK) {
                return '!!Envoi du fichier impossible. Vérifiez la taille (maximum 3 Mo) et le format, puis réessayez.';
            }
        }
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ($slug === '' || !preg_match('/^[a-z0-9-]{1,50}$/', $slug)) {
                return '!!Définissez d’abord l’identifiant public de votre communauté (page Identité & code rejoindre), puis enregistrez à nouveau.';
            }
            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                return '!!Fichier reçu invalide. Réessayez.';
            }
            if ((int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
                return '!!Image trop volumineuse : limite de 3 Mo.';
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return '!!Format non pris en charge. Utilisez une image JPG, PNG ou WebP.';
            }
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return '!!Le stockage des images n’est pas disponible sur le serveur pour le moment.';
            }
            if (!$this->writeRegistryCoverAsJpeg($tmp, $dest)) {
                return '!!Impossible de traiter cette image. Essayez avec un autre fichier ou contactez l’hébergeur.';
            }

            return 'uploaded';
        }

        if ($request->input('remove_registry_cover') === '1' && $slug !== '' && is_file($dest)) {
            @unlink($dest);

            return 'removed';
        }

        return '';
    }

    /**
     * @return ''|'uploaded'|'removed'|string préfixée par « !! » (erreur utilisateur)
     */
    private function processNavigationImageUpload(array $tenant, Request $request, string $slot): string
    {
        if (!in_array($slot, ['operations', 'resources'], true)) {
            return '';
        }
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        $dir = base_path('public/assets/img/communities');
        $dest = $dir . '/' . $slug . '-nav-' . $slot . '.jpg';

        $file = $_FILES['nav_' . $slot . '_image'] ?? null;
        if (is_array($file)) {
            $fe = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fe !== UPLOAD_ERR_NO_FILE && $fe !== UPLOAD_ERR_OK) {
                return '!!Envoi du fichier impossible (' . $slot . '). Vérifiez la taille (maximum 3 Mo) et le format, puis réessayez.';
            }
        }
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ($slug === '' || !preg_match('/^[a-z0-9-]{1,50}$/', $slug)) {
                return '!!Définissez d’abord l’identifiant public de votre communauté avant d’ajouter une image de navigation.';
            }
            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                return '!!Fichier reçu invalide pour l’image de navigation.';
            }
            if ((int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
                return '!!Image de navigation trop volumineuse : limite de 3 Mo.';
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return '!!Format non pris en charge pour l’image de navigation. Utilisez JPG, PNG ou WebP.';
            }
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return '!!Le stockage des images de navigation n’est pas disponible sur le serveur pour le moment.';
            }
            if (!$this->writeRegistryCoverAsJpeg($tmp, $dest)) {
                return '!!Impossible de traiter cette image de navigation. Essayez avec un autre fichier.';
            }

            return 'uploaded';
        }

        if ($request->input('remove_nav_' . $slot . '_image') === '1' && $slug !== '' && is_file($dest)) {
            @unlink($dest);

            return 'removed';
        }

        return '';
    }

    private function writeRegistryCoverAsJpeg(string $tmpPath, string $destPath): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmpPath) ?: '';

            return $mime === 'image/jpeg' && @copy($tmpPath, $destPath);
        }
        $bin = @file_get_contents($tmpPath);
        if ($bin === false) {
            return false;
        }
        $im = @imagecreatefromstring($bin);
        if (!$im) {
            return false;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 1 || $h < 1) {
            imagedestroy($im);

            return false;
        }
        $maxW = 1800;
        if ($w > $maxW) {
            $newH = max(1, (int) round($h * ($maxW / $w)));
            $scaled = imagecreatetruecolor($maxW, $newH);
            if ($scaled === false) {
                imagedestroy($im);

                return false;
            }
            imagecopyresampled($scaled, $im, 0, 0, 0, 0, $maxW, $newH, $w, $h);
            imagedestroy($im);
            $im = $scaled;
        }
        $ok = @imagejpeg($im, $destPath, 86);
        imagedestroy($im);

        return $ok;
    }

    /** Assistant de rattrapage onboarding (communautés créées avant le parcours guidé complet). */
    public function onboardingRecovery(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('dashboard'));
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        $health = (new TenantOnboardingHealthService($this->tenantRepository))->analyze($tenantId);

        return Response::view('layout.main', [
            'title' => 'Aide après inscription',
            'content' => 'admin.organization.onboarding_recovery',
            'health' => $health,
            'tenant' => is_array($tenant) ? $tenant : [],
        ]);
    }

    public function onboardingRecoveryApply(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Rechargez la page et réessayez.');

            return Response::redirect(url('back-office/onboarding-recovery'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('dashboard'));
        }
        try {
            $service = new TenantOnboardingHealthService($this->tenantRepository);
            $before = $service->analyze($tenantId);
            if (!$before['can_auto_apply']) {
                Session::flash('success', 'Aucune action automatique n’était nécessaire. Consultez la checklist pour les étapes restantes.');

                return Response::redirect(url('back-office/onboarding-recovery'));
            }

            $applied = $service->applyFrDefaults($tenantId);
            $settings = $this->tenantRepository->getSettings($tenantId);
            if ((int) ($settings['onboarding_wizard_version'] ?? 0) < 2) {
                $this->tenantRepository->mergeSettings($tenantId, [
                    'onboarding_wizard_version' => 2,
                ]);
                $applied[] = 'Parcours de création finalisé pour la communauté.';
            }

            if ($applied === []) {
                Session::flash('success', 'Rien à modifier : votre configuration est déjà à jour sur les points traités automatiquement.');
            } else {
                Session::flash('success', implode(' ', $applied) . ' Les éléments déjà en place n’ont pas été supprimés.');
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'La mise à jour automatique n’a pas abouti. Réessayez ou complétez les étapes manuellement depuis la checklist.');
        }

        return Response::redirect(url('back-office/onboarding-recovery'));
    }

    /** Vue de suivi onboarding membres (cross-modules) pour le staff. */
    public function onboardingMembers(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('dashboard'));
        }
        $dashboard = (new MemberOnboardingService())->buildStaffDashboard($tenantId, 120);

        return Response::view('layout.main', [
            'title' => 'Onboarding membres',
            'content' => 'admin.organization.onboarding_members',
            'onboardingRows' => $dashboard['rows'],
            'onboardingKpis' => $dashboard['kpis'],
        ]);
    }
}
