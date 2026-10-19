<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlatformSettingsRepository;
use App\Services\Audit\AuditService;
use App\Support\PayPalDonation;

/**
 * Administration du site : dons PayPal (lien de don, activation, aperçu du bouton et du QR code).
 */
final class SystemDonationsController
{
    public function __construct(
        private ?PlatformSettingsRepository $settings = null,
        private ?AuditService $audit = null,
    ) {
        $this->settings ??= new PlatformSettingsRepository();
        $this->audit ??= new AuditService();
    }

    public function index(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'content' => 'admin.system.donations',
            'title' => 'Dons',
            'paypalUrl' => PayPalDonation::configuredUrl($this->settings),
            'paypalRaw' => $this->settings->get(PayPalDonation::SETTING_URL, ''),
            'paypalEnabled' => $this->settings->getBool(PayPalDonation::SETTING_ENABLED, true),
            'settingsReady' => $this->settings->tableExists(),
            'stripeReady' => (getenv('STRIPE_SECRET_KEY') ?: '') !== '',
        ]);
    }

    public function save(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/system/dons'));
        }
        $raw = trim((string) $request->input('paypal_url', ''));
        $url = $raw === '' ? '' : PayPalDonation::normalizeUrl($raw);
        if ($url === null) {
            Session::flash('error', 'Ce lien n’est pas un lien de don PayPal. Exemples acceptés : https://paypal.me/votrenom ou https://www.paypal.com/donate/?hosted_button_id=…');

            return Response::redirect(url('admin/system/dons'));
        }
        $enabled = $request->input('paypal_enabled') === '1';
        $this->settings->setMany([
            PayPalDonation::SETTING_URL => $url,
            PayPalDonation::SETTING_ENABLED => $enabled ? '1' : '0',
        ]);
        try {
            $this->audit->log('platform.donations_updated', null, Session::get('user_id') ? (int) Session::get('user_id') : null, 'platform_settings', null, null, json_encode(['paypal_url' => $url, 'enabled' => $enabled], JSON_UNESCAPED_SLASHES));
        } catch (\Throwable) {
        }
        Session::flash('success', $url === ''
            ? 'Lien PayPal retiré : le bloc PayPal n’apparaît plus sur la page de dons.'
            : ($enabled ? 'Dons PayPal enregistrés et affichés sur la page de dons.' : 'Lien PayPal enregistré, mais masqué au public.'));

        return Response::redirect(url('admin/system/dons'));
    }
}
