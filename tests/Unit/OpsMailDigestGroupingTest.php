<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\EmailDigestRepository;
use App\Repositories\OperatorGameProfileRepository;
use App\Repositories\PlatformBanAppealRepository;
use App\Services\Email\EmailEvents;
use App\Services\Email\MailBudgetGate;
use App\Services\Email\OpsMailDigestService;
use App\Services\EmailService;
use PHPUnit\Framework\TestCase;

final class OpsMailDigestGroupingTest extends TestCase
{
    private static function incident(int $id, string $at, string $rid, string $message = "SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'blood_type_detected' at row 1"): array
    {
        $body = implode("\n", [
            'RAPPORT D’INCIDENT (équipe technique)',
            'Bonjour,',
            'L’application a rencontré un dysfonctionnement pendant le traitement d’une requête.',
            '────────── Résumé ──────────',
            'Nature : Exception non gérée',
            'Message : ' . $message,
            '',
            '────────── Requête concernée ──────────',
            'Méthode HTTP : POST',
            'Chemin applicatif : /api/atak/operator/register',
            'Identifiant de corrélation : ' . $rid,
            'Adresse IP du client : 203.0.113.7',
            'Client (User-Agent, version du mod) : COMSPEC-Link/2.0.88',
            '',
            '────────── Détail technique ──────────',
            'Fichier : /var/www/app/Repositories/OperatorGameProfileRepository.php',
            'Ligne : 93',
            '',
            '────────── Pile d’appels (trace) ──────────',
            '#0 OperatorGameProfileRepository->upsertProfile()',
        ]);

        return ['id' => $id, 'event_code' => 'error_alert', 'subject' => '[Athena] Incident technique — /api/atak/operator/register', 'body_text' => $body, 'created_at' => $at];
    }

    public function testIdenticalIncidentsFoldIntoOneCauseWithCounter(): void
    {
        $rows = [];
        for ($i = 0; $i < 24; $i++) {
            $rows[] = self::incident($i + 1, sprintf('2026-10-19 08:%02d:00', $i * 2), 'rid' . $i);
        }
        $rows[] = self::incident(99, '2026-10-19 09:00:00', 'other', 'Autre erreur');
        $groups = OpsMailDigestService::groupByCause($rows);
        self::assertCount(2, $groups);
        self::assertSame(24, $groups[0]['count']);
        self::assertSame('2026-10-19 08:00:00', $groups[0]['first']);

        [$subject, $html, $text] = OpsMailDigestService::compose($rows, 'https://example.test/admin');
        self::assertStringContainsString('25 alertes (2 causes)', $subject);
        self::assertSame(1, substr_count($text, 'Data too long'));
        self::assertStringContainsString('24 fois', $html);
        self::assertStringNotContainsString('Pile d’appels', $text);
        self::assertStringNotContainsString('Bonjour,', $text);
    }

    public function testAttachmentListsEveryOccurrenceAndFullReport(): void
    {
        $rows = [self::incident(1, '2026-10-19 08:02:00', 'abc'), self::incident(2, '2026-10-19 08:08:00', 'def')];
        $txt = OpsMailDigestService::attachmentText($rows);
        self::assertStringContainsString('2 alerte(s), 1 cause(s)', $txt);
        self::assertStringContainsString('19/10/2026 08:02:00 · POST /api/atak/operator/register · requête abc · IP 203.0.113.7 · client COMSPEC-Link/2.0.88', $txt);
        self::assertStringContainsString('requête def', $txt);
        self::assertStringContainsString('#0 OperatorGameProfileRepository->upsertProfile()', $txt);
        self::assertMatchesRegularExpression('/^rapports-erreurs-\d{4}-\d{2}-\d{2}-\d{4}\.txt$/', OpsMailDigestService::attachmentName());
    }

    public function testNewAppealsReachTheOpsDigestWithoutAnIncidentOrPersonalDetails(): void
    {
        $ops = 'ops@example.test';
        $member = 'member@example.test';
        $items = $this->createMock(EmailDigestRepository::class);
        $items->method('recipientsDue')->willReturnOnConsecutiveCalls([$member], [$ops]);
        $items->method('pendingFor')->willReturnMap([
            [$ops, 500, [
                ['id' => 1, 'event_code' => EmailEvents::PLATFORM_BAN_APPEAL_SUBMITTED, 'body_text' => 'Contenu confidentiel'],
                ['id' => 2, 'event_code' => EmailEvents::PLATFORM_BAN_APPEAL_SUBMITTED],
            ]],
            [$member, 500, [['id' => 3, 'event_code' => EmailEvents::NEW_COMMUNITY_MEMBER, 'subject' => 'Nouveau membre']]],
        ]);
        $items->expects(self::exactly(2))->method('markDigested');
        $appeals = $this->createMock(PlatformBanAppealRepository::class);
        $appeals->method('ready')->willReturn(true);
        $appeals->expects(self::once())->method('countPending')->willReturn(1);

        $gate = $this->createStub(MailBudgetGate::class);
        $gate->method('config')->willReturn(['limit' => 0, 'reserve' => 0, 'digest_minutes' => 15, 'muted' => []]);
        $email = $this->createMock(EmailService::class);
        $email->expects(self::exactly(2))->method('send')->willReturnCallback(
            static function ($event, $to, $subject, $html, $text, ...$options) use ($ops, $member): bool {
                if ($to === $member) {
                    self::assertSame(EmailEvents::MEMBER_DAILY_DIGEST, $event);
                    self::assertStringNotContainsString('contestations', $text);

                    return true;
                }
                self::assertSame($ops, $to);
                self::assertSame(EmailEvents::OPS_DIGEST, $event);
                foreach ([$html, $text] as $body) {
                    self::assertStringContainsString('2 nouvelles demandes dans ce récapitulatif.', $body);
                    self::assertStringContainsString('1 demande en attente de traitement.', $body);
                    self::assertStringContainsString('/admin/sanctions#contestations', $body);
                    self::assertStringNotContainsString('Contenu confidentiel', $body);
                    self::assertStringNotContainsString('.txt', $body);
                }
                self::assertSame([], $options[6]);

                return true;
            }
        );

        $result = (new OpsMailDigestService($items, $email, $gate, $appeals))->sendDue();
        self::assertSame(['recipients' => 2, 'emails' => 2, 'items' => 3], $result);
    }

    public function testAppealQueueSendsOnlyAnAnonymousMarkerToConfiguredInboxes(): void
    {
        $env = $_ENV;
        try {
            $_ENV['ERROR_ALERT_EMAIL'] = 'ops@example.test';
            $_ENV['REGISTRATION_ALERT_EMAIL'] = 'OPS@example.test';
            $_ENV['SECURITY_ALERT_EMAILS'] = '';
            $items = $this->createMock(EmailDigestRepository::class);
            $items->expects(self::once())->method('add')->with(
                'ops@example.test', EmailEvents::PLATFORM_BAN_APPEAL_SUBMITTED, 'Nouvelle contestation de bannissement', '', null
            );
            $gate = $this->createStub(MailBudgetGate::class);
            $gate->method('config')->willReturnOnConsecutiveCalls(
                ['limit' => 0, 'reserve' => 0, 'digest_minutes' => 15, 'muted' => []],
                ['limit' => 0, 'reserve' => 0, 'digest_minutes' => 15, 'muted' => [EmailEvents::PLATFORM_BAN_APPEAL_SUBMITTED]],
            );
            $service = new OpsMailDigestService($items, $this->createStub(EmailService::class), $gate);
            $service->enqueueBanAppeal();
            $service->enqueueBanAppeal();
        } finally {
            $_ENV = $env;
        }
    }

    public function testQueuedAttachmentsSurviveJsonRoundTrip(): void
    {
        $files = [['name' => 'a.txt', 'content' => "é\x00\r\n", 'mime' => 'text/plain']];
        $json = json_encode(EmailService::queueAttachments($files));
        self::assertIsString($json);
        self::assertSame($files, EmailService::attachmentsFromQueue(json_decode($json, true)));
    }

    public function testOperatorProfileValuesFitTheirColumns(): void
    {
        self::assertSame('O+', OperatorGameProfileRepository::bloodType('O POS'));
        self::assertSame('AB-', OperatorGameProfileRepository::bloodType('AB Negative'));
        self::assertSame('A+', OperatorGameProfileRepository::bloodType('A+'));
        $odd = OperatorGameProfileRepository::bloodType('Groupe sanguin inconnu (dogtag)');
        self::assertNotNull($odd);
        self::assertLessThanOrEqual(12, mb_strlen($odd));
        self::assertNull(OperatorGameProfileRepository::bloodType(''));
        self::assertNull(OperatorGameProfileRepository::text(['array'], 20));
        self::assertSame(190, mb_strlen((string) OperatorGameProfileRepository::text(str_repeat('é', 400), 190)));
    }
}
