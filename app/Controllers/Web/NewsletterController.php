<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\NewsletterSubscriberRepository;
use App\Services\EmailService;

final class NewsletterController
{
    public function subscribe(Request $request, array $params = []): Response
    {
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            return Response::redirect(url('/?newsletter=csrf'));
        }

        $email = mb_strtolower(trim((string) $request->input('email')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::redirect(url('/?newsletter=invalid_email'));
        }

        $repo = Container::get(NewsletterSubscriberRepository::class);
        if (!$repo->schemaReady()) {
            return Response::redirect(url('/?newsletter=schema_missing'));
        }

        $rawConfirmToken = bin2hex(random_bytes(32));
        $rawUnsubscribeToken = bin2hex(random_bytes(32));
        $confirmHash = hash('sha256', $rawConfirmToken);
        $unsubscribeHash = hash('sha256', $rawUnsubscribeToken);

        $existing = $repo->findByEmail($email);
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

        if ($existing) {
            $repo->refreshPending((int) $existing['id'], $confirmHash, $unsubscribeHash, $ip, $ua);
        } else {
            $repo->createPending($email, $confirmHash, $unsubscribeHash, $ip, $ua);
        }

        $confirmUrl = url('newsletter/confirm?token=' . rawurlencode($rawConfirmToken));
        $unsubscribeUrl = url('newsletter/unsubscribe?token=' . rawurlencode($rawUnsubscribeToken));

        $emailService = Container::get(EmailService::class);
        $emailService->sendTemplated(
            'NEWSLETTER_OPTIN_CONFIRM',
            'newsletter_optin_confirm',
            $email,
            'Confirmez votre inscription aux communications Athena',
            [
                'confirmUrl' => $confirmUrl,
                'unsubscribeUrl' => $unsubscribeUrl,
                'expiresInHours' => 48,
            ],
            null,
            null,
            ['purpose' => 'newsletter_optin_confirm']
        );

        return Response::redirect(url('/?newsletter=confirm_sent'));
    }

    public function confirm(Request $request, array $params = []): Response
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            return Response::redirect(url('/?newsletter=confirm_invalid'));
        }

        $repo = Container::get(NewsletterSubscriberRepository::class);
        $row = $repo->markSubscribedByConfirmToken(hash('sha256', $token));
        if (!$row) {
            return Response::redirect(url('/?newsletter=confirm_invalid'));
        }

        $rawUnsubscribeToken = bin2hex(random_bytes(32));
        $repo->rotateUnsubscribeToken((int) $row['id'], hash('sha256', $rawUnsubscribeToken));

        $unsubscribeUrl = url('newsletter/unsubscribe?token=' . rawurlencode($rawUnsubscribeToken));
        $email = (string) ($row['email'] ?? '');
        if ($email !== '') {
            /** @var EmailService $emailService */
            $emailService = Container::get(EmailService::class);
            $emailService->sendTemplated(
                'NEWSLETTER_WELCOME',
                'newsletter_welcome',
                $email,
                'Bienvenue dans les communications Athena',
                ['unsubscribeUrl' => $unsubscribeUrl],
                null,
                null,
                ['purpose' => 'newsletter_welcome']
            );
        }

        return Response::redirect(url('/?newsletter=confirmed'));
    }

    public function unsubscribe(Request $request, array $params = []): Response
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '') {
            return Response::redirect(url('/?newsletter=unsubscribe_invalid'));
        }

        $repo = Container::get(NewsletterSubscriberRepository::class);
        $row = $repo->markUnsubscribedByToken(hash('sha256', $token));

        return Response::redirect(url('/?newsletter=' . ($row ? 'unsubscribed' : 'unsubscribe_invalid')));
    }
}
