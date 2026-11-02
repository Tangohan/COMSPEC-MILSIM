<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAdminSettingsRepository;
use App\Services\Doctrine\DoctrineLibraryService;
use App\Services\Doctrine\DoctrineReferential;
use App\Services\Doctrine\EchelonCatalog;
use App\Services\Doctrine\OrderFormatCatalog;
use App\Services\Doctrine\TrainingPipelineCatalog;
use App\Support\DoctrineLibraryFileStorage;
use InvalidArgumentException;
use Throwable;

/**
 * Doctrine › Référentiel : catalogue des publications de référence (France, États-Unis, OTAN, Allemagne… fourni par le
 * site, en accès libre uniquement) personnalisé par la communauté — références ajoutées, références du site masquées,
 * catégories propres, rattachement d’une référence à un document de doctrine — puis, plus bas, le choix US / FR / les
 * deux qui filtre les trames d’ordres, les échelons et les parcours proposés (paramètres de la communauté).
 */
final class DoctrineReferentialController
{
    private const BACK = 'back-office/doctrine/referentiel';

    public function __construct(
        private ?TenantAdminSettingsRepository $adminSettings = null,
        private ?DoctrineLibraryService $library = null,
    ) {
        $this->adminSettings ??= new TenantAdminSettingsRepository();
        $this->library ??= new DoctrineLibraryService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('dashboard'));
        }

        $referential = $this->currentReferential($tenantId);
        $gate = Gate::getInstance();

        return Response::view('layout.main', [
            'content' => 'admin.organization.doctrine_referential',
            'title' => 'Référentiel doctrinal',
            'backOfficePageCss' => ['doctrine-hub.css', 'doctrine-library.css'],
            'boSkipPageHead' => true,
            'library' => $this->library->overview($tenantId),
            'doctrineReferential' => $referential,
            'doctrineReferentialLabels' => DoctrineReferential::labels(),
            'doctrineReferentialDescriptions' => DoctrineReferential::descriptions(),
            'doctrineOrderFormats' => OrderFormatCatalog::forReferential($referential),
            'doctrineEchelons' => EchelonCatalog::forReferential($referential),
            'doctrineTraining' => TrainingPipelineCatalog::forReferential($referential),
            'doctrineCanEdit' => $this->canEdit(),
            'can' => [
                'create' => $gate->allows('doctrine.create') || $gate->allows('documents.create') || $gate->allows('admin.organization') || $gate->allows('admin.access'),
                'manage' => $this->canEdit(),
                'compliance' => $gate->allows('doctrine.view_compliance') || $this->canEdit(),
                'edit' => $this->canEdit(),
            ],
            'csrf_token' => Csrf::token(),
        ]);
    }

    /** Choix du référentiel US / FR / les deux (trames, échelons, parcours proposés). */
    public function update(Request $request, array $params = []): Response
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $chosen = DoctrineReferential::sanitize($request->input('referential'));
        $settings = $this->adminSettings->getForTenant($tenantId);
        $settings['doctrine']['referential'] = $chosen;
        $this->adminSettings->saveForTenant($tenantId, $settings);

        Session::flash('success', 'Référentiel doctrinal enregistré : ' . DoctrineReferential::label($chosen)
            . '. Les documents déjà rédigés ne sont pas modifiés.');

        return Response::redirect(url(self::BACK) . '#trames');
    }

    /** Ajoute ou modifie une référence de la communauté (organisation, code, titre, domaine, liens, fichier PDF). */
    public function saveEntry(Request $request, array $params = []): Response
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->inputString('entry_id', '0');
        try {
            $values = DoctrineLibraryService::normalizeEntry([
                'organization' => $request->input('organization', ''), 'code' => $request->input('code', ''),
                'title' => $request->input('title', ''), 'title_translation' => $request->input('title_translation', ''),
                'lang' => $request->input('lang', 'fr'), 'domain' => $request->input('domain', ''),
                'type' => $request->input('type', ''), 'level' => $request->input('level', ''),
                'summary' => $request->input('summary', ''),
                'link_url' => $request->input('link_url', []), 'link_label' => $request->input('link_label', []),
            ]);
            $current = $id > 0 ? $this->library->repository()->findEntry($tenantId, $id) : null;
            $stored = DoctrineLibraryFileStorage::storeFromUpload($tenantId, $_FILES['file'] ?? null);
            if ($stored['error'] !== null) {
                throw new InvalidArgumentException($stored['error']);
            }
            if ($stored['path'] !== null) {
                $values['file_path'] = $stored['path'];
                $values['file_name'] = $stored['name'];
            } elseif ($request->input('remove_file') === '1') {
                $values['file_path'] = '';
            }
            $id = $this->library->repository()->saveEntry($tenantId, $this->userId(), $id, $values);
            Session::flash('success', $current === null ? 'Référence ajoutée au référentiel de la communauté.' : 'Référence modifiée.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url(self::BACK));
        } catch (Throwable $e) {
            error_log('Doctrine library save: ' . $e->getMessage());
            Session::flash('error', 'Enregistrement impossible pour le moment.');

            return Response::redirect(url(self::BACK));
        }

        return Response::redirect(url(self::BACK) . '#ref-' . DoctrineLibraryService::customKey($id));
    }

    /** Archive (ou réactive avec restore=1) une référence ajoutée par la communauté. */
    public function archiveEntry(Request $request, array $params = []): Response
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $restore = $request->input('restore') === '1';
        $id = (int) ($params['id'] ?? 0);

        return $this->attempt(function () use ($id, $restore): void {
            $this->library->repository()->setEntryArchived((int) Session::get('tenant_id'), $this->userId(), $id, !$restore);
        }, $restore ? 'Référence réactivée.' : 'Référence archivée : elle quitte la liste, rien n’est supprimé.', DoctrineLibraryService::customKey($id));
    }

    /** Masque (hidden=1) ou réaffiche (hidden=0) une référence du catalogue du site pour la communauté. */
    public function toggleHidden(Request $request, array $params = []): Response
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $key = $request->inputString('key', '');
        $hide = $request->input('hidden') === '1';
        if (DoctrineLibraryService::customId($key) > 0 || !$this->library->knows($tenantId, $key)) {
            Session::flash('error', 'Référence introuvable.');

            return Response::redirect(url(self::BACK));
        }

        return $this->attempt(function () use ($tenantId, $key, $hide): void {
            $this->library->repository()->setHidden($tenantId, $this->userId(), $key, $hide);
        }, $hide ? 'Référence masquée pour votre communauté. Elle reste dans le catalogue du site : vous pouvez la réafficher.' : 'Référence de nouveau affichée.', $key);
    }

    /** Crée ou renomme une catégorie de la communauté. */
    public function saveCategory(Request $request, array $params = []): Response
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $id = (int) $request->inputString('category_id', '0');

        return $this->attempt(function () use ($request, $id): void {
            $c = DoctrineLibraryService::normalizeCategory($request->input('label', ''), $request->input('color', ''));
            $this->library->repository()->saveCategory((int) Session::get('tenant_id'), $this->userId(), $id, $c['label'], $c['color']);
        }, $id > 0 ? 'Catégorie modifiée.' : 'Catégorie créée.', '', 'categories');
    }

    public function archiveCategory(Request $request, array $params = []): Response
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $restore = $request->input('restore') === '1';
        $id = (int) ($params['id'] ?? 0);

        return $this->attempt(function () use ($id, $restore): void {
            $this->library->repository()->setCategoryArchived((int) Session::get('tenant_id'), $this->userId(), $id, !$restore);
        }, $restore ? 'Catégorie réactivée.' : 'Catégorie archivée : ses références restent, sans catégorie affichée dans le rail.', '', 'categories');
    }

    /** Rattache une référence à un document de doctrine de la communauté (unlink=1 : retire le rattachement). */
    public function link(Request $request, array $params = []): Response
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $key = $request->inputString('key', '');
        $documentId = (int) $request->inputString('document_id', '0');
        $unlink = $request->input('unlink') === '1';
        if (!$this->library->knows($tenantId, $key)) {
            Session::flash('error', 'Référence introuvable.');

            return Response::redirect(url(self::BACK));
        }

        return $this->attempt(function () use ($tenantId, $key, $documentId, $unlink): void {
            if ($unlink) {
                $this->library->repository()->unlink($tenantId, $this->userId(), $key, $documentId);
            } else {
                $this->library->repository()->link($tenantId, $this->userId(), $key, $documentId);
            }
        }, $unlink ? 'Rattachement retiré.' : 'Référence rattachée au document.', $key);
    }

    /** Fichier PDF d’une référence de la communauté (membres de cette communauté seulement). */
    public function file(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $row = $tenantId > 0 ? $this->library->repository()->findEntry($tenantId, (int) ($params['id'] ?? 0)) : null;
        if ($row === null || empty($row['file_path'])) {
            return (new Response())->setStatusCode(404)->setBody('Fichier introuvable.');
        }

        return DoctrineLibraryFileStorage::downloadResponse($tenantId, (string) $row['file_path'], (string) ($row['file_name'] ?? ''));
    }

    private function attempt(callable $action, string $success, string $key, string $anchor = ''): Response
    {
        try {
            $action();
            Session::flash('success', $success);
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Doctrine library: ' . $e->getMessage());
            Session::flash('error', 'Enregistrement impossible pour le moment.');
        }
        $hash = $key !== '' ? '#ref-' . $key : ($anchor !== '' ? '#' . $anchor : '');

        return Response::redirect(url(self::BACK) . $hash);
    }

    private function guard(Request $request): ?Response
    {
        if ((int) Session::get('tenant_id') < 1) {
            return Response::redirect(url('dashboard'));
        }
        if (!$this->canEdit()) {
            Session::flash('error', 'Vous n’avez pas les droits pour modifier le référentiel doctrinal.');

            return Response::redirect(url(self::BACK));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url(self::BACK));
        }

        return null;
    }

    private function userId(): int
    {
        return (int) Session::get('user_id');
    }

    private function currentReferential(int $tenantId): string
    {
        try {
            $settings = $this->adminSettings->getForTenant($tenantId);

            return DoctrineReferential::sanitize($settings['doctrine']['referential'] ?? null);
        } catch (\Throwable) {
            return DoctrineReferential::DEFAULT;
        }
    }

    private function canEdit(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('training.manage');
    }
}
