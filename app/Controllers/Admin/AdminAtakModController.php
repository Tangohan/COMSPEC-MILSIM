<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;

class AdminAtakModController
{
    private const STORAGE_DIR = 'atak-mod';
    private const FILENAME = 'comspec-overwatch.zip';
    private const MAX_SIZE = 50 * 1024 * 1024; // 50 Mo
    private const REQUIRED_MOD_CPP = 'mod.cpp';
    private const REQUIRED_ADDONS = 'addons';

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
     * Vérifie que le zip contient mod.cpp et un dossier addons (ou équivalent).
     */
    private function validateAtakModZip(string $tmpPath): array
    {
        $errors = [];
        if (!class_exists(\ZipArchive::class)) {
            $errors[] = 'Extension PHP ZipArchive requise.';
            return $errors;
        }
        $zip = new \ZipArchive();
        if ($zip->open($tmpPath, \ZipArchive::RDONLY) !== true) {
            $errors[] = 'Fichier ZIP invalide ou corrompu.';
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
        if (!$hasModCpp) {
            $errors[] = 'L’archive doit contenir un fichier mod.cpp (à la racine ou dans un sous-dossier type @COMSPECOverwatch).';
        }
        if (!$hasAddons) {
            $errors[] = 'L’archive doit contenir un dossier addons (structure mod Arma).';
        }
        return $errors;
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $hasMod = $this->hasModForTenant($tenantId);
        $success = Session::getFlash('success');
        $error = Session::getFlash('error');
        $errors = Session::getFlash('errors') ?? [];

        return Response::view('layout.main', [
            'content' => 'admin.atak-mod.index',
            'title' => 'Mod ATAK — Administration',
            'hasMod' => $hasMod,
            'success' => $success,
            'error' => $error,
            'errors' => $errors,
        ]);
    }

    public function upload(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('admin/atak-mod'));
        }

        $file = $_FILES['mod_zip'] ?? null;
        $errors = [];

        if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Aucun fichier envoyé ou upload invalide.';
            Session::flash('errors', $errors);
            Session::flash('error', 'Envoi du fichier impossible.');
            return Response::redirect(url('admin/atak-mod'));
        }

        if ($file['size'] > self::MAX_SIZE) {
            $errors[] = 'Fichier trop volumineux (max 50 Mo).';
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Erreur d’upload (code ' . $file['error'] . ').';
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            $errors[] = 'Le fichier doit être une archive .zip.';
        }

        if (empty($errors)) {
            $validationErrors = $this->validateAtakModZip($file['tmp_name']);
            if (!empty($validationErrors)) {
                $errors = array_merge($errors, $validationErrors);
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('error', implode(' ', $errors));
            return Response::redirect(url('admin/atak-mod'));
        }

        $dir = $this->getStoragePath($tenantId);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                Session::flash('error', 'Impossible de créer le dossier de stockage.');
                return Response::redirect(url('admin/atak-mod'));
            }
        }
        $dest = $this->getModFilePath($tenantId);
        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            Session::flash('error', 'Impossible d’enregistrer le fichier.');
            return Response::redirect(url('admin/atak-mod'));
        }

        Session::flash('success', 'Mod ATAK enregistré. Il est disponible au téléchargement sur la page ATAK.');
        return Response::redirect(url('admin/atak-mod'));
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('admin/atak-mod'));
        }
        $path = $this->getModFilePath($tenantId);
        if (is_file($path)) {
            @unlink($path);
        }
        Session::flash('success', 'Mod ATAK supprimé.');
        return Response::redirect(url('admin/atak-mod'));
    }
}
