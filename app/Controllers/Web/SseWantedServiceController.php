<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseWantedMetaRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseServiceEmblems;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedService;
use App\Support\SseWantedServiceAdmin;
use App\Support\SseWantedServices;

/**
 * Personnalisation des services demandeurs d'une communauté (Réglages › Services) : fiche complète d'un service créé ou livré
 * (surcharge), retour aux valeurs livrées, emblème téléversé (pose, retrait, affichage par route contrôlée) et mise à jour
 * explicite du service porté par un avis déjà émis.
 *
 * Partout : communauté de la SESSION (jamais de la requête), jeton CSRF sur chaque écriture, niveau « Gérer » de la section
 * Fichiers et sujets pour écrire (lecture pour qui lit la section), audit de chaque changement avec l'ancien et le nouveau.
 * Les formulaires de la fenêtre répondent en JSON (message dans la fenêtre) ; sans script, la réponse est un retour à la page.
 */
final class SseWantedServiceController
{
    public const BASE = 'atak/sse/avis';

    public function __construct(
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?SseWantedSettingsRepository $settings = null,
        private ?SseWantedAccess $rights = null,
        private ?SseServiceEmblems $emblems = null,
        private ?SseWantedNoticeRepository $notices = null,
        private ?SseWantedMetaRepository $meta = null,
        private ?SseAuditLogRepository $audit = null,
        private ?\Closure $isUpload = null,
    ) {
        $this->settings ??= new SseWantedSettingsRepository();
    }

    // ------------------------------------------------------------ Fiche d'un service

    /** Nouveau service de la communauté (fenêtre « Nouveau service »). */
    public function create(Request $request, array $params = []): Response
    {
        return $this->write($request, function (SseWantedAccess $r, array $config) use ($request): array {
            $form = self::input($request);
            $res = SseWantedServiceAdmin::create($config, $form);

            return $this->placed($res, $request, null);
        }, 'create');
    }

    /** Fiche d'un service créé, ou surcharge d'un service livré : identité, coordonnées, documents, ordre, défaut, visibilité. */
    public function update(Request $request, array $params = []): Response
    {
        $code = self::code($params);

        return $this->write($request, function (SseWantedAccess $r, array $config) use ($request, $code): array {
            $before = SseWantedServices::find($code, $config);
            if ($before === null) {
                return ['ok' => false, 'config' => $config, 'errors' => ['Service introuvable.'], 'message' => '', 'code' => null];
            }
            $res = SseWantedServiceAdmin::update($config, $code, self::input($request));

            return $this->placed($res, $request, $before);
        }, 'update');
    }

    /** « Rétablir les valeurs livrées » d'un service du catalogue surchargé. */
    public function reset(Request $request, array $params = []): Response
    {
        $code = self::code($params);

        return $this->write($request, static function (SseWantedAccess $r, array $config) use ($code): array {
            $before = SseWantedServices::find($code, $config);
            $res = SseWantedServiceAdmin::reset($config, $code);

            return $res + ['before' => $before, 'after' => $res['ok'] ? SseWantedServices::find($code, $res['config']) : null];
        }, 'reset');
    }

    /**
     * Ordre, service par défaut et visibilité demandés par la fiche, après l'enregistrement du service lui-même.
     *
     * @param array<string, mixed> $res résultat de SseWantedServiceAdmin
     * @param array<string, mixed>|null $before service avant le changement
     * @return array<string, mixed>
     */
    private function placed(array $res, Request $request, ?array $before): array
    {
        if (($res['ok'] ?? false) !== true || !is_string($res['code'] ?? null)) {
            return $res;
        }
        $code = $res['code'];
        $config = $res['config'];
        $position = (int) $request->inputString('position');
        $codes = array_keys(SseWantedServices::all($config));
        if ($position > 0 && array_search($code, $codes, true) !== $position - 1) {
            $config = SseWantedServiceAdmin::setPosition($config, $code, $position)['config'];
        }
        $wantDefault = $request->inputString('is_default') === '1';
        $isDefault = SseWantedServices::defaultCode($config) === $code;
        if ($wantDefault && !$isDefault) {
            $set = SseWantedServiceAdmin::setDefault($config, $code);
            if (!$set['ok']) {
                return ['ok' => false, 'config' => $res['config'], 'errors' => ['Un service masqué ne peut pas être le service par défaut : rendez-le actif d’abord.'], 'message' => '', 'code' => null];
            }
            $config = $set['config'];
        } elseif (!$wantDefault && $isDefault && $request->input('default_set') !== null) {
            $config = SseWantedServiceAdmin::setDefault($config, '')['config'];
        }
        $res['config'] = $config;

        return $res + ['before' => $before, 'after' => SseWantedServices::find($code, $config)];
    }

    /**
     * Écriture des réglages de services : jeton, droit, enregistrement, audit, réponse (JSON pour la fenêtre, retour sinon).
     *
     * @param \Closure(SseWantedAccess, array<string, mixed>): array<string, mixed> $act
     */
    private function write(Request $request, \Closure $act, string $action): Response
    {
        $r = $this->r();
        $back = url(self::BASE . '/reglages') . '#services';
        if (!self::csrfOk($request)) {
            return $this->answer($request, false, [SseWantedNoticeController::TOKEN_EXPIRED], '', $back, 419);
        }
        if (!$r->canAdmin() || $r->tenantId() < 1) {
            return $this->answer($request, false, [SseWantedNoticeController::NEED_ADMIN], '', $back, 403);
        }
        $tenantId = $r->tenantId();
        $res = $act($r, $this->settings->get($tenantId));
        if (($res['ok'] ?? false) !== true) {
            return $this->answer($request, false, array_values(array_map('strval', (array) ($res['errors'] ?? []))) ?: ['Le service n’a pas pu être enregistré.'], '', $back, 422);
        }
        try {
            $this->settings->save($tenantId, $res['config'], $r->userId());
        } catch (\Throwable $e) {
            error_log('Services demandeurs : réglages non enregistrés : ' . $e->getMessage());

            return $this->answer($request, false, ['Les services n’ont pas pu être enregistrés (étape de base de données en attente ?).'], '', $back, 500);
        }
        $this->trace($tenantId, $r, $action, (string) ($res['message'] ?? ''), self::auditShape($res['before'] ?? null), ['code' => $res['code'] ?? null] + (self::auditShape($res['after'] ?? null) ?? []));

        return $this->answer($request, true, [], (string) ($res['message'] ?? ''), $back, 200, ['code' => $res['code'] ?? null]);
    }

    // ------------------------------------------------------------ Emblème

    /**
     * Emblème téléversé d'un service (« ?v=mini » : la miniature) : lecture de la section, communauté de la session. Sans emblème,
     * autre communauté, code inconnu ou étape de base de données en attente : 404 identique (l'écran montre l'emblème générique).
     */
    public function emblem(Request $request, array $params = []): Response
    {
        $notFound = static fn (): Response => (new Response())
            ->setStatusCode(404)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'no-store')
            ->setBody('Not found');
        $r = $this->r();
        if (!$r->canRead() || $r->tenantId() < 1) {
            return $notFound();
        }
        $code = self::code($params);
        try {
            $res = $this->emblems()->resolve($r->tenantId(), $code, $request->queryString('v') === 'mini');
        } catch (\Throwable) {
            $res = null;
        }
        if ($res === null) {
            return $notFound();
        }
        $abs = $res['file'];

        return (new Response())
            ->header('Content-Type', $res['mime'])
            ->header('Content-Length', (string) filesize($abs))
            ->header('Content-Disposition', 'inline; filename="embleme-' . strtolower($code) . '.' . ($res['mime'] === 'image/png' ? 'png' : 'jpg') . '"')
            // L'adresse porte la version (« e= ») : la copie du navigateur reste privée et courte.
            ->header('Cache-Control', $request->queryString('e') !== '' ? 'private, max-age=300' : 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')
            ->setBodyStream(static function () use ($abs): void {
                $h = fopen($abs, 'rb');
                if ($h !== false) {
                    fpassthru($h);
                    fclose($h);
                }
            });
    }

    /** Téléverse l'emblème d'un service : case de confirmation obligatoire, contrôles de SseServiceEmblems. */
    public function emblemUpload(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $back = url(self::BASE . '/reglages') . '#services';
        if (!self::csrfOk($request)) {
            return $this->answer($request, false, [SseWantedNoticeController::TOKEN_EXPIRED], '', $back, 419);
        }
        if (!$r->canAdmin() || $r->tenantId() < 1) {
            return $this->answer($request, false, [SseWantedNoticeController::NEED_ADMIN], '', $back, 403);
        }
        $code = self::code($params);
        $file = $_FILES['emblem_file'] ?? null;
        $tmp = is_array($file) ? (string) ($file['tmp_name'] ?? '') : '';
        $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return $this->answer($request, false, ['Image refusée : 1 Mo au plus.'], '', $back, 422);
        }
        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !$this->uploaded($tmp)) {
            return $this->answer($request, false, ['Choisissez une image à téléverser (PNG, JPEG ou WebP, 1 Mo au plus).'], '', $back, 422);
        }
        $res = $this->emblems()->store($r->tenantId(), $code, $tmp, $request->inputString('emblem_confirm') === '1', $r);
        if (!$res['ok']) {
            return $this->answer($request, false, [$res['error']], '', $back, 422);
        }

        return $this->answer($request, true, [], 'Emblème enregistré.', $back, 200, [
            'code' => $code, 'version' => $res['version'], 'ref' => $res['ref'],
            'url' => SseServiceEmblems::url($code, false, $res['version']), 'thumb' => SseServiceEmblems::url($code, true, $res['version']), 'flash' => false,
        ]);
    }

    /** Retire l'emblème téléversé d'un service : il retrouve son emblème générique. */
    public function emblemRemove(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $back = url(self::BASE . '/reglages') . '#services';
        if (!self::csrfOk($request)) {
            return $this->answer($request, false, [SseWantedNoticeController::TOKEN_EXPIRED], '', $back, 419);
        }
        if (!$r->canAdmin() || $r->tenantId() < 1) {
            return $this->answer($request, false, [SseWantedNoticeController::NEED_ADMIN], '', $back, 403);
        }
        $code = self::code($params);
        $res = $this->emblems()->remove($r->tenantId(), $code, $r);
        if (!$res['ok']) {
            return $this->answer($request, false, [$res['error']], '', $back, 422);
        }

        return $this->answer($request, true, [], 'Emblème retiré : le service reprend son emblème générique.', $back, 200, ['code' => $code, 'flash' => false]);
    }

    // ------------------------------------------------------------ Avis déjà émis

    /**
     * « Mettre à jour le service sur cet avis » : un avis émis garde l'instantané de son service ; cette action explicite le
     * remplace par les valeurs actuelles du service (nom, sigle, autorité, coordonnées, couleur). Rien d'autre ne réécrit un avis.
     */
    public function noticeRefresh(Request $request, array $params = []): Response
    {
        $r = $this->r();
        $noticeId = (int) ($params['id'] ?? 0);
        $fallback = url(self::BASE);
        if (!self::csrfOk($request)) {
            return $this->flashBack('error', SseWantedNoticeController::TOKEN_EXPIRED, $fallback);
        }
        if (!$r->canAdmin() || $r->tenantId() < 1) {
            return $this->flashBack('error', SseWantedNoticeController::NEED_ADMIN, $fallback);
        }
        $tenantId = $r->tenantId();
        $notice = ($this->notices ??= new SseWantedNoticeRepository())->find($tenantId, $noticeId);
        if ($notice === null) {
            return $this->flashBack('error', 'Avis introuvable.', $fallback);
        }
        $back = url('atak/sse/papiers/identites/' . (int) $notice['identity_id']) . '#avis';
        if (!SseWantedMetaRepository::ready()) {
            return $this->flashBack('error', 'Le service d’un avis se met à jour après l’étape de base de données « processus des avis ».', $back);
        }
        $this->meta ??= new SseWantedMetaRepository();
        $meta = $this->meta->forNotice($tenantId, $noticeId);
        $code = (string) ($meta['service_code'] ?? '');
        $service = $code !== '' ? SseWantedServices::find($code, $this->settings->get($tenantId)) : null;
        if ($service === null) {
            return $this->flashBack('error', 'Cet avis ne porte pas de service du catalogue : rien à mettre à jour.', $back);
        }
        $snapshot = SseWantedService::snapshot($service);
        $prefill = SseWantedServices::prefill($service, 'fr');
        $before = ['service' => $meta['service'], 'contact_label' => $notice['contact_label'] ?? null, 'contact_detail' => $notice['contact_detail'] ?? null, 'authority' => $notice['authority'] ?? null];
        $after = ['service' => $snapshot, 'contact_label' => $prefill['contact_label'], 'contact_detail' => $prefill['contact_detail'], 'authority' => $prefill['authority']];
        try {
            $this->meta->save($tenantId, $noticeId, ['service' => $snapshot]);
            Database::getInstance()->execute(
                'UPDATE sse_wanted_notices SET contact_label = :cl, contact_detail = :cd, authority = :auth WHERE id = :id AND tenant_id = :t',
                ['cl' => $after['contact_label'], 'cd' => $after['contact_detail'] !== '' ? $after['contact_detail'] : null, 'auth' => $after['authority'], 'id' => $noticeId, 't' => $tenantId]
            );
        } catch (\Throwable $e) {
            error_log('Services demandeurs : service de l’avis non mis à jour : ' . $e->getMessage());

            return $this->flashBack('error', 'Le service de l’avis n’a pas pu être mis à jour.', $back);
        }
        $this->trace($tenantId, $r, 'notice_refresh', 'Service ' . $code . ' mis à jour sur l’avis ' . (string) ($notice['ref'] ?? $noticeId) . '.', $before, $after + ['notice_id' => $noticeId, 'ref' => $notice['ref'] ?? null], $noticeId);

        return $this->flashBack('success', 'Service mis à jour sur cet avis : le prochain PDF porte ses valeurs actuelles.', $back);
    }

    // ------------------------------------------------------------- Outils

    /**
     * Saisie de la fiche d'un service (tous les champs personnalisables).
     *
     * @return array<string, mixed>
     */
    public static function input(Request $request): array
    {
        $out = [];
        foreach (['fr', 'en', 'ac', 'family', 'auth_fr', 'auth_en', 'desc_fr', 'desc_en', 'motto', 'radio', 'tel', 'mail', 'address', 'city', 'country', 'hours', 'web', 'max', 'class', 'thr', 'color', 'emblem', 'ins_fr', 'ins_en'] as $k) {
            $v = $request->input($k);
            $out[$k] = is_scalar($v) ? (string) $v : '';
        }
        $out['exp'] = (int) $request->inputString('exp');
        $out['enabled'] = $request->inputString('enabled') === '1';
        foreach (['roles', 'threats', 'types', 'lang', 'print'] as $k) {
            $v = $request->input($k);
            $out[$k] = is_array($v) ? array_slice(array_values(array_map('strval', array_filter($v, 'is_scalar'))), 0, 20) : [];
        }
        // Formulaire d'avant les cases « Afficher sur les PDF » : tout reste affiché.
        if ($request->input('print_set') === null && $request->input('print') === null) {
            unset($out['print']);
        }

        return $out;
    }

    /** @param array<string, mixed> $params */
    private static function code(array $params): string
    {
        $code = strtoupper(trim((string) ($params['code'] ?? '')));

        return preg_match('/^(?:SVC|CSV)-[A-Z0-9-]{1,24}$/', $code) === 1 ? $code : '';
    }

    /**
     * Forme d'un service gardée au journal d'audit (ancien et nouveau).
     *
     * @param array<string, mixed>|null $service
     * @return array<string, mixed>|null
     */
    private static function auditShape(mixed $service): ?array
    {
        if (!is_array($service)) {
            return null;
        }
        $keep = array_merge(['code', 'source', 'enabled', 'overridden', 'emblem_ref'], SseWantedServices::OVERRIDE_FIELDS);

        return array_intersect_key($service, array_flip($keep));
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    private function trace(int $tenantId, SseWantedAccess $r, string $action, string $reason, ?array $before, array $after, int $objectId = 0): void
    {
        try {
            ($this->audit ??= new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId,
                'actor_user_id' => $r->userId(),
                'actor_label' => $r->actorLabel() !== '' ? $r->actorLabel() : 'Avis de recherche',
                'action' => 'wanted.services.' . $action,
                'object_type' => $objectId > 0 ? 'sse_wanted_notice' : 'sse_wanted_services',
                'object_id' => $objectId,
                'reason' => $reason,
                'before' => $before,
                'after' => $after,
            ]);
        } catch (\Throwable $e) {
            error_log('Services demandeurs : audit indisponible : ' . $e->getMessage());
        }
    }

    /**
     * Réponse d'une écriture : JSON pour la fenêtre (le message s'affiche dedans), retour à la page avec le message sinon.
     *
     * @param list<string> $errors
     * @param array<string, mixed> $extra
     */
    private function answer(Request $request, bool $ok, array $errors, string $message, string $back, int $status = 200, array $extra = []): Response
    {
        if (self::wantsJson($request)) {
            // La fenêtre recharge la page après un enregistrement réussi : le message l'y attend.
            if ($ok && $message !== '' && ($extra['flash'] ?? true) === true) {
                Session::flash('success', $message);
            }
            unset($extra['flash']);

            return Response::json(['ok' => $ok, 'errors' => $errors, 'message' => $message, 'redirect' => $back] + $extra, $status)
                ->header('Cache-Control', 'no-store');
        }

        return $this->flashBack($ok ? 'success' : 'error', $ok ? $message : implode(' ', $errors), $back);
    }

    private static function wantsJson(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept')), 'application/json')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest';
    }

    private function uploaded(string $tmp): bool
    {
        return $this->isUpload !== null ? (bool) ($this->isUpload)($tmp) : is_uploaded_file($tmp);
    }

    private function emblems(): SseServiceEmblems
    {
        return $this->emblems ??= new SseServiceEmblems(null, null, $this->settings);
    }

    /** Jeton CSRF présent et valable (un jeton vide n'est jamais accepté). */
    private static function csrfOk(Request $request): bool
    {
        $token = $request->input('_csrf_token');

        return is_string($token) && $token !== '' && Csrf::validate($token);
    }

    private function r(): SseWantedAccess
    {
        return $this->rights ??= SseWantedAccess::fromSession($this->access, $this->clearance);
    }

    private function flashBack(string $type, string $message, string $to): Response
    {
        Session::flash($type, $message);

        return Response::redirect($to);
    }
}
