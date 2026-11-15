<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditService;
use App\Services\LegacyData\LegacyDataNotice;
use App\Services\LegacyData\LegacyIdentityImport;
use Throwable;

/**
 * Actions de la section « Données anciennes à vérifier » (Qualité des données) : reprise des anciens noms de personnage
 * (simulation en GET sur la page, application et annulation ici) et réglage de la clôture automatique des bilans de
 * recrutement. Réservé aux administrateurs ; chaque action est protégée par le jeton CSRF et inscrite au journal d'audit.
 * Rien n'est supprimé ni écrasé.
 */
final class OrganizationLegacyDataController
{
    private const BACK = 'back-office/organisation/qualite-donnees?simuler=1#dq-donnees-anciennes';

    public function importNames(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $result = (new LegacyIdentityImport())->apply($tenantId, $actorId);
        if (!$result['ok']) {
            Session::flash('error', $result['reason'] !== '' ? $result['reason'] : 'La reprise des anciens noms a échoué : rien n’a été modifié.');

            return Response::redirect(url(self::BACK));
        }
        LegacyDataNotice::forget($tenantId);
        $this->audit('legacy_identity.imported', $tenantId, $actorId, ['batch_id' => $result['batch_id'], 'imported' => $result['imported'], 'skipped' => $result['skipped']]);
        Session::flash(
            'success',
            $result['imported'] . ' ancien(s) nom(s) repris et marqué(s) « à vérifier ». Aucune valeur existante n’a été remplacée ; vous pouvez annuler cette reprise ci-dessous.'
        );

        return Response::redirect(url(self::BACK));
    }

    public function revertNames(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $batch = preg_replace('/[^a-f0-9]/', '', strtolower(trim((string) $request->input('batch_id', '')))) ?? '';
        $result = (new LegacyIdentityImport())->revert($tenantId, $batch);
        if (!$result['ok']) {
            Session::flash('error', $result['reason'] !== '' ? $result['reason'] : 'L’annulation a échoué : rien n’a été modifié.');

            return Response::redirect(url(self::BACK));
        }
        LegacyDataNotice::forget($tenantId);
        $this->audit('legacy_identity.reverted', $tenantId, $actorId, ['batch_id' => $batch, 'reverted' => $result['reverted'], 'kept' => $result['kept']]);
        Session::flash(
            'success',
            $result['reverted'] . ' nom(s) retiré(s)' . ($result['kept'] > 0 ? ' ; ' . $result['kept'] . ' laissé(s) tel(s) quel(s) car modifié(s) depuis la reprise' : '') . '.'
        );

        return Response::redirect(url(self::BACK));
    }

    /** Active ou désactive la clôture automatique des bilans 30 jours pour la communauté (réglage tenants.settings, retro_auto_close). */
    public function retroSetting(Request $request, array $params = []): Response
    {
        $ctx = $this->guard($request);
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $actorId] = $ctx;
        $enabled = (string) $request->input('enabled', '1') === '1';
        try {
            (new TenantRepository())->updateSettings($tenantId, ['retro_auto_close' => ['enabled' => $enabled]]);
        } catch (Throwable $e) {
            error_log('[legacy_data] réglage des bilans : ' . $e->getMessage());
            Session::flash('error', 'Le réglage n’a pas pu être enregistré.');

            return Response::redirect(url(self::BACK));
        }
        $this->audit('legacy_retro_auto_close.setting', $tenantId, $actorId, ['enabled' => $enabled]);
        Session::flash('success', $enabled ? 'Clôture automatique des bilans activée (dossiers récents seulement).' : 'Clôture automatique des bilans désactivée pour votre communauté.');

        return Response::redirect(url(self::BACK));
    }

    /** @return array{0: int, 1: int}|Response */
    private function guard(Request $request): array|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $actorId = (int) Session::get('user_id');
        if ($tenantId < 1 || $actorId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url(self::BACK));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization') && !$gate->allows('admin.access')) {
            Session::flash('error', 'Droits insuffisants.');

            return Response::redirect(url('dashboard'));
        }

        return [$tenantId, $actorId];
    }

    /** @param array<string, mixed> $details */
    private function audit(string $action, int $tenantId, int $actorId, array $details): void
    {
        try {
            (new AuditService())->log($action, $tenantId, $actorId, 'legacy_data', null, null, (string) json_encode($details, JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            error_log('[legacy_data] audit : ' . $e->getMessage());
        }
    }
}
