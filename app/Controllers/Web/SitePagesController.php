<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAnalyticsRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;
use App\Support\ChangelogCatalog;
use App\Support\DevDispatchCatalog;

/**
 * Pages marketing publiques : à propos, contact, journal des nouveautés, présentation SSE.
 */
final class SitePagesController
{
    public function about(Request $request, array $params = []): Response
    {
        return Response::view('layout.marketing', [
            'content' => 'site.about',
            'title' => __('site.about_meta_title'),
            'meta_description' => __('site.about_meta_description'),
            'marketingActive' => 'about',
        ]);
    }

    public function contact(Request $request, array $params = []): Response
    {
        return Response::view('layout.marketing', [
            'content' => 'site.contact',
            'title' => __('site.contact_meta_title'),
            'meta_description' => __('site.contact_meta_description'),
            'marketingActive' => 'contact',
            'contactInboxConfigured' => legal_public_contact_email() !== null,
        ]);
    }

    public function contactSubmit(Request $request, array $params = []): Response
    {
        if ($request->method() !== 'POST') {
            return Response::redirect(url('contact'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', __('site.contact_flash_session'));

            return Response::redirect(url('contact'));
        }

        $honeypot = trim((string) $request->input('company_website', ''));
        if ($honeypot !== '') {
            Session::flash('success', __('site.contact_flash_sent'));

            return Response::redirect(url('contact'));
        }

        $to = legal_public_contact_email();
        if ($to === null) {
            Session::flash('error', __('site.contact_flash_unavailable'));

            return Response::redirect(url('contact'));
        }

        $name = trim((string) $request->input('full_name', ''));
        $email = trim((string) $request->input('from_email', ''));
        $subject = trim((string) $request->input('subject', ''));
        $message = trim((string) $request->input('message', ''));

        if ($name === '' || strlen($name) > 160) {
            Session::flash('error', __('site.contact_flash_name'));

            return Response::redirect(url('contact'));
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', __('site.contact_flash_email'));

            return Response::redirect(url('contact'));
        }
        if ($subject === '' || strlen($subject) > 200) {
            Session::flash('error', __('site.contact_flash_subject'));

            return Response::redirect(url('contact'));
        }
        if ($message === '' || strlen($message) < 20 || strlen($message) > 5000) {
            Session::flash('error', __('site.contact_flash_message'));

            return Response::redirect(url('contact'));
        }

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $html = '<p><strong>Nom</strong> : ' . $safeName . '</p>'
            . '<p><strong>E-mail</strong> : ' . $safeEmail . '</p>'
            . '<p><strong>Objet</strong> : ' . $safeSubject . '</p>'
            . '<hr><p>' . $safeMessage . '</p>';
        $text = "Nom : {$name}\nE-mail : {$email}\nObjet : {$subject}\n\n{$message}";

        /** @var EmailService $emailService */
        $emailService = Container::get(EmailService::class);
        $ok = $emailService->send(
            EmailEvents::COMMUNITY_CONTACT,
            $to,
            'Contact Athena — ' . $subject,
            $html,
            $text,
            null,
            $email,
            ['purpose' => 'platform_contact', 'from' => $email]
        );

        if (!$ok) {
            Session::flash('error', __('site.contact_flash_send_failed'));

            return Response::redirect(url('contact'));
        }

        Session::flash('success', __('site.contact_flash_sent'));

        return Response::redirect(url('contact'));
    }

    public function changelog(Request $request, array $params = []): Response
    {
        $kpis = [];
        try {
            /** @var TenantAnalyticsRepository $analyticsRepo */
            $analyticsRepo = Container::get(TenantAnalyticsRepository::class);
            $raw = $analyticsRepo->getPlatformOperationalKpis(30);
            $kpis['communities_total'] = (int) ($raw['communities_total'] ?? 0);
        } catch (\Throwable) {
            $kpis = [];
        }

        $catalog = ChangelogCatalog::hydrate($kpis);
        $dispatches = DevDispatchCatalog::all();
        $featuredDispatch = DevDispatchCatalog::featured();
        $siteRoot = rtrim(url(''), '/');
        $pageUrl = $siteRoot . '/nouveautes';
        $itemList = [];
        foreach ($catalog['releases'] as $index => $release) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $release['title'],
                'description' => $release['summary'],
                'url' => $pageUrl . '#' . rawurlencode((string) $release['id']),
            ];
        }

        return Response::view('layout.marketing', [
            'content' => 'site.changelog',
            'title' => __('site.changelog_meta_title'),
            'meta_description' => __('site.changelog_meta_description'),
            'marketingActive' => 'changelog',
            'catalog' => $catalog,
            'dispatches' => $dispatches,
            'featuredDispatch' => $featuredDispatch,
            'marketingStyles' => [asset_url('assets/css/changelog.css')],
            'marketingScripts' => [asset_url('assets/js/changelog.js')],
            'jsonLdExtra' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => __('site.changelog_meta_title'),
                    'description' => __('site.changelog_meta_description'),
                    'url' => $pageUrl,
                    'inLanguage' => str_starts_with((string) html_lang(), 'en') ? 'en' : 'fr',
                    'about' => [
                        '@type' => 'SoftwareApplication',
                        'name' => 'Athena',
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem' => 'Web',
                    ],
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'itemListOrder' => 'https://schema.org/ItemListOrderDescending',
                        'numberOfItems' => count($itemList),
                        'itemListElement' => $itemList,
                    ],
                ],
            ],
        ]);
    }

    public function dispatch(Request $request, array $params = []): Response
    {
        $kind = strtolower(trim((string) ($params['kind'] ?? '')));
        $number = (string) ($params['number'] ?? '');
        $dispatch = DevDispatchCatalog::find($kind, $number);
        if ($dispatch === null) {
            return Response::redirect(url('nouveautes'));
        }
        $pageUrl = rtrim(url(''), '/') . '/nouveautes/' . $kind . '/' . $dispatch['number_pad'];

        return Response::view('layout.marketing', [
            'content' => 'site.dispatch',
            'title' => $dispatch['heading'] . ' — ' . $dispatch['title'],
            'meta_description' => $dispatch['activity'] !== '' ? $dispatch['activity'] : $dispatch['title'],
            'marketingActive' => 'changelog',
            'dispatch' => $dispatch,
            'dispatchHeadingTag' => 'h1',
            'marketingStyles' => [asset_url('assets/css/changelog.css')],
            'jsonLdExtra' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    'headline' => $dispatch['heading'] . ' — ' . $dispatch['title'],
                    'datePublished' => $dispatch['date'],
                    'inLanguage' => str_starts_with((string) html_lang(), 'en') ? 'en' : 'fr',
                    'url' => $pageUrl,
                    'author' => ['@type' => 'Organization', 'name' => 'Athena COMSPEC'],
                ],
            ],
        ]);
    }

    public function sse(Request $request, array $params = []): Response
    {
        return Response::view('layout.marketing', [
            'content' => 'site.sse',
            'title' => __('site.sse_meta_title'),
            'meta_description' => __('site.sse_meta_description'),
            'marketingActive' => 'sse',
        ]);
    }
}
