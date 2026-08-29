<?php



declare(strict_types=1);



namespace App\Controllers\Web;



use App\Core\Csrf;

use App\Core\Request;

use App\Core\Response;

use App\Core\Session;

use App\Repositories\TacticalBriefingSlideRepository;

use App\Repositories\TacticalPhonePairingRepository;

use App\Repositories\TenantRepository;



/**

 * Connexion téléphone du module ATAK (inspiré de cTab) : page publique sans compte, ouverte

 * en scannant le QR affiché sur Athena (ordinateur) ou en jeu, ou en saisissant le code court

 * sur /connect — choix entre diapositives (briefing) et carte ATAK complète.

 */

final class AtakPhoneConnectController

{

    private TacticalPhonePairingRepository $pairingRepository;

    private TacticalBriefingSlideRepository $briefingSlideRepository;

    private TenantRepository $tenantRepository;



    public function __construct(

        ?TacticalPhonePairingRepository $pairingRepository = null,

        ?TacticalBriefingSlideRepository $briefingSlideRepository = null,

        ?TenantRepository $tenantRepository = null,

    ) {

        $this->pairingRepository = $pairingRepository ?? new TacticalPhonePairingRepository();

        $this->briefingSlideRepository = $briefingSlideRepository ?? new TacticalBriefingSlideRepository();

        $this->tenantRepository = $tenantRepository ?? new TenantRepository();

    }



    /** Saisie manuelle du code (pas de jeton dans l’URL). */

    public function codeForm(Request $request, array $params = []): Response

    {

        return Response::view('atak.connect_code', [

            'title' => 'Connexion téléphone — Athena ATAK',

            'entryUrlLabel' => $this->publicEntryLabel(),

        ]);

    }



    public function codeSubmit(Request $request, array $params = []): Response

    {

        if (!Csrf::validate((string) $request->input('_csrf_token'))) {

            Session::flash('error', 'La session a expiré. Saisissez à nouveau le code.');



            return Response::redirect(url('connect'));

        }

        $code = trim((string) $request->input('code', ''));

        $pairing = $this->pairingRepository->findValidByCode($code);

        if ($pairing === null) {

            Session::flash('error', 'Code incorrect ou expiré. Demandez un nouveau code depuis Athena sur l’ordinateur (bouton Téléphone), puis réessayez.');

            try {

                $guessTenant = (int) (getenv('ATAK_DEFAULT_TENANT_ID') ?: 0);

                if ($guessTenant > 0) {

                    (new \App\Services\Tactical\AtakActivityLogService())->recordAuthAttempt(

                        $guessTenant,

                        false,

                        'Connexion téléphone refusée — code invalide ou expiré',

                        ['reason' => 'invalid_or_expired_code', 'method' => 'phone']

                    );

                }

            } catch (\Throwable) {

                // Best-effort.

            }



            return Response::redirect(url('connect'));

        }



        return Response::redirect(url('connect/' . (string) $pairing['token']));

    }



    /** Après scan QR ou code valide : choix Diapositives / Carte ATAK. */

    public function show(Request $request, array $params = []): Response

    {

        $token = trim((string) ($params['token'] ?? ''));

        $pairing = $this->pairingRepository->findValidByToken($token);

        if ($pairing === null) {

            return $this->expiredView();

        }

        $this->pairingRepository->markPaired($token);



        $tenantId = (int) ($pairing['tenant_id'] ?? 0);

        $tenantName = $this->tenantDisplayName($tenantId);



        return Response::view('atak.connect_choose', [

            'title' => 'Choisir une destination — Athena ATAK',

            'atakTenantName' => $tenantName,

            'slidesUrl' => url('connect/' . $token . '/slides'),

            'carteUrl' => url('connect/' . $token . '/carte'),

            'chatUrl' => url('connect/' . $token . '/tchat'),

        ]);

    }



    /** Briefing / diapositives mobile (expérience existante). */

    public function slides(Request $request, array $params = []): Response

    {

        $token = trim((string) ($params['token'] ?? ''));

        $pairing = $this->pairingRepository->findValidByToken($token);

        if ($pairing === null) {

            return $this->expiredView();

        }

        $this->pairingRepository->markPaired($token);



        $tenantId = (int) ($pairing['tenant_id'] ?? 0);

        $tenantName = $this->tenantDisplayName($tenantId);

        $slides = $tenantId > 0 ? $this->briefingSlideRepository->listActiveForTenant($tenantId) : [];



        return Response::view('atak.connect_slides', [

            'title' => 'ATAK Athena — ' . $tenantName,

            'atakTenantName' => $tenantName,

            'atakSlides' => $slides,

            'atakPairingToken' => $token,

            'atakPresenceUrl' => url('api/atak/briefing-presence'),

            'atakCommentsBaseUrl' => url('api/atak/briefing-slides'),

        ]);

    }



    /**
     * Ouvre la carte ATAK dans la coque téléphone Android (bezel cTab / IceMan).
     * Session téléphone (communauté + jeton) si aucun membre portail n’est connecté.
     */
    public function openCarte(Request $request, array $params = []): Response
    {
        return $this->openDevice($params, 'map');
    }

    /** Ouvre uniquement le tchat pour déléguer les communications à un mobile. */
    public function openChat(Request $request, array $params = []): Response
    {
        return $this->openDevice($params, 'chat');
    }

    /** Ouvre la SITAC (carte / marqueurs) dans la coque téléphone. */
    public function openSitac(Request $request, array $params = []): Response
    {
        return $this->openDevice($params, 'sitac');
    }

    public function openOrders(Request $request, array $params = []): Response
    {
        return $this->openDevice($params, 'orders');
    }

    public function openExplosives(Request $request, array $params = []): Response
    {
        return $this->openDevice($params, 'charges');
    }

    public function openC2(Request $request, array $params = []): Response
    {
        return $this->openDevice($params, 'c2');
    }

    /** Prépare une vue ATAK mobile ciblée à partir d'une liaison QR valide. */
    private function openDevice(array $params, string $view): Response
    {
        $token = trim((string) ($params['token'] ?? ''));
        $pairing = $this->pairingRepository->findValidByToken($token);
        if ($pairing === null) {
            return $this->expiredView();
        }
        $this->pairingRepository->markPaired($token);

        $tenantId = (int) ($pairing['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            Session::flash('error', 'Communauté introuvable pour cette liaison. Demandez un nouveau code.');

            return Response::redirect(url('connect'));
        }

        $memberUserId = (int) Session::get('user_id');
        if ($memberUserId < 1) {
            Session::set('tenant_id', $tenantId);
            Session::set('atak_phone_pairing_token', $token);
            Session::set('atak_phone_operator_label', 'Opérateur téléphone');
        }

        try {
            (new \App\Services\Tactical\AtakActivityLogService())->recordPhonePaired(
                $tenantId,
                $memberUserId > 0 ? null : 'Opérateur téléphone'
            );
        } catch (\Throwable) {
            // Best-effort : ne bloque pas l’ouverture de la carte.
        }

        $tenantName = $this->tenantDisplayName($tenantId);
        /* C2 → onglet Mission (section commandement), pas Ordres. */
        $tabs = ['chat' => 'chat', 'orders' => 'orders', 'charges' => 'charges', 'c2' => 'mission', 'sitac' => 'markers'];
        $tab = $tabs[$view] ?? '';
        $labels = ['chat' => 'Tchat', 'orders' => 'Ordres', 'charges' => 'Explosifs', 'c2' => 'C2', 'sitac' => 'SITAC'];
        $label = $labels[$view] ?? 'ATAK';
        $atakEmbedUrl = url('atak') . ($tab !== ''
            ? '?embed=device&popout=left&tab=' . rawurlencode($tab)
            : '?embed=device');
        if ($view === 'c2') {
            $atakEmbedUrl .= (str_contains($atakEmbedUrl, '?') ? '&' : '?') . 'section=c2';
        }
        if ($view === 'sitac') {
            $atakEmbedUrl .= (str_contains($atakEmbedUrl, '?') ? '&' : '?') . 'section=sitac';
        }

        return Response::view('atak.connect_device', [
            'title' => $label . ' ATAK — ' . $tenantName,
            'atakTenantName' => $tenantName,
            'atakEmbedUrl' => $atakEmbedUrl,
            'atakDeviceMode' => strtoupper($label),
            'atakDeviceHint' => $tab !== ''
                ? 'Vue ' . strtolower($label) . ' déléguée — le téléphone reste synchronisé avec le poste de commandement.'
                : 'Carte Arma dans le terminal ATAK Android — même liaison que sur Athena.',
            'slidesUrl' => url('connect/' . $token . '/slides'),
            'chooseUrl' => url('connect/' . $token),
        ]);
    }



    private function expiredView(): Response

    {

        return Response::view('atak.connect_expired', [

            'title' => 'Connexion expirée — Athena ATAK',

            'entryUrlLabel' => $this->publicEntryLabel(),

        ]);

    }



    private function tenantDisplayName(int $tenantId): string

    {

        if ($tenantId < 1) {

            return 'Communauté';

        }

        $tenant = $this->tenantRepository->findById($tenantId);

        if (!$tenant) {

            return 'Communauté';

        }



        return function_exists('community_display_name')

            ? community_display_name($tenant)

            : (string) ($tenant['name'] ?? 'Communauté');

    }



    /** Libellé court affiché à l’utilisateur (sans jargon technique). */

    private function publicEntryLabel(): string

    {

        $raw = url('connect');

        if (preg_match('#^(https?://)([^/]+)/public/connect/?$#i', $raw, $m)) {

            return $m[2] . '/connect';

        }

        $host = parse_url($raw, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {

            return 'athena.ttrd.fr/connect';

        }

        $path = (string) (parse_url($raw, PHP_URL_PATH) ?: '/connect');

        $path = rtrim($path, '/');

        if ($path === '' || $path === '/') {

            $path = '/connect';

        }

        // Afficher sans le segment /public si présent (adresse racine réécrite).

        $path = preg_replace('#^/public(/|$)#', '/', $path) ?? $path;

        if ($path === '/' || $path === '') {

            $path = '/connect';

        }



        return $host . $path;

    }

}
