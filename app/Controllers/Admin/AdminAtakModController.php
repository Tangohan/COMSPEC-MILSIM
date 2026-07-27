<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAlertRepository;
use App\Services\Integrations\ModUpdateDiscordNotifier;
use ZipArchive;

class AdminAtakModController
{
    private const STORAGE_DIR = 'atak-mod';
    private const FILENAME = 'comspec-overwatch.zip';
    private const MAX_SIZE = 50 * 1024 * 1024; // 50 Mo
    private const MAX_CHANGELOG_NOTES = 4000;

    public function __construct(
        private ?ModUpdateDiscordNotifier $modDiscord = null,
    ) {
        $this->modDiscord ??= new ModUpdateDiscordNotifier();
    }

    private function getStoragePath(int $tenantId): string
    {
        $base = dirname(__DIR__, 2) . '/../storage/' . self::STORAGE_DIR;

        return $base . '/' . $tenantId;
    }

    private function getModFilePath(int $tenantId): string
    {
        return $this->getStoragePath($tenantId) . '/' . self::FILENAME;
    }

    public function hasModForTenant(int $tenantId): bool
    {
        $path = $this->getModFilePath($tenantId);

        return is_file($path) && is_readable($path);
    }

    /**
     * @return array{has_mod: bool, size_bytes: int|null, size_label: string|null, updated_at: string|null, version: string|null}
     */
    private function buildModMeta(int $tenantId): array
    {
        $path = $this->getModFilePath($tenantId);
        if (!is_file($path) || !is_readable($path)) {
            return [
                'has_mod' => false,
                'size_bytes' => null,
                'size_label' => null,
                'updated_at' => null,
                'version' => null,
            ];
        }
        $bytes = (int) filesize($path);
        $mtime = (int) filemtime($path);

        return [
            'has_mod' => true,
            'size_bytes' => $bytes,
            'size_label' => $this->formatBytes($bytes),
            'updated_at' => date('d/m/Y H:i', $mtime),
            'version' => $this->readVersionFromZip($path),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' o';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', ' ') . ' Ko';
        }

        return number_format($bytes / (1024 * 1024), 1, ',', ' ') . ' Mo';
    }

    private function readVersionFromZip(string $zipPath): ?string
    {
        if (!class_exists(ZipArchive::class)) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            return null;
        }
        $version = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            $norm = str_replace('\\', '/', $name);
            if (!preg_match('#(^|/)mod\.cpp$#i', $norm)) {
                continue;
            }
            $content = $zip->getFromIndex($i);
            if (!is_string($content) || $content === '') {
                continue;
            }
            if (preg_match('/version\s*=\s*"([^"]+)"/i', $content, $m)
                || preg_match('/version\s*=\s*\'([^\']+)\'/i', $content, $m)
                || preg_match('/version\s*=\s*([0-9][0-9.\-]*)/i', $content, $m)
            ) {
                $version = trim($m[1]);
            }
            break;
        }
        $zip->close();

        return $version !== null && $version !== '' ? $version : null;
    }

    /**
     * @return list<string>
     */
    private function validateAtakModZip(string $tmpPath): array
    {
        $errors = [];
        if (!class_exists(ZipArchive::class)) {
            $errors[] = 'Le serveur ne peut pas lire les archives ZIP pour le moment. Contactez un administrateur technique.';

            return $errors;
        }
        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::RDONLY) !== true) {
            $errors[] = 'Cette archive est illisible ou endommagée. Recompressez le pack Overwatch puis réessayez.';

            return $errors;
        }
        $hasModCpp = false;
        $hasAddons = false;
        $entryCount = $zip->numFiles;
        if ($entryCount === 0) {
            $errors[] = 'L’archive est vide.';
            $zip->close();

            return $errors;
        }
        for ($i = 0; $i < $entryCount; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            $norm = str_replace('\\', '/', $name);
            if (preg_match('#(^|/)mod\.cpp$#i', $norm)) {
                $hasModCpp = true;
            }
            if (preg_match('#(^|/)addons(/|$)#i', $norm)) {
                $hasAddons = true;
            }
        }
        $zip->close();
        if (!$hasModCpp || !$hasAddons) {
            $errors[] = 'Cette archive ne ressemble pas à un pack Overwatch complet. Vérifiez que vous déposez bien le dossier du pack compressé (structure habituelle du mod Arma).';
        }

        return $errors;
    }

    private function publishCommunityAlert(int $tenantId, ?string $version): void
    {
        try {
            $repo = new TenantAlertRepository();
            $title = 'Nouvelle version du pack Overwatch';
            $body = $version
                ? 'Une nouvelle version du pack Overwatch (' . $version . ') est disponible au téléchargement pour votre communauté.'
                : 'Une nouvelle version du pack Overwatch est disponible au téléchargement pour votre communauté.';
            $repo->insert($tenantId, [
                'kind' => 'info',
                'display_style' => 'classic',
                'title' => $title,
                'body' => $body,
                'cta_label' => 'Télécharger le pack',
                'cta_url' => url('atak/mod'),
                'coupon_code' => null,
                'accent_color' => '#059669',
                'icon_key' => 'megaphone',
                'starts_at' => date('Y-m-d H:i:s'),
                'ends_at' => date('Y-m-d H:i:s', time() + 14 * 24 * 3600),
                'sort_order' => 0,
                'is_active' => 1,
            ]);
        } catch (\Throwable) {
            // L’upload reste valide même si l’annonce échoue.
        }
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $meta = $this->buildModMeta($tenantId);

        return Response::view('layout.main', [
            'content' => 'admin.atak-mod.index',
            'title' => 'Pack Overwatch — Administration',
            'hasMod' => $meta['has_mod'],
            'modMeta' => $meta,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
            'errors' => Session::getFlash('errors') ?? [],
            'memberDownloadUrl' => url('atak/mod'),
            'discordRelayReady' => $this->modDiscord->hasWebhookConfigured($tenantId),
            'organizationSettingsUrl' => url('back-office/organisation/parametres'),
        ]);
    }

    public function upload(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide. Réessayez.');

            return Response::redirect(url('admin/atak-mod'));
        }

        $file = $_FILES['mod_zip'] ?? null;
        $errors = [];

        if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Aucun fichier reçu. Sélectionnez une archive ZIP du pack Overwatch.';
            Session::flash('errors', $errors);
            Session::flash('error', 'Envoi impossible.');

            return Response::redirect(url('admin/atak-mod'));
        }

        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            $errors[] = 'Le fichier dépasse la taille maximale autorisée (50 Mo).';
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'L’envoi a échoué. Réessayez avec une connexion stable.';
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            $errors[] = 'Déposez une archive ZIP du pack Overwatch.';
        }

        if ($errors === []) {
            $errors = array_merge($errors, $this->validateAtakModZip((string) $file['tmp_name']));
        }

        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flash('error', implode(' ', $errors));

            return Response::redirect(url('admin/atak-mod'));
        }

        $dir = $this->getStoragePath($tenantId);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            Session::flash('error', 'Impossible d’enregistrer le pack pour le moment.');

            return Response::redirect(url('admin/atak-mod'));
        }
        $dest = $this->getModFilePath($tenantId);
        if (!@move_uploaded_file((string) $file['tmp_name'], $dest)) {
            Session::flash('error', 'Impossible d’enregistrer le pack pour le moment.');

            return Response::redirect(url('admin/atak-mod'));
        }

        $version = $this->readVersionFromZip($dest);
        $this->publishCommunityAlert($tenantId, $version);

        $changelogNotes = trim((string) $request->input('changelog_notes', ''));
        if (mb_strlen($changelogNotes) > self::MAX_CHANGELOG_NOTES) {
            $changelogNotes = mb_substr($changelogNotes, 0, self::MAX_CHANGELOG_NOTES);
        }

        $notifyDiscord = $request->input('notify_discord') === '1'
            || $request->input('notify_discord') === 'on';
        $discordSuffix = '';
        if ($notifyDiscord) {
            $discordResult = $this->modDiscord->notifyModUpdate(
                $tenantId,
                $version,
                url('atak/mod'),
                $changelogNotes !== '' ? $changelogNotes : null,
                $dest,
            );
            if ($discordResult['sent']) {
                $discordSuffix = ' Une annonce a aussi été envoyée sur Discord.';
            } elseif ($discordResult['skipped']) {
                $discordSuffix = ' Aucun relais Discord n’est configuré : l’annonce Discord a été ignorée.';
            } else {
                $discordSuffix = ' L’annonce Discord n’a pas pu être envoyée (le pack reste bien publié).';
            }
        }

        Session::flash(
            'success',
            'Pack Overwatch enregistré. Il est disponible pour les membres, et une annonce a été publiée pour la communauté.'
            . $discordSuffix
        );

        return Response::redirect(url('admin/atak-mod'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide. Réessayez.');

            return Response::redirect(url('admin/atak-mod'));
        }
        $path = $this->getModFilePath($tenantId);
        if (is_file($path)) {
            @unlink($path);
        }
        Session::flash('success', 'Pack Overwatch retiré. Les membres ne peuvent plus le télécharger.');

        return Response::redirect(url('admin/atak-mod'));
    }
}
