<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DemoNda\DemoNdaGateService;
use App\Services\EmailService;

final class DemoNdaController
{
    public function __construct(
        private DemoNdaGateService $gate,
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        if (!$this->gate->isEnabled()) {
            return Response::redirect(url(''));
        }

        $ip = $this->gate->clientIp();
        if ($this->gate->isBypassIp($ip)) {
            return Response::redirect(url(''));
        }

        $visit = $this->gate->registerFirstHit($ip, $request->userAgent());
        if ($visit === null) {
            return Response::redirect(url(''));
        }
        $visit = $this->gate->refreshStatus($visit);
        $status = (string) ($visit['status'] ?? 'pending');

        if ($status === 'expired') {
            return Response::view('demo_nda.denied', [
                'title' => 'Accès indisponible',
            ])->setStatusCode(403);
        }

        if ($status === 'granted' && $this->gate->hasValidSession($visit)) {
            return Response::redirect(url(ltrim($this->gate->consumeIntendedPath(), '/')));
        }

        if ($status === 'granted') {
            $this->gate->expireVisit($visit);

            return Response::view('demo_nda.denied', [
                'title' => 'Accès indisponible',
            ])->setStatusCode(403);
        }

        $claimExpiresAt = (string) ($visit['claim_expires_at'] ?? '');
        $error = Session::getFlash('error');

        return Response::view('demo_nda.gate', [
            'title' => 'Engagement de confidentialité',
            'ttlHours' => $this->gate->ttlHours(),
            'claimExpiresAt' => $claimExpiresAt,
            'error' => is_string($error) ? $error : null,
            'observedIp' => $ip,
            'showObservedIp' => filter_var((string) env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)
                || filter_var((string) env('DEMO_NDA_GATE_SHOW_IP', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function submit(Request $request, array $params = []): Response
    {
        if (!$this->gate->isEnabled()) {
            return Response::redirect(url(''));
        }

        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Merci de réessayer.');

            return Response::redirect(url(ltrim(DemoNdaGateService::GATE_PATH, '/')));
        }

        $ip = $this->gate->clientIp();
        if ($this->gate->isBypassIp($ip)) {
            return Response::redirect(url(''));
        }

        $visit = $this->gate->registerFirstHit($ip, $request->userAgent());
        if ($visit === null) {
            Session::flash('error', 'Impossible de poursuivre pour le moment. Réessayez dans un instant.');

            return Response::redirect(url(ltrim(DemoNdaGateService::GATE_PATH, '/')));
        }

        $visit = $this->gate->refreshStatus($visit);
        if ((string) ($visit['status'] ?? '') === 'expired') {
            return Response::view('demo_nda.denied', [
                'title' => 'Accès indisponible',
            ])->setStatusCode(403);
        }

        $code = trim((string) $request->input('access_code', ''));
        if ($code === '') {
            Session::flash('error', 'Indiquez le code d’accès qui vous a été communiqué.');

            return Response::redirect(url(ltrim(DemoNdaGateService::GATE_PATH, '/')));
        }

        if (!$this->gate->grantAccess($visit, $code)) {
            Session::flash('error', 'Ce code d’accès n’est pas reconnu, ou la fenêtre d’entrée est close.');

            return Response::redirect(url(ltrim(DemoNdaGateService::GATE_PATH, '/')));
        }

        $intended = $this->gate->consumeIntendedPath();

        return Response::redirect(url(ltrim($intended, '/')));
    }

    public function feedbackForm(Request $request, array $params = []): Response
    {
        $error = Session::getFlash('error');
        $success = Session::getFlash('success');

        return Response::view('demo_nda.feedback', [
            'title' => 'Votre avis sur la démonstration',
            'error' => is_string($error) ? $error : null,
            'success' => is_string($success) ? $success : null,
            'inboxConfigured' => demo_feedback_inbox_email() !== null,
            'ratings' => self::feedbackRatingLabels(),
            'highlights' => self::feedbackHighlightLabels(),
            'frictions' => self::feedbackFrictionLabels(),
            'old' => Session::getFlash('old_input'),
        ]);
    }

    public function feedbackSubmit(Request $request, array $params = []): Response
    {
        $path = ltrim(DemoNdaGateService::FEEDBACK_PATH, '/');

        if ($request->method() !== 'POST') {
            return Response::redirect(url($path));
        }

        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré. Merci de réessayer.');

            return Response::redirect(url($path));
        }

        $honeypot = trim((string) $request->input('company_website', ''));
        if ($honeypot !== '') {
            Session::flash('success', 'Merci. Votre retour a bien été transmis.');

            return Response::redirect(url($path));
        }

        $to = demo_feedback_inbox_email();
        if ($to === null) {
            Session::flash('error', 'L’envoi n’est pas disponible pour le moment. Contactez directement TTRD.FR.');

            return Response::redirect(url($path));
        }

        $ratings = self::feedbackRatingLabels();
        $highlights = self::feedbackHighlightLabels();
        $frictions = self::feedbackFrictionLabels();

        $overall = (string) $request->input('overall', '');
        $navigation = (string) $request->input('navigation', '');
        $clarity = (string) $request->input('clarity', '');
        $lookFeel = (string) $request->input('look_feel', '');

        foreach (['overall' => $overall, 'navigation' => $navigation, 'clarity' => $clarity, 'look_feel' => $lookFeel] as $field => $value) {
            if (!isset($ratings[$value])) {
                Session::flash('error', 'Merci de répondre à toutes les questions d’appréciation.');
                Session::flash('old_input', $this->feedbackOldInput($request));

                return Response::redirect(url($path));
            }
        }

        $selectedHighlights = $this->normalizeMultiChoice($request->input('highlights'), $highlights);
        $selectedFrictions = $this->normalizeMultiChoice($request->input('frictions'), $frictions);

        $ideas = trim((string) $request->input('ideas', ''));
        if (strlen($ideas) > 4000) {
            $ideas = substr($ideas, 0, 4000);
        }

        $contactEmail = trim((string) $request->input('contact_email', ''));
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Indiquez une adresse e-mail valide, ou laissez le champ vide.');
            Session::flash('old_input', $this->feedbackOldInput($request));

            return Response::redirect(url($path));
        }

        $contactOk = (string) $request->input('contact_ok', '') === '1';

        $answers = [
            'Impression générale' => $ratings[$overall],
            'Facilité à se retrouver' => $ratings[$navigation],
            'Clarté des écrans' => $ratings[$clarity],
            'Ambiance visuelle' => $ratings[$lookFeel],
            'Ce qui a bien fonctionné' => $selectedHighlights !== []
                ? implode(', ', $selectedHighlights)
                : 'Rien de coché',
            'Ce qui a gêné' => $selectedFrictions !== []
                ? implode(', ', $selectedFrictions)
                : 'Rien de coché',
            'Idées et suggestions' => $ideas !== '' ? $ideas : '—',
            'Souhaite être recontacté' => $contactOk ? 'Oui' : 'Non',
            'Adresse e-mail (si indiquée)' => $contactEmail !== '' ? $contactEmail : '—',
        ];

        $brand = email_brand_name();
        $emailService = Container::get(EmailService::class);
        $ok = $emailService->sendDemoNdaFeedback(
            $to,
            $brand,
            $answers,
            $contactEmail !== '' ? $contactEmail : null
        );

        if (!$ok) {
            Session::flash('error', 'L’envoi a échoué. Merci de réessayer dans un instant, ou de contacter TTRD.FR directement.');
            Session::flash('old_input', $this->feedbackOldInput($request));

            return Response::redirect(url($path));
        }

        Session::flash('success', 'Merci. Votre retour a bien été transmis à l’équipe.');

        return Response::redirect(url($path));
    }

    /**
     * @return array<string, string>
     */
    public static function feedbackRatingLabels(): array
    {
        return [
            '1' => 'Très insatisfaisant',
            '2' => 'Insuffisant',
            '3' => 'Correct',
            '4' => 'Bon',
            '5' => 'Excellent',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function feedbackHighlightLabels(): array
    {
        return [
            'home' => 'Page d’accueil claire',
            'nav' => 'Menus et navigation intuitifs',
            'forms' => 'Formulaires simples à remplir',
            'mobile' => 'Bonne lecture sur téléphone',
            'visual' => 'Design et ambiance réussis',
            'speed' => 'Pages rapides à charger',
            'content' => 'Textes compréhensibles',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function feedbackFrictionLabels(): array
    {
        return [
            'lost' => 'Difficile de savoir où aller',
            'dense' => 'Trop d’informations à l’écran',
            'labels' => 'Libellés peu clairs',
            'buttons' => 'Boutons ou actions difficiles à repérer',
            'contrast' => 'Contraste / lisibilité insuffisants',
            'mobile_ux' => 'Gênant sur téléphone',
            'slow' => 'Sensation de lenteur',
            'inconsistent' => 'Écrans trop différents les uns des autres',
        ];
    }

    /**
     * @param mixed $raw
     * @param array<string, string> $allowed
     * @return list<string>
     */
    private function normalizeMultiChoice(mixed $raw, array $allowed): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $key) {
            $key = (string) $key;
            if (isset($allowed[$key])) {
                $out[] = $allowed[$key];
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackOldInput(Request $request): array
    {
        return [
            'overall' => (string) $request->input('overall', ''),
            'navigation' => (string) $request->input('navigation', ''),
            'clarity' => (string) $request->input('clarity', ''),
            'look_feel' => (string) $request->input('look_feel', ''),
            'highlights' => is_array($request->input('highlights')) ? $request->input('highlights') : [],
            'frictions' => is_array($request->input('frictions')) ? $request->input('frictions') : [],
            'ideas' => (string) $request->input('ideas', ''),
            'contact_email' => (string) $request->input('contact_email', ''),
            'contact_ok' => (string) $request->input('contact_ok', '') === '1',
        ];
    }
}
