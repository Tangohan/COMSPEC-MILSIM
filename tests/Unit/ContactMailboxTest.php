<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ContactMail\AttachmentStorage;
use App\Services\ContactMail\ContactMailConfig;
use App\Services\ContactMail\ContactMailIngest;
use App\Services\ContactMail\ContactMailSender;
use App\Services\ContactMail\ContactMailSyncService;
use App\Services\ContactMail\DownloadName;
use App\Services\ContactMail\HtmlSanitizer;
use App\Services\ContactMail\ImapClient;
use App\Services\ContactMail\MailHeaderGuard;
use App\Services\ContactMail\MimeParser;
use App\Services\ContactMail\ReplyComposer;
use App\Services\Cron\CronSchedule;
use App\Services\Cron\Jobs\ContactMailSyncCronJob;
use App\Services\Email\AutomaticMailNotice;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeImapServer;
use Tests\Support\InMemoryContactMailRepository;

/**
 * Boîte de contact de la plateforme : analyse MIME, assainissement HTML, client IMAP (serveur factice en mémoire),
 * rangement en fils, envoi (refus d'injection d'en-tête, plafond, pièces jointes), accès réservé, état « non configurée »,
 * migration, vues, Reply-To des e-mails automatiques et formulaire public.
 */
final class ContactMailboxTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/contact-mailbox-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmp);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->removeDir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    private function config(string $password = 'secret', array $extra = []): ContactMailConfig
    {
        return new ContactMailConfig($extra + [
            'address' => 'contact@example.test',
            'user' => 'contact@example.test',
            'password' => $password,
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'from_name' => 'Athena',
            'signature' => 'L’équipe Athena',
            'sent_folder' => 'INBOX.Sent',
            'send_per_hour' => 20,
            'timeout' => 5,
            'reply_to_mode' => 'contact',
        ]);
    }

    /** @param list<string> $headers */
    private function mail(array $headers, string $body): string
    {
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function simple(string $from, string $subject, string $body, string $id, string $inReplyTo = '', string $extra = ''): string
    {
        $h = [
            'From: ' . $from,
            'To: contact@example.test',
            'Subject: ' . $subject,
            'Date: Sat, 14 Nov 2026 10:15:00 +0100',
            'Message-ID: <' . $id . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if ($inReplyTo !== '') {
            $h[] = 'In-Reply-To: <' . $inReplyTo . '>';
            $h[] = 'References: <' . $inReplyTo . '>';
        }
        if ($extra !== '') {
            $h[] = $extra;
        }

        return $this->mail($h, $body . "\r\n");
    }

    // ---------------------------------------------------------------- MIME

    public function testParsesMultipartAlternativeWithEncodedHeaders(): void
    {
        $html = base64_encode('<p>Bonjour <b>à tous</b></p>');
        $raw = $this->mail([
            'From: =?UTF-8?B?' . base64_encode('José Méndez') . '?= <Jose@Example.org>',
            'To: Contact <contact@example.test>, "Lopez, Ana" <ana@example.org>',
            'Cc: dev@example.org',
            'Subject: =?UTF-8?Q?Demande_d=27information_=E2=80=94_=C3=A9v=C3=A9nement?=',
            'Date: Sat, 14 Nov 2026 10:15:00 +0100',
            'Message-ID: <abc123@example.org>',
            'Reply-To: Jose Perso <jose.perso@example.net>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="BOUND"',
        ], "--BOUND\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nBonjour =C3=A0 tous,=0D=0AMerci.\r\n"
            . "--BOUND\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split($html) . "--BOUND--\r\n");

        $m = MimeParser::parse($raw);

        self::assertSame('José Méndez', $m->fromName);
        self::assertSame('jose@example.org', $m->fromEmail);
        self::assertSame('jose.perso@example.net', $m->replyToEmail);
        self::assertSame('Demande d\'information — événement', $m->subject);
        self::assertSame('abc123@example.org', $m->messageId);
        self::assertSame('2026-11-14 09:15:00', $m->sentAt);
        self::assertStringContainsString("Bonjour à tous,\r\nMerci.", $m->text);
        self::assertStringContainsString('<b>à tous</b>', $m->html);
        self::assertSame('contact@example.test', $m->to[0]['email']);
        self::assertSame('Lopez, Ana', $m->to[1]['name']);
        self::assertSame('ana@example.org', $m->to[1]['email']);
        self::assertSame('dev@example.org', $m->cc[0]['email']);
        self::assertSame([], $m->attachments);
    }

    public function testParsesAttachmentsAndEncodedFilenames(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n";
        $raw = $this->mail([
            'From: a@example.org',
            'Subject: Pièce jointe',
            'Message-ID: <att@example.org>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="MIX"',
        ], "--MIX\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nVoir le rapport.\r\n"
            . "--MIX\r\nContent-Type: application/pdf; name=\"rapport.pdf\"\r\nContent-Disposition: attachment; filename*=UTF-8''rapport%20%C3%A9t%C3%A9.pdf\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($pdf))
            . "--MIX\r\nContent-Type: image/png\r\nContent-ID: <logo@cid>\r\nContent-Disposition: inline\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode('PNGDATA'))
            . "--MIX--\r\n");

        $m = MimeParser::parse($raw);

        self::assertStringContainsString('Voir le rapport.', $m->text);
        self::assertCount(2, $m->attachments);
        self::assertSame('rapport été.pdf', $m->attachments[0]->filename);
        self::assertSame('application/pdf', $m->attachments[0]->mime);
        self::assertSame($pdf, $m->attachments[0]->content);
        self::assertFalse($m->attachments[0]->inline);
        self::assertSame('logo@cid', $m->attachments[1]->contentId);
        self::assertTrue($m->attachments[1]->inline);
        self::assertSame('PNGDATA', $m->attachments[1]->content);
    }

    public function testLegacyCharsetsAndEncodedWordsAreDecodedToUtf8(): void
    {
        $raw = $this->mail([
            'From: =?ISO-8859-1?Q?Andr=E9?= <andre@example.org>',
            'Subject: =?ISO-8859-1?Q?Caf=E9?= =?ISO-8859-1?Q?_cr=E8me?=',
            'Message-ID: <iso@example.org>',
            'Content-Type: text/plain; charset=ISO-8859-1',
            'Content-Transfer-Encoding: quoted-printable',
        ], "Caf=E9 cr=E8me =80 5\r\n");

        $m = MimeParser::parse($raw);

        self::assertSame('André', $m->fromName);
        self::assertSame('Café crème', $m->subject);
        self::assertStringContainsString('Café crème', $m->text);
        self::assertTrue(mb_check_encoding($m->text, 'UTF-8'));

        $words = MimeParser::decodeHeader('=?UTF-8?B?' . base64_encode('Bon') . '?=   =?UTF-8?B?' . base64_encode('jour') . '?=');
        self::assertSame('Bonjour', $words, 'blancs entre deux mots encodés supprimés');
        self::assertSame('a b c', MimeParser::decodeHeader("a\r\nb\tc"), 'retours à la ligne neutralisés');
    }

    public function testHtmlOnlyMessageGetsATextVersionAndBrokenBoundariesDoNotCrash(): void
    {
        $m = MimeParser::parse($this->mail(['From: a@example.org', 'Subject: x', 'Content-Type: text/html; charset=utf-8'], '<p>Un</p><p>Deux &amp; trois</p><style>p{color:red}</style>'));
        self::assertSame("Un\nDeux & trois", $m->text);

        $broken = MimeParser::parse($this->mail(['From: a@example.org', 'Subject: y', 'Content-Type: multipart/mixed; boundary="NOPE"'], "--NOPE\r\nContent-Type: text/plain\r\n\r\nSans fin"));
        self::assertStringContainsString('Sans fin', $broken->text);

        $empty = MimeParser::parse('');
        self::assertSame('', $empty->fromEmail);
    }

    public function testAutomaticMessagesAreRecognised(): void
    {
        $m = MimeParser::parse($this->simple('Robot <mailer-daemon@example.org>', 'Échec', 'Retour', 'bounce@example.org'));
        self::assertTrue($m->autoReply);
        $m = MimeParser::parse($this->simple('a@example.org', 'Absent', 'Je reviens', 'ooo@example.org', '', 'Auto-Submitted: auto-replied'));
        self::assertTrue($m->autoReply);
        $m = MimeParser::parse($this->simple('a@example.org', 'Bonjour', 'Salut', 'hi@example.org'));
        self::assertFalse($m->autoReply);
    }

    // ------------------------------------------------------------- fils

    private function ingest(InMemoryContactMailRepository $repo, ?ContactMailConfig $cfg = null): ContactMailIngest
    {
        return new ContactMailIngest($repo, new AttachmentStorage($this->tmp . '/files'), $cfg ?? $this->config());
    }

    public function testRepliesJoinTheThreadByInReplyToAndUnrelatedMailsStartAnother(): void
    {
        $repo = new InMemoryContactMailRepository();
        $in = $this->ingest($repo);

        $t1 = $in->ingest(MimeParser::parse($this->simple('Ana <ana@example.org>', 'Question', 'Première', 'm1@example.org')));
        $t2 = $in->ingest(MimeParser::parse($this->simple('Ana <ana@example.org>', 'Re: Question', 'Suite', 'm2@example.org', 'm1@example.org')));
        $t3 = $in->ingest(MimeParser::parse($this->simple('Bob <bob@example.org>', 'Autre sujet', 'Hors sujet', 'm3@example.org')));
        // Le client de Ana perd les en-têtes de fil : repli par objet « Re: » et même correspondant.
        $t4 = $in->ingest(MimeParser::parse($this->simple('Ana <ana@example.org>', 'RE: Question', 'Sans en-tête', 'm4@example.org')));
        // Même objet sans « Re: » : nouvelle demande, nouveau fil.
        $t5 = $in->ingest(MimeParser::parse($this->simple('Ana <ana@example.org>', 'Question', 'Nouvelle demande', 'm5@example.org')));

        self::assertSame($t1, $t2);
        self::assertNotSame($t1, $t3);
        self::assertSame($t1, $t4);
        self::assertNotSame($t1, $t5);
        self::assertSame(3, $repo->threads[(int) $t1]['message_count']);
        self::assertSame(3, $repo->threads[(int) $t1]['unread_count']);
        self::assertSame(1, $repo->threads[(int) $t1]['todo']);
    }

    public function testDuplicateDeliveriesAreIgnoredAndAutoRepliesDoNotAskForAnAnswer(): void
    {
        $repo = new InMemoryContactMailRepository();
        $in = $this->ingest($repo);
        $raw = $this->simple('Ana <ana@example.org>', 'Bonjour', 'Salut', 'dup@example.org');
        self::assertNotNull($in->ingest(MimeParser::parse($raw), ['uidvalidity' => 7, 'uid' => 1]));
        self::assertNull($in->ingest(MimeParser::parse($raw), ['uidvalidity' => 7, 'uid' => 1]), 'même UID');
        self::assertNull($in->ingest(MimeParser::parse($raw), ['uidvalidity' => 8, 'uid' => 1]), 'même Message-ID après un changement d’UIDVALIDITY');
        self::assertCount(1, $repo->messages);

        $t = $in->ingest(MimeParser::parse($this->simple('Robot <noreply@example.org>', 'Accusé', 'Auto', 'auto@example.org')));
        self::assertSame(0, $repo->threads[(int) $t]['todo']);
    }

    public function testPublicFormMessageBecomesAFormThread(): void
    {
        $repo = new InMemoryContactMailRepository();
        $id = $this->ingest($repo)->ingestForm('Camille', 'camille@example.org', 'Une question', 'Bonjour, voici ma demande en détail.');
        self::assertSame('form', $repo->threads[$id]['source']);
        self::assertSame(1, $repo->threads[$id]['todo']);
        $msg = array_values($repo->messages)[0];
        self::assertSame('form', $msg['source']);
        self::assertSame('camille@example.org', $msg['from_email']);
        self::assertSame('', $msg['message_id'], 'pas d’identifiant inventé : la réponse ne le cite pas');
    }

    public function testAttachmentsAreStoredOutsidePublicAndPathsCannotEscape(): void
    {
        $storage = new AttachmentStorage($this->tmp . '/store');
        $saved = $storage->save('contenu');
        self::assertNotNull($saved);
        self::assertMatchesRegularExpression('~^\d{4}/\d{2}/[a-f0-9]{32}\.bin$~', $saved['path']);
        self::assertSame(hash('sha256', 'contenu'), $saved['sha256']);
        self::assertNotNull($storage->resolve($saved['path']));
        self::assertNull($storage->resolve('../../etc/passwd'));
        self::assertNull($storage->resolve('2026/11/../../../x.bin'));
        self::assertNull($storage->resolve('2026/11/' . str_repeat('a', 32) . '.bin'));
        $storage->delete($saved['path']);
        self::assertNull($storage->resolve($saved['path']));
        self::assertStringNotContainsString('/public/', (new AttachmentStorage())->root() . '/');
    }

    public function testDownloadNamesAreSafe(): void
    {
        self::assertSame('passwd', DownloadName::clean('../../etc/passwd'));
        self::assertSame('piece-jointe', DownloadName::clean(''));
        self::assertSame('ab.pdf', DownloadName::clean("a\r\nb\".pdf"));
        $p = DownloadName::dispositionParams('rapport été.pdf');
        self::assertStringContainsString('filename="rapport _t_.pdf"', $p);
        self::assertStringContainsString("filename*=UTF-8''rapport%20%C3%A9t%C3%A9.pdf", $p);
    }

    // --------------------------------------------------------- assainissement

    public function testSanitizerRemovesClassicXssPayloads(): void
    {
        $payloads = [
            '<script>alert(1)</script><p>ok</p>',
            '<img src=x onerror=alert(1)>',
            '<a href="javascript:alert(1)">clic</a>',
            "<a href=\"  jaVa\nscr\tipt:alert(1)\">clic</a>",
            '<a href="&#106;avascript:alert(1)">clic</a>',
            '<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">d</a>',
            '<img src="data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+">',
            '<iframe src="https://evil.example/"></iframe>',
            '<form action="https://evil.example/"><input name="x"><button>Envoyer</button></form>',
            '<svg onload=alert(1)><circle/></svg>',
            '<style>body{background:url(javascript:alert(1))}</style>',
            '<div style="background:url(javascript:alert(1))" onclick="alert(1)">x</div>',
            '<object data="x.swf"></object><embed src="x.swf">',
            '<body onload=alert(1)>texte</body>',
            '<math><mtext></form><form><mglyph><style></math><img src onerror=alert(1)>',
            '<meta http-equiv="refresh" content="0;url=https://evil.example/"><link rel="stylesheet" href="https://evil.example/x.css">',
            '<noscript><p title="</noscript><img src=x onerror=alert(1)>">',
            '<a href="vbscript:msgbox(1)">v</a><a href="file:///etc/passwd">f</a>',
            '<base href="https://evil.example/"><p>x</p>',
            '<textarea><script>alert(1)</script></textarea>',
        ];
        foreach ($payloads as $payload) {
            foreach ([false, true] as $images) {
                $out = HtmlSanitizer::sanitize($payload, $images);
                $low = strtolower($out);
                foreach (['<script', 'onerror', 'onload', 'onclick', 'javascript:', 'vbscript:', '<iframe', '<form', '<input', '<button', '<style', '<svg', '<object', '<embed', '<meta', '<link', '<base', 'data:', 'file:', ' style=', '<textarea'] as $bad) {
                    self::assertStringNotContainsString($bad, $low, $bad . ' dans « ' . $out . ' » pour ' . $payload);
                }
            }
        }
    }

    public function testSanitizerKeepsSafeFormattingAndNeutralisesLinksAndImages(): void
    {
        $html = '<p>Bonjour <b>Ana</b>, <a href="https://example.org/page?a=1&b=2" onclick="x()">voir</a> et <a href="mailto:a@example.org">écrire</a></p>'
            . '<img src="https://tracker.example/pixel.gif" alt="logo" width="10" height="10"><table><tr><td colspan="2">cellule</td></tr></table>';

        $off = HtmlSanitizer::sanitize($html, false);
        self::assertStringContainsString('<b>Ana</b>', $off);
        self::assertStringContainsString('href="https://example.org/page?a=1&amp;b=2"', $off);
        self::assertStringContainsString('rel="noopener noreferrer nofollow"', $off);
        self::assertStringContainsString('target="_blank"', $off);
        self::assertStringContainsString('href="mailto:a@example.org"', $off);
        self::assertStringNotContainsString('<img', $off, 'image distante non chargée par défaut');
        self::assertStringNotContainsString('tracker.example', $off);
        self::assertStringContainsString('[logo]', $off);
        self::assertStringContainsString('<td colspan="2">', $off);

        $on = HtmlSanitizer::sanitize($html, true);
        self::assertStringContainsString('<img src="https://tracker.example/pixel.gif"', $on);
        self::assertStringContainsString('referrerpolicy="no-referrer"', $on);

        $cid = HtmlSanitizer::sanitize('<img src="cid:logo@cid" alt="l">', false, static fn (string $c): ?string => $c === 'logo@cid' ? '/admin/system/boite-contact/piece/9' : null);
        self::assertStringContainsString('src="/admin/system/boite-contact/piece/9"', $cid);
        $missing = HtmlSanitizer::sanitize('<img src="cid:other" alt="">', true, static fn (string $c): ?string => null);
        self::assertStringNotContainsString('<img', $missing);
    }

    public function testTextBodiesAreEscapedAndLinksNeutralised(): void
    {
        $out = HtmlSanitizer::textToHtml("<script>alert(1)</script>\nVoir https://example.org/a?x=1&y=2, merci.\njavascript:alert(1) http://");
        self::assertStringNotContainsString('<script', $out);
        self::assertStringContainsString('&lt;script&gt;', $out);
        self::assertStringContainsString('<a href="https://example.org/a?x=1&amp;y=2" rel="noopener noreferrer nofollow" target="_blank">https://example.org/a?x=1&amp;y=2</a>, merci.', $out);
        self::assertStringNotContainsString('href="javascript', $out);
    }

    // ---------------------------------------------------- injection d'en-tête

    public function testHeaderInjectionIsRefused(): void
    {
        foreach (["a@example.org\r\nBcc: evil@example.org", "a@example.org\nBcc: x@y.fr", "a@example.org%0d%0aBcc:x@y.fr", "a@example.org\0", 'a@example.org>, <b@example.org', '"x"@example.org'] as $bad) {
            try {
                MailHeaderGuard::address($bad);
                self::fail('adresse acceptée : ' . json_encode($bad));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
        foreach (["Objet\r\nBcc: evil@example.org", "Objet\nCc: x@y.fr", "Objet\0"] as $bad) {
            try {
                MailHeaderGuard::subject($bad);
                self::fail('objet accepté');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
        try {
            MailHeaderGuard::addressList("ok@example.org,\r\nBcc: evil@example.org");
            self::fail('liste acceptée');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        self::assertSame(['a@example.org', 'b@example.org'], MailHeaderGuard::addressList('A@example.org; b@example.org, a@example.org'));
        self::assertSame('Re: Bonjour', MailHeaderGuard::replySubject('RE: Re: Fwd: Bonjour'));
        self::assertSame('Re: Bonjour', MailHeaderGuard::replySubject('Bonjour'));
        self::assertSame('bonjour', MailHeaderGuard::normalizeSubject('Re: TR: Bonjour'));
    }

    // ------------------------------------------------------------ envoi

    private function sender(InMemoryContactMailRepository $repo, ?ContactMailConfig $cfg = null, array &$captured = [], array &$sentCopies = []): ContactMailSender
    {
        $cfg ??= $this->config();

        return new ContactMailSender(
            $cfg,
            $repo,
            $this->ingest($repo, $cfg),
            static function (string $raw) use (&$sentCopies): void {
                $sentCopies[] = $raw;
            },
            static function (PHPMailer $mail) use (&$captured): void {
                $captured[] = $mail->getSentMIMEMessage();
            }
        );
    }

    public function testReplyIsThreadedQuotedAndRecordedInTheThread(): void
    {
        $repo = new InMemoryContactMailRepository();
        $in = $this->ingest($repo);
        $threadId = (int) $in->ingest(MimeParser::parse($this->simple('Ana <ana@example.org>', 'Re: RE: Demande', "Première ligne\r\nSeconde", 'in1@example.org', '', 'Reply-To: Ana Perso <ana.perso@example.net>')));
        $repo->messages[1]['refs'] = 'old1@example.org old2@example.org';

        $captured = [];
        $copies = [];
        $sender = $this->sender($repo, null, $captured, $copies);
        $reply = ReplyComposer::defaults($repo->threads[$threadId], $repo->messages[1], $this->config());

        self::assertSame('ana.perso@example.net', $reply['to'], 'Reply-To du correspondant');
        self::assertSame('Re: Demande', $reply['subject'], 'sans empilement');
        self::assertStringContainsString("> Première ligne\n> Seconde", $reply['body']);
        self::assertStringContainsString("-- \nL’équipe Athena", $reply['body']);
        self::assertSame('in1@example.org', $reply['in_reply_to']);
        self::assertSame('old1@example.org old2@example.org in1@example.org', $reply['references']);

        $r = $sender->send(['thread_id' => $threadId, 'to' => $reply['to'], 'subject' => $reply['subject'], 'body' => "Merci pour votre message.\n\n" . $reply['body']]);

        self::assertTrue($r['ok']);
        self::assertSame($threadId, $r['thread_id']);
        self::assertCount(1, $captured);
        $raw = $captured[0];
        self::assertMatchesRegularExpression('/^From: Athena <contact@example\.test>\r?$/m', $raw);
        self::assertMatchesRegularExpression('/^To: ana\.perso@example\.net\r?$/m', $raw);
        self::assertMatchesRegularExpression('/^Subject: Re: Demande\r?$/m', $raw);
        self::assertMatchesRegularExpression('/^In-Reply-To: <in1@example\.org>\r?$/m', $raw);
        self::assertMatchesRegularExpression('/^References: <old1@example\.org> <old2@example\.org> <in1@example\.org>\r?$/m', $raw);
        self::assertMatchesRegularExpression('/^Message-ID: <[a-f0-9]{32}@example\.test>\r?$/m', $raw);
        self::assertStringNotContainsString('X-Mailer: PHPMailer', $raw);
        self::assertStringNotContainsString("\r\nBcc:", $raw);
        self::assertSame($raw, $copies[0], 'copie dans Envoyés');

        $out = array_values(array_filter($repo->messages, static fn (array $m): bool => $m['direction'] === 'out'));
        self::assertCount(1, $out);
        self::assertSame('sent', $out[0]['state']);
        self::assertSame('reply', $out[0]['source']);
        self::assertSame($threadId, $out[0]['thread_id']);
        self::assertSame('in1@example.org', $out[0]['in_reply_to']);
        self::assertSame(0, $repo->threads[$threadId]['todo'], 'répondu : plus à traiter');
        self::assertSame('out', $repo->threads[$threadId]['last_direction']);

        // Un nouveau message du correspondant, qui cite notre réponse, retombe dans le même fil.
        $mid = $out[0]['message_id'];
        $t = $in->ingest(MimeParser::parse($this->simple('Ana <ana.perso@example.net>', 'Re: Demande', 'Merci', 'in2@example.org', $mid)));
        self::assertSame($threadId, $t);
    }

    public function testNewMessageCreatesAThreadAndAttachmentsAreChecked(): void
    {
        $repo = new InMemoryContactMailRepository();
        $captured = [];
        $sender = $this->sender($repo, null, $captured);

        $r = $sender->send([
            'to' => 'dest@example.org',
            'cc' => 'copie@example.org',
            'subject' => 'Proposition',
            'body' => "Bonjour,\nVoici le document.",
            'attachments' => [['name' => '../Rapport été.pdf', 'content' => "%PDF-1.4\nfake"]],
        ]);
        self::assertTrue($r['ok']);
        $raw = $captured[0];
        self::assertMatchesRegularExpression('/^Cc: copie@example\.org\r?$/m', $raw);
        self::assertStringContainsString('Content-Disposition: attachment;', $raw);
        self::assertStringNotContainsString('..', explode("\r\n\r\n", $raw)[0]);
        self::assertSame('compose', $repo->threads[(int) $r['thread_id']]['source']);
        self::assertSame('dest@example.org', $repo->threads[(int) $r['thread_id']]['participant_email']);
        self::assertCount(1, $repo->attachments);
        self::assertSame('Rapport été.pdf', $repo->attachments[0]['filename']);

        foreach ([
            [['name' => 'virus.exe', 'content' => 'MZ'], 'refusé'],
            [['name' => 'gros.pdf', 'content' => str_repeat('a', ContactMailSender::MAX_ATTACHMENT_BYTES + 1)], 'dépasse'],
        ] as [$file, $needle]) {
            $before = count($captured);
            $r = $sender->send(['to' => 'dest@example.org', 'subject' => 'x', 'body' => 'y', 'attachments' => [$file]]);
            self::assertFalse($r['ok']);
            self::assertStringContainsString($needle, (string) $r['error']);
            self::assertCount($before, $captured, 'rien ne part');
        }
        $many = array_fill(0, ContactMailSender::MAX_ATTACHMENTS + 1, ['name' => 'a.txt', 'content' => 'a']);
        self::assertFalse($sender->send(['to' => 'dest@example.org', 'subject' => 'x', 'body' => 'y', 'attachments' => $many])['ok']);
    }

    public function testInjectionAndLimitsStopTheSendBeforeAnythingLeaves(): void
    {
        $repo = new InMemoryContactMailRepository();
        $captured = [];
        $sender = $this->sender($repo, null, $captured);

        $cases = [
            ['to' => "ok@example.org\r\nBcc: evil@example.org", 'subject' => 's', 'body' => 'b'],
            ['to' => 'ok@example.org', 'cc' => "x@example.org\nBcc: evil@example.org", 'subject' => 's', 'body' => 'b'],
            ['to' => 'ok@example.org', 'subject' => "Objet\r\nBcc: evil@example.org", 'body' => 'b'],
            ['to' => 'pas une adresse', 'subject' => 's', 'body' => 'b'],
            ['to' => '', 'subject' => 's', 'body' => 'b'],
            ['to' => 'ok@example.org', 'subject' => '', 'body' => 'b'],
            ['to' => 'ok@example.org', 'subject' => 's', 'body' => '   '],
            ['to' => 'ok@example.org', 'subject' => str_repeat('x', 201), 'body' => 'b'],
            ['to' => 'ok@example.org', 'subject' => 's', 'body' => str_repeat('x', ContactMailSender::MAX_BODY_CHARS + 1)],
        ];
        foreach ($cases as $i => $case) {
            $r = $sender->send($case);
            self::assertFalse($r['ok'], 'cas ' . $i);
        }
        self::assertSame([], $captured);
        self::assertSame([], $repo->messages);

        // Plafond horaire.
        $limited = $this->sender($repo, $this->config('secret', ['send_per_hour' => 2]), $captured);
        self::assertTrue($limited->send(['to' => 'a@example.org', 'subject' => 's', 'body' => 'b'])['ok']);
        self::assertTrue($limited->send(['to' => 'a@example.org', 'subject' => 's', 'body' => 'b'])['ok']);
        $third = $limited->send(['to' => 'a@example.org', 'subject' => 's', 'body' => 'b']);
        self::assertFalse($third['ok']);
        self::assertStringContainsString('Limite', (string) $third['error']);
        self::assertCount(2, $captured);
    }

    public function testNothingIsSentOrWrittenWhenTheMailboxIsNotConfigured(): void
    {
        $repo = new InMemoryContactMailRepository();
        $captured = [];
        $sender = $this->sender($repo, $this->config(''), $captured);
        $r = $sender->send(['to' => 'a@example.org', 'subject' => 's', 'body' => 'b']);
        self::assertFalse($r['ok']);
        self::assertStringContainsString('CONTACT_MAIL_PASSWORD', (string) $r['error']);
        self::assertFalse($sender->saveDraft(['to' => 'a@example.org', 'subject' => 's', 'body' => 'b'])['ok']);
        self::assertSame([], $captured);
        self::assertSame([], $repo->threads);
    }

    public function testLocalDraftIsKeptWithoutSending(): void
    {
        $repo = new InMemoryContactMailRepository();
        $in = $this->ingest($repo);
        $threadId = (int) $in->ingest(MimeParser::parse($this->simple('Ana <ana@example.org>', 'Demande', 'Texte', 'd1@example.org')));
        $captured = [];
        $sender = $this->sender($repo, null, $captured);

        self::assertTrue($sender->saveDraft(['thread_id' => $threadId, 'to' => 'ana@example.org', 'subject' => 'Re: Demande', 'body' => 'Brouillon 1'])['ok']);
        self::assertTrue($sender->saveDraft(['thread_id' => $threadId, 'to' => 'ana@example.org', 'subject' => 'Re: Demande', 'body' => 'Brouillon 2'])['ok']);
        self::assertSame([], $captured);
        self::assertSame('Brouillon 2', $repo->draft($threadId)['body_text'] ?? '');
        self::assertCount(1, array_filter($repo->messages, static fn (array $m): bool => $m['state'] === 'draft'));
        self::assertSame(1, $repo->threads[$threadId]['message_count'], 'le brouillon ne compte pas');

        $new = $sender->saveDraft(['to' => "a@example.org\r\nBcc: x@y.fr", 'subject' => "Sujet\r\nBcc: x", 'body' => 'x']);
        self::assertTrue($new['ok']);
        self::assertSame('draft', $repo->threads[(int) $new['thread_id']]['status']);
        self::assertStringNotContainsString("\n", $repo->threads[(int) $new['thread_id']]['subject']);
    }

    // ----------------------------------------------------------- client IMAP

    /** @return array{0: ContactMailSyncService, 1: InMemoryContactMailRepository, 2: FakeImapServer} */
    private function syncRig(?ContactMailConfig $cfg = null, int $maxBytes = ImapClient::DEFAULT_MAX_MESSAGE_BYTES): array
    {
        $cfg ??= $this->config();
        $server = new FakeImapServer('contact@example.test', 'secret');
        $repo = new InMemoryContactMailRepository();
        $svc = new ContactMailSyncService(
            $cfg,
            $repo,
            $this->ingest($repo, $cfg),
            static fn (): ImapClient => new ImapClient($server, $maxBytes),
            $this->tmp . '/sync.lock'
        );

        return [$svc, $repo, $server];
    }

    public function testIncrementalSyncIsIdempotentAndUsesPeek(): void
    {
        [$svc, $repo, $server] = $this->syncRig();
        $server->messages = [
            1 => ['raw' => $this->simple('Ana <ana@example.org>', 'Demande', 'Bonjour', 'a1@example.org'), 'flags' => ['\\Seen']],
            2 => ['raw' => $this->simple('Ana <ana@example.org>', 'Re: Demande', 'Relance', 'a2@example.org', 'a1@example.org'), 'flags' => []],
        ];

        $r = $svc->run();
        self::assertTrue($r['available']);
        self::assertTrue($r['ok']);
        self::assertSame(2, $r['imported']);
        self::assertCount(1, $repo->threads, 'même fil');
        self::assertSame(1, $repo->threads[1]['unread_count'], 'le message déjà lu côté serveur reste lu');
        self::assertSame(2, $repo->state['INBOX']['last_uid']);
        self::assertSame(42, $repo->state['INBOX']['uidvalidity']);
        self::assertTrue($server->closed);

        $log = implode("\n", $server->log);
        self::assertStringContainsString('BODY.PEEK[]', $log);
        self::assertStringNotContainsString('BODY[]', str_replace('BODY.PEEK[]', '', $log));
        self::assertStringNotContainsString('secret', $log);

        // Deuxième passage sans nouveauté : rien n'est relu ni dupliqué.
        $r = $svc->run();
        self::assertSame(0, $r['imported']);
        self::assertCount(2, $repo->messages);

        // Un nouveau message.
        $server->messages[3] = ['raw' => $this->simple('Bob <bob@example.org>', 'Autre', 'Salut', 'b1@example.org'), 'flags' => []];
        $r = $svc->run();
        self::assertSame(1, $r['imported']);
        self::assertCount(2, $repo->threads);
        self::assertSame(3, $repo->state['INBOX']['last_uid']);

        // UIDVALIDITY change : tout est relu, rien n'est dupliqué.
        $server->uidValidity = 99;
        $r = $svc->run();
        self::assertSame(0, $r['imported']);
        self::assertCount(3, $repo->messages);
        self::assertSame(99, $repo->state['INBOX']['uidvalidity']);
    }

    public function testBadCredentialsAndOversizedMessagesAreHandledWithoutLeaks(): void
    {
        [$svc, $repo, $server] = $this->syncRig($this->config('mauvais'));
        $server->messages = [1 => ['raw' => $this->simple('a@example.org', 'x', 'y', 'z@example.org'), 'flags' => []]];
        $r = $svc->run();
        self::assertFalse($r['ok']);
        self::assertStringNotContainsString('mauvais', $r['reason']);
        self::assertStringNotContainsString('secret', $r['reason']);
        self::assertSame([], $repo->messages);
        self::assertStringContainsString('NO', $r['reason']);
        self::assertNotSame('', $repo->state['INBOX']['last_error']);

        [$svc, $repo, $server] = $this->syncRig(null, 1000);
        $big = $this->simple('Ana <ana@example.org>', 'Gros message', str_repeat('Contenu volumineux. ', 200), 'big@example.org');
        self::assertGreaterThan(1000, strlen($big));
        $server->messages = [1 => ['raw' => $big, 'flags' => []]];
        $r = $svc->run();
        self::assertSame(1, $r['imported']);
        $msg = array_values($repo->messages)[0];
        self::assertStringContainsString('trop volumineux', $msg['body_text']);
        self::assertSame('Gros message', $msg['subject']);
        self::assertStringNotContainsString('Contenu volumineux', $msg['body_text']);
    }

    public function testClientCommandsAndFetchParser(): void
    {
        $server = new FakeImapServer('contact@example.test', 'se"cret');
        $server->messages = [5 => ['raw' => "Subject: x\r\n\r\ncorps (avec) parenthèses )\r\n", 'flags' => ['\\Seen', '\\Flagged']]];
        $c = new ImapClient($server);
        $c->login('contact@example.test', 'se"cret');
        $info = $c->select('INBOX');
        self::assertSame(42, $info['uidvalidity']);
        self::assertSame(6, $info['uidnext']);
        self::assertSame([5], $c->uidsFrom(1));
        self::assertSame([], $c->uidsFrom(6), 'n:* renvoie le dernier message même plus ancien que n');
        $sum = $c->summaries([5]);
        self::assertSame(['\\Seen', '\\Flagged'], $sum[5]['flags']);
        $msg = $c->fetchMessage(5, $sum[5]['size']);
        self::assertNotNull($msg);
        self::assertSame("Subject: x\r\n\r\ncorps (avec) parenthèses )\r\n", $msg['raw']);
        $c->storeFlags(5, '+FLAGS', ['\\Seen']);
        self::assertSame('+FLAGS \\Seen', $server->stored[5]);
        $c->append('INBOX.Sent', "Subject: copie\r\n\r\nTexte\r\n");
        self::assertSame('INBOX.Sent', $server->appended[0]['folder']);
        self::assertSame("Subject: copie\r\n\r\nTexte\r\n", $server->appended[0]['raw']);
        $c->logout();
        self::assertTrue($server->closed);

        try {
            $c2 = new ImapClient(new FakeImapServer('contact@example.test', 'secret'));
            $c2->storeFlags(1, 'DROP', ['\\Seen']);
            self::fail('mode accepté');
        } catch (\Throwable $e) {
            self::assertStringNotContainsString('secret', $e->getMessage());
        }

        $f = ImapClient::parseFetch("* 3 FETCH (UID 9 FLAGS (\\Seen) BODY[] {11}\r\nab) \"c\" d\r\n x)");
        self::assertNotNull($f);
        self::assertSame('9', $f['UID']);
        self::assertSame("ab) \"c\" d\r\n", $f['BODY[]']);
        self::assertNull(ImapClient::parseFetch('* SEARCH 1 2'));
    }

    // ----------------------------------------------------------- non configurée

    public function testUnconfiguredMailboxOpensNoConnectionAndMakesNoQuery(): void
    {
        $calls = 0;
        $repo = new InMemoryContactMailRepository();
        $repo->isReady = false;
        $cfg = $this->config('');
        self::assertFalse($cfg->isConfigured());
        self::assertSame(['CONTACT_MAIL_PASSWORD'], $cfg->missingKeys());
        $svc = new ContactMailSyncService($cfg, $repo, $this->ingest($repo, $cfg), static function () use (&$calls): ImapClient {
            $calls++;
            throw new \RuntimeException('connexion interdite');
        }, $this->tmp . '/sync.lock');
        $r = $svc->run();
        self::assertFalse($r['available']);
        self::assertTrue($r['ok']);
        self::assertSame(0, $calls);
        self::assertStringContainsString('non configurée', $r['reason']);

        $job = new ContactMailSyncCronJob($svc);
        $res = $job->run();
        self::assertTrue($res['ok']);
        self::assertStringContainsString('Rien à faire', $res['summary']);
        self::assertSame(0, $calls);

        // Configurée mais sans tables : pas de connexion non plus.
        $ready = new ContactMailSyncService($this->config(), $repo, $this->ingest($repo), static function () use (&$calls): ImapClient {
            $calls++;
            throw new \RuntimeException('connexion interdite');
        }, $this->tmp . '/sync2.lock');
        self::assertFalse($ready->run()['available']);
        self::assertSame(0, $calls);

        // Adresse invalide : manquante aussi.
        self::assertContains('CONTACT_MAIL_ADDRESS', (new ContactMailConfig(['address' => 'pas une adresse', 'password' => 'x', 'imap_host' => 'h', 'imap_port' => 993, 'smtp_host' => 'h', 'smtp_port' => 465]))->missingKeys());
    }

    public function testConfigDefaultsComeFromTheEnvKeys(): void
    {
        $src = $this->read('config/contact_mail.php');
        foreach (['CONTACT_MAIL_ADDRESS', 'CONTACT_MAIL_USER', 'CONTACT_MAIL_PASSWORD', 'CONTACT_MAIL_IMAP_HOST', 'CONTACT_MAIL_IMAP_PORT', 'CONTACT_MAIL_SMTP_HOST', 'CONTACT_MAIL_SMTP_PORT', 'CONTACT_MAIL_FROM_NAME', 'CONTACT_MAIL_SIGNATURE', 'MAIL_REPLY_TO_MODE'] as $key) {
            self::assertStringContainsString("'" . $key . "'", $src, $key);
        }
        foreach (['imap.hostinger.com', 'smtp.hostinger.com', '993', '465'] as $default) {
            self::assertStringContainsString($default, $src);
        }
        self::assertSame('Athena', ContactMailConfig::fromEnv()->fromName());
        $example = $this->read('.env.example');
        foreach (array_keys(ContactMailConfig::KEYS) as $key) {
            self::assertMatchesRegularExpression('/^#? ?' . $key . '=/m', $example, $key . ' absent de .env.example');
        }
        $doc = $this->read('docs/boite-contact.md');
        foreach (array_keys(ContactMailConfig::KEYS) as $key) {
            self::assertStringContainsString($key, $doc, $key);
        }
        foreach (['SPF', 'DKIM', 'DMARC', 'imap.hostinger.com', 'smtp.hostinger.com', 'contact_mail_sync', 'contact_mailbox', 'jamais'] as $needle) {
            self::assertStringContainsString($needle, $doc, $needle);
        }
    }

    // --------------------------------------------------- Reply-To et pied de message

    public function testAutomaticMailsCarryReplyToAndAFooter(): void
    {
        $cfg = $this->config();
        self::assertSame('contact@example.test', $cfg->autoReplyTo());
        self::assertNull($this->config('secret', ['reply_to_mode' => 'none'])->autoReplyTo());
        self::assertSame('contact@example.test', $this->config('')->autoReplyTo(), 'Reply-To sans mot de passe de boîte : l’adresse suffit');

        $n = AutomaticMailNotice::apply('<html><body><p>Bonjour</p></body></html>', 'Bonjour', $cfg);
        self::assertStringContainsString('répondez à contact@example.test', $n['html']);
        self::assertStringContainsString('reply to contact@example.test', $n['html']);
        self::assertLessThan(strpos($n['html'], '</body>'), strpos($n['html'], AutomaticMailNotice::MARKER));
        self::assertStringContainsString("Bonjour\n\n-- \nCe message est automatique.", $n['text']);
        $again = AutomaticMailNotice::apply($n['html'], $n['text'], $cfg);
        self::assertSame($n, $again, 'un seul pied de message');

        $none = AutomaticMailNotice::apply('<p>x</p>', 'x', $this->config('secret', ['reply_to_mode' => 'none']));
        self::assertStringContainsString('merci de ne pas y répondre', $none['html']);
        self::assertStringNotContainsString('contact@example.test', $none['html'] . $none['text']);

        $html = AutomaticMailNotice::apply('<p>"&lt;x&gt;</p>', '', new ContactMailConfig(['address' => 'a"b@example.org']));
        self::assertStringNotContainsString('a"b', $html['html'], 'adresse invalide : jamais citée');

        // Câblage : seul l'envoi commun de la plateforme est touché, jamais le compte d'une communauté.
        $svc = $this->read('app/Services/EmailService.php');
        self::assertStringContainsString('if ($community === null) {', $svc);
        self::assertStringContainsString('->autoReplyTo()', $svc);
        self::assertStringContainsString('AutomaticMailNotice::apply(', $svc);
        self::assertLessThan(strpos($svc, 'AutomaticMailNotice::apply('), strpos($svc, 'holdForBudget($eventCode'), 'regroupement et report : texte sans pied de message');
        self::assertStringContainsString('\'reply_to\' => $state[\'reply_to\'] !== \'\' ? $state[\'reply_to\'] : null', $this->read('app/Services/Email/TenantMailSender.php'), 'expéditeurs de communauté inchangés');
        self::assertStringNotContainsString('ContactMail', $this->read('app/Services/Email/TenantMailSender.php'));
        self::assertStringNotContainsString('ContactMail', $this->read('app/Services/EmailTransportResolver.php'));
    }

    // ---------------------------------------------------------------- accès

    public function testEveryRouteIsReservedToThePlatformAdministrator(): void
    {
        $routes = $this->read('routes/web.php');
        preg_match_all('~\$router->(get|post)\(\'(/admin/system/boite-contact[^\']*)\', \[[^\]]*SystemContactMailboxController::class, \'(\w+)\'\], (\[[^\]]*\])\);~', $routes, $m, PREG_SET_ORDER);
        self::assertCount(8, $m);
        $actions = [];
        foreach ($m as $row) {
            self::assertStringContainsString('AuthMiddleware::class', $row[4], $row[2]);
            self::assertStringContainsString('SystemAdminMiddleware::class', $row[4], $row[2]);
            $actions[] = $row[3];
            if ($row[1] === 'post') {
                self::assertContains($row[3], ['sync', 'send', 'saveDraft', 'action']);
            }
        }
        sort($actions);
        self::assertSame(['action', 'attachment', 'compose', 'index', 'saveDraft', 'send', 'sync', 'thread'], $actions);
        // Toute écriture valide le jeton CSRF avant d'agir.
        $ctl = $this->read('app/Controllers/Admin/System/SystemContactMailboxController.php');
        foreach (['send', 'saveDraft', 'action', 'sync'] as $method) {
            $body = substr($ctl, (int) strpos($ctl, 'public function ' . $method . '('));
            $body = substr($body, 0, (int) strpos($body, "\n    }\n"));
            self::assertStringContainsString('Csrf::verify($request)', $body, $method);
        }
        $mw = $this->read('app/Middleware/SystemAdminMiddleware.php');
        self::assertStringContainsString("deny('admin.system')", $mw);
        $nav = $this->read('views/partials/platform_admin_sidebar.php');
        self::assertStringContainsString("'path' => 'admin/system/boite-contact'", $nav);
        self::assertStringContainsString("'access' => 'system'", substr($nav, (int) strpos($nav, 'admin/system/boite-contact'), 250));
    }

    public function testControllerShowsTheUnconfiguredStateWithoutTouchingTheDatabase(): void
    {
        $repo = new InMemoryContactMailRepository();
        $repo->isReady = false;
        $ctl = new \App\Controllers\Admin\System\SystemContactMailboxController($this->config(''), $repo, new AttachmentStorage($this->tmp));
        $m = new \ReflectionMethod($ctl, 'baseData');
        $data = $m->invoke($ctl, 'Boîte de contact');
        self::assertSame('unconfigured', $data['mbState']);
        self::assertSame(['CONTACT_MAIL_PASSWORD'], $data['mbMissing']);
        self::assertArrayHasKey('CONTACT_MAIL_PASSWORD', $data['mbKeys']);
        self::assertSame([], $repo->threads);

        $ctl = new \App\Controllers\Admin\System\SystemContactMailboxController($this->config(), $repo, new AttachmentStorage($this->tmp));
        self::assertSame('no_tables', $m->invoke($ctl, 'x')['mbState']);
        $repo->isReady = true;
        self::assertSame('ready', $m->invoke($ctl, 'x')['mbState']);

        $view = $this->read('views/admin/system/contact_mailbox.php');
        self::assertStringContainsString('Boîte non configurée', $view);
        self::assertStringContainsString('CONTACT_MAIL_PASSWORD', $view);
        self::assertStringContainsString("\$state === 'unconfigured'", $view);
    }

    public function testAttachmentDownloadIsProtectedAndNeverExecutable(): void
    {
        $ctl = $this->read('app/Controllers/Admin/System/SystemContactMailboxController.php');
        self::assertStringContainsString("'attachment'", $ctl);
        self::assertStringContainsString("'; ' . DownloadName::dispositionParams", $ctl);
        self::assertStringContainsString("'X-Content-Type-Options', 'nosniff'", $ctl);
        self::assertStringContainsString("sandbox", $ctl);
        self::assertStringContainsString("'application/octet-stream'", $ctl);
        self::assertStringContainsString('SAFE_MIME', $ctl);
        self::assertStringNotContainsString('text/html', $ctl);
        self::assertStringNotContainsString('image/svg', $ctl);
        // Aucun fichier de la boîte sous public/.
        foreach (glob(self::ROOT . 'app/Services/ContactMail/*.php') ?: [] as $f) {
            self::assertStringNotContainsString("base_path('public", (string) file_get_contents($f), basename($f));
        }
        self::assertStringContainsString('storage/private/contact-mail', $this->read('app/Services/ContactMail/AttachmentStorage.php'));
    }

    public function testNoSecretNorMessageBodyReachesTheErrorLog(): void
    {
        foreach (glob(self::ROOT . 'app/Services/ContactMail/*.php') ?: [] as $f) {
            $src = (string) file_get_contents($f);
            preg_match_all('/error_log\((.*)\);/', $src, $m);
            foreach ($m[1] as $call) {
                self::assertStringNotContainsString('getMessage()', $call, basename($f));
                self::assertStringNotContainsString('password', strtolower($call), basename($f));
                self::assertStringNotContainsString('body', strtolower($call), basename($f));
            }
        }
        $ctl = $this->read('app/Controllers/Web/SitePagesController.php');
        preg_match_all('/error_log\(\'\[contact_mail\][^;]*;/', $ctl, $m);
        self::assertNotEmpty($m[0]);
        foreach ($m[0] as $call) {
            self::assertStringNotContainsString('getMessage()', $call);
        }
        self::assertStringContainsString('ImapException', $this->read('app/Services/ContactMail/ImapException.php'));
    }

    // ------------------------------------------------- migration, tâche, formulaire

    public function testMigrationIsIdempotentReversibleAndRegistered(): void
    {
        $sql = $this->read('migrations/2026_11_15_010_contact_mailbox.sql');
        self::assertSame(4, preg_match_all('/CREATE TABLE IF NOT EXISTS (contact_mail_threads|contact_mail_messages|contact_mail_attachments|contact_mail_sync_state)\b/', $sql));
        self::assertStringNotContainsString('DROP ', $sql);
        self::assertStringNotContainsString('INSERT', $sql, 'aucune donnée ajoutée');
        self::assertStringContainsString('UNIQUE KEY uq_cm_messages_imap (imap_uidvalidity, imap_uid)', $sql);
        self::assertStringContainsString('ON DELETE CASCADE', $sql);
        self::assertSame(4, substr_count($sql, 'ENGINE=InnoDB'));
        $down = $this->read('migrations/rollback/contact_mailbox_down.sql');
        foreach (['contact_mail_attachments', 'contact_mail_messages', 'contact_mail_threads', 'contact_mail_sync_state'] as $t) {
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $t . ';', $down);
        }
        self::assertLessThan(strpos($down, 'contact_mail_messages;'), strpos($down, 'contact_mail_attachments;'), 'enfants d’abord');
        $step = $this->read('database/steps/4565_contact_mailbox.php');
        self::assertStringContainsString('tableExists', $step);
        self::assertStringContainsString('2026_11_15_010_contact_mailbox.sql', $step);
        $registry = $this->read('database/steps/registry.php');
        self::assertStringContainsString("'id' => 'contact_mailbox'", $registry);
        self::assertSame(1, substr_count($registry, '4565_contact_mailbox.php'));
        self::assertFileExists(self::ROOT . 'database/steps/4565_contact_mailbox.php');
        // Les statuts du schéma sont ceux que le code écrit.
        $repo = $this->read('app/Services/ContactMail/ContactMailRepository.php');
        foreach (['contact_mail_threads', 'contact_mail_messages', 'contact_mail_attachments', 'contact_mail_sync_state'] as $t) {
            self::assertStringContainsString($t, $repo);
        }
    }

    public function testScheduledTaskIsRegisteredEveryFiveMinutes(): void
    {
        $job = new ContactMailSyncCronJob();
        self::assertSame('contact_mail_sync', $job->key());
        self::assertSame(5, CronSchedule::intervalMinutes('contact_mail_sync'));
        $container = $this->read('app/Core/Container.php');
        self::assertGreaterThanOrEqual(2, substr_count($container, 'ContactMailSyncCronJob::class'));
        self::assertStringContainsString("'contact_mail_sync' => 5", $this->read('app/Services/Cron/CronSchedule.php'));
    }

    public function testPublicContactFormFeedsTheMailboxWithAntiSpam(): void
    {
        $c = $this->read('app/Controllers/Web/SitePagesController.php');
        self::assertStringContainsString("Csrf::validate((string) \$request->input('_csrf_token'))", $c);
        self::assertStringContainsString("company_website", $c, 'champ piège');
        self::assertStringContainsString("tooManyAttempts('contact_form:'", $c, 'limite par IP');
        self::assertStringContainsString('ingestForm(', $c);
        self::assertStringContainsString('storeInMailbox(', $c);
        self::assertStringContainsString('$mailboxReady && $this->storeInMailbox(', $c, 'rien n’est écrit sans mot de passe de boîte');
        self::assertStringContainsString('contact_flash_rate', $this->read('lang/fr/site.php'));
        self::assertStringContainsString('contact_flash_rate', $this->read('lang/en/site.php'));
    }

    // ------------------------------------------------------------------ vues

    public function testViewsImportEveryClassTheyCall(): void
    {
        $builtin = ['self', 'static', 'parent', 'PHP', 'DateTime', 'DateTimeImmutable'];
        foreach (['contact_mailbox', 'contact_mailbox_thread', 'contact_mailbox_compose', '_contact_mailbox_form'] as $name) {
            $src = $this->read('views/admin/system/' . $name . '.php');
            preg_match_all('/(?<![\w\\\\$>:])([A-Z][A-Za-z0-9_]*)::/', $src, $m);
            foreach (array_unique($m[1]) as $class) {
                if (in_array($class, $builtin, true)) {
                    continue;
                }
                self::assertMatchesRegularExpression('/^use [\w\\\\]+\\\\' . preg_quote($class, '/') . ';$/m', $src, $name . ' appelle ' . $class . ' sans use');
            }
            preg_match_all('/\bnew\s+([A-Z][A-Za-z0-9_]*)\b/', $src, $n);
            foreach (array_unique($n[1]) as $class) {
                self::assertMatchesRegularExpression('/^use [\w\\\\]+\\\\' . preg_quote($class, '/') . ';$/m', $src, $name . ' instancie ' . $class);
            }
            self::assertSame(substr_count($src, 'method="post"'), substr_count($src, 'Csrf::field()'), 'un jeton par formulaire : ' . $name);
            self::assertStringNotContainsString('alert(', $src);
            self::assertStringNotContainsString('<script', $src);
            self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src), 'aucun emoji : ' . $name);
        }
        $thread = $this->read('views/admin/system/contact_mailbox_thread.php');
        self::assertStringContainsString('translate="no"', $thread, 'le contenu des e-mails n’est pas traduit');
        self::assertStringContainsString('$mv[\'safe\']', $thread, 'seul le corps assaini est affiché tel quel');
        self::assertStringNotContainsString("\$m['body_html']", $thread);
        $css = $this->read('public/assets/css/contact-mailbox.css');
        self::assertStringContainsString('@media (max-width: 760px)', $css);
        self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $css));
    }

    public function testEnglishEntriesExistForTheMailboxScreens(): void
    {
        $dict = require self::ROOT . 'lang/en/site_auto.php';
        foreach (['Boîte de contact', 'Boîte non configurée', 'Nouveau message', 'Afficher les images', 'Répondre', 'Enregistrer le brouillon', 'Message envoyé.', 'Limite d’envois atteinte (%1 par heure). Réessayez plus tard.'] as $fr) {
            self::assertArrayHasKey($fr, $dict, $fr);
            self::assertNotSame('', $dict[$fr]);
        }
    }
}
