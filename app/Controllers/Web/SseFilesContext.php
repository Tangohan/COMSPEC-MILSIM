<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseFileRecordRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseClearanceService;
use App\Support\SseAccessCatalog;
use App\Support\SseFileCatalog;
use App\Support\SseIdentityCatalog;

/**
 * Fichiers SSE (portail) — contexte commun des écrans : communauté, compte, droits, habilitation, rendu dans la coque du
 * Bureau SSE, retour sûr, annulation d'une suppression.
 *
 * Droits :
 *  - consulter, annoter, surveiller, envoyer une photo : accès au portail SSE (middleware), jamais en invité pour écrire ;
 *  - modifier une fiche (signalement, alias, liens, mentions) : gestion des dossiers SSE, attribution des accès ou
 *    gestion des fichiers (atak.sse.case.manage, atak.sse.grant, atak.sse.files.admin, admin.access) ;
 *  - lore des sessions : atak.sse.lore.manage ou admin.access.
 */
final class SseFilesContext
{
    public const BASE = 'atak/sse/fichiers';

    private ?SseClearanceService $clearance = null;

    public function __construct(private ?SseAccessCodeService $access = null)
    {
        $this->access ??= new SseAccessCodeService();
    }

    public function access(): SseAccessCodeService
    {
        return $this->access;
    }

    public function tenantId(): int
    {
        $tid = $this->access->tenantId();

        return $tid > 0 ? $tid : (int) Session::get('tenant_id');
    }

    public function userId(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    public function isGuest(): bool
    {
        return $this->access->isGuest();
    }

    /**
     * Droit d'une section du portail (SseAccessPolicy) : par défaut celui des anciennes gardes, étendu ou restreint par
     * les règles de la communauté. Sert tous les écrans des fichiers : fichiers, photothèque, extractions, lore, journal.
     */
    public function allows(string $section, string $level, ?bool $legacy = null): bool
    {
        $context = ['guest' => $this->isGuest(), 'tenant_id' => $this->tenantId()];
        if ($legacy !== null) {
            $context['legacy'] = $legacy;
        }

        return SseAccessPolicy::sessionAllows($section, $level, $context);
    }

    /** Garde d'origine d'un profil du catalogue pour la session (action qui garde sa propre garde d'origine). */
    public function profile(string $profile): bool
    {
        return SseAccessPolicy::sessionProfile($profile, ['guest' => $this->isGuest(), 'tenant_id' => $this->tenantId()]);
    }

    /** Écrire (annoter, surveiller, envoyer une photo) : un compte, pas une session invitée (section Fichiers, niveau « verser »). */
    public function canWrite(): bool
    {
        return $this->allows('fichiers', SseAccessCatalog::LEVEL_ADD);
    }

    /** Modifier une fiche (signalement, alias, liens, mentions) : section Fichiers, niveau « écrire » (gestion des dossiers ou des fichiers). */
    public function canManage(): bool
    {
        return $this->allows('fichiers', SseAccessCatalog::LEVEL_WRITE);
    }

    /** Administrer les fichiers (modération des extractions, réglages) : section Fichiers, niveau « gérer » (gestion des fichiers SSE). */
    public function canAdmin(): bool
    {
        return $this->allows('fichiers', SseAccessCatalog::LEVEL_MANAGE);
    }

    /** Préparer le lore des sessions : section Lore, niveau « écrire » (atak.sse.lore.manage ou administrateur). */
    public function canLore(): bool
    {
        return $this->allows('lore', SseAccessCatalog::LEVEL_WRITE);
    }

    /** @param list<string> $perms */
    public static function can(array $perms): bool
    {
        if (!function_exists('can')) {
            return false;
        }
        foreach ($perms as $p) {
            if (can($p)) {
                return true;
            }
        }

        return false;
    }

    /** Niveau de diffusion lisible par la session (habilitation du Bureau SSE). @return \Closure(string): bool */
    public function canSee(): \Closure
    {
        $clearance = $this->clearance ??= new SseClearanceService($this->access);

        return static function (string $scope) use ($clearance): bool {
            try {
                return $clearance->allows($scope !== '' ? $scope : 'interne');
            } catch (\Throwable) {
                return $scope === 'interne';
            }
        };
    }

    public function operatorLabel(): ?string
    {
        if ($this->isGuest()) {
            $label = trim((string) Session::get('sse_guest_label', ''));

            return $label !== '' ? 'Invité · ' . $label : 'Invité';
        }
        $name = trim((string) (Session::get('display_name') ?? Session::get('callsign') ?? ''));

        return $name !== '' ? $name : null;
    }

    public function csrfOk(Request $request): bool
    {
        return Csrf::validate((string) $request->input('_csrf_token', ''));
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        try {
            return (new SseFileRecordRepository())->settings($this->tenantId());
        } catch (\Throwable) {
            return SseFileRecordRepository::SETTINGS_DEFAULTS;
        }
    }

    /** Retour sur une page des fichiers seulement (jamais une adresse extérieure). */
    public static function safeBack(string $back): string
    {
        $back = trim($back);
        if ($back !== '' && preg_match('#^atak/sse/fichiers(/[A-Za-z0-9/_-]*)?(\?[^\s<>"\']*)?(\#[A-Za-z0-9_-]*)?$#', $back) === 1) {
            return url($back);
        }

        return url(self::BASE);
    }

    public function back(Request $request, string $default = self::BASE): Response
    {
        $b = $request->inputString('back');

        return Response::redirect(self::safeBack($b !== '' ? $b : $default));
    }

    public function denied(Request $request, string $default = self::BASE): Response
    {
        Session::flash('error', 'Action non autorisée.');

        return $this->back($request, $default);
    }

    /**
     * Suppression annulable : le toast de la page suivante propose « Annuler » (POST fichiers/annuler).
     *
     * @param array<string, mixed> $payload ce qu'il faut recréer
     */
    public static function offerUndo(string $message, string $kind, array $payload, string $back): void
    {
        $token = bin2hex(random_bytes(8));
        Session::set('sse_files_undo', ['token' => $token, 'kind' => $kind, 'payload' => $payload, 'back' => $back, 'at' => time()]);
        Session::flash('sse_files_toast', (string) json_encode(['message' => $message, 'undo' => $token], JSON_UNESCAPED_UNICODE));
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data): Response
    {
        $data['papersTab'] = 'fichiers';
        $data['filesCanManage'] = $this->canManage();
        $data['filesCanAdmin'] = $this->canAdmin();
        $data['filesCanLore'] = $this->canLore();
        $data['filesCanWrite'] = $this->canWrite();
        $data['csrfToken'] = Csrf::token();
        $data['mentionLabels'] = SseFileCatalog::MENTIONS;
        $data['docTypes'] = SseIdentityCatalog::DOC_TYPES;
        $data['filesToast'] = Session::getFlash('sse_files_toast');

        return (new SseIdentityRegistryController($this->access))->page($view, $data);
    }
}
