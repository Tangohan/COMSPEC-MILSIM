<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Audit\AuditService;
use App\Services\ContactMail\AttachmentStorage;
use App\Services\ContactMail\ContactMailConfig;
use App\Services\ContactMail\ContactMailFactory;
use App\Services\ContactMail\ContactMailRepository;
use App\Services\ContactMail\ContactMailSender;
use App\Services\ContactMail\DownloadName;
use App\Services\ContactMail\HtmlSanitizer;
use App\Services\ContactMail\MailHeaderGuard;
use App\Services\ContactMail\ReplyComposer;
use App\Services\Security\FileRateLimiter;

/**
 * /admin/system/boite-contact : boîte de contact de la plateforme (messages reçus par IMAP, réponses par SMTP).
 * Réservée à l'administrateur de la plateforme (SystemAdminMiddleware sur chaque route). Sans CONTACT_MAIL_PASSWORD,
 * l'écran n'affiche que l'état « non configurée » : aucune requête, aucune connexion.
 */
final class SystemContactMailboxController
{
    private const BASE = 'admin/system/boite-contact';

    /** Types servis tels quels au téléchargement ; tout le reste part en application/octet-stream. */
    private const SAFE_MIME = [
        'application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/plain', 'text/csv', 'application/zip',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet',
    ];

    private const INLINE_IMAGE_MIME = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    public function __construct(
        private ?ContactMailConfig $config = null,
        private ?ContactMailRepository $repo = null,
        private ?AttachmentStorage $storage = null,
        private ?ContactMailSender $sender = null,
    ) {
        $this->config ??= ContactMailFactory::config();
        $this->repo ??= ContactMailFactory::repository();
        $this->storage ??= new AttachmentStorage();
    }

    /** Liste des fils, ou état « non configurée » (aucune requête dans ce cas). */
    public function index(Request $request, array $params = []): Response
    {
        $data = $this->baseData('Boîte de contact');
        if ($data['mbState'] !== 'ready') {
            return Response::view('layout.main', $data);
        }
        $folder = $request->queryString('dossier', 'inbox');
        $folder = in_array($folder, ContactMailRepository::FOLDERS, true) ? $folder : 'inbox';
        $query = mb_substr(trim($request->queryString('q', '')), 0, 100);
        $page = max(1, (int) $request->queryString('page', '1'));
        try {
            $list = $this->repo->listThreads($folder, $query, $page);
            $data['mbCounts'] = $this->repo->counts();
            $data['mbSync'] = $this->repo->syncState('INBOX');
        } catch (\Throwable) {
            $data['mbState'] = 'no_tables';

            return Response::view('layout.main', $data);
        }
        $data['content'] = 'admin.system.contact_mailbox';

        return Response::view('layout.main', $data + [
            'mbThreads' => $list['rows'],
            'mbTotal' => $list['total'],
            'mbPage' => $page,
            'mbPerPage' => ContactMailRepository::PER_PAGE,
            'mbFolder' => $folder,
            'mbQuery' => $query,
        ]);
    }

    /** Fil de discussion : messages, pièces jointes, zone de réponse. */
    public function thread(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $data = $this->baseData('Boîte de contact');
        if ($data['mbState'] !== 'ready') {
            return Response::view('layout.main', $data);
        }
        $thread = $this->repo->thread($id);
        if ($thread === null) {
            Session::flash('error', 'Ce fil n’existe plus.');

            return Response::redirect(url(self::BASE));
        }
        $messages = $this->repo->messages($id);
        $unreadBefore = (int) $thread['unread_count'];
        if ($unreadBefore > 0) {
            $this->repo->setThreadRead($id, true);
        }
        $showImages = $request->queryString('images', '') === '1';
        $htmlView = $request->queryString('vue', '') === 'html';
        $attachments = $this->repo->attachmentsFor(array_map(static fn (array $m): int => (int) $m['id'], $messages));

        $views = [];
        foreach ($messages as $m) {
            $mid = (int) $m['id'];
            $files = $attachments[$mid] ?? [];
            $cid = [];
            foreach ($files as $f) {
                if ($f['content_id'] !== '' && $f['storage_path'] !== null && in_array((string) $f['mime'], self::INLINE_IMAGE_MIME, true)) {
                    $cid[(string) $f['content_id']] = url(self::BASE . '/piece/' . (int) $f['id']);
                }
            }
            $html = (string) ($m['body_html'] ?? '');
            $useHtml = $htmlView && $html !== '';
            $views[] = [
                'row' => $m,
                'has_html' => $html !== '',
                'as_html' => $useHtml,
                'safe' => $useHtml
                    ? HtmlSanitizer::sanitize($html, $showImages, static fn (string $c): ?string => $cid[$c] ?? null)
                    : HtmlSanitizer::textToHtml((string) ($m['body_text'] ?? '')),
                'files' => $files,
                'to' => self::decodeList($m['to_json'] ?? null),
                'cc' => self::decodeList($m['cc_json'] ?? null),
            ];
        }

        $target = null;
        foreach ($messages as $m) {
            if ($m['direction'] === 'in') {
                $target = $m;
            }
        }
        $defaults = ReplyComposer::defaults($thread, $target, $this->config);
        $draft = $this->repo->draft($id);

        $data['content'] = 'admin.system.contact_mailbox_thread';
        $data['title'] = (string) $thread['subject'] !== '' ? (string) $thread['subject'] : 'Boîte de contact';

        return Response::view('layout.main', $data + [
            'mbThread' => $thread,
            'mbMessages' => $views,
            'mbReply' => $defaults,
            'mbReplyTarget' => $target !== null ? (int) $target['id'] : 0,
            'mbDraft' => $draft,
            'mbShowImages' => $showImages,
            'mbHtmlView' => $htmlView,
            'mbSendLimit' => ['max' => $this->config->sendPerHour()],
            'mbUploadLimits' => ['count' => ContactMailSender::MAX_ATTACHMENTS, 'mb' => (int) (ContactMailSender::MAX_ATTACHMENT_BYTES / 1048576)],
        ]);
    }

    public function compose(Request $request, array $params = []): Response
    {
        $data = $this->baseData('Nouveau message');
        if ($data['mbState'] !== 'ready') {
            return Response::view('layout.main', $data);
        }
        $to = '';
        $prefill = trim($request->queryString('a', ''));
        if ($prefill !== '') {
            try {
                $to = MailHeaderGuard::address($prefill);
            } catch (\InvalidArgumentException) {
                $to = '';
            }
        }
        $data['content'] = 'admin.system.contact_mailbox_compose';

        return Response::view('layout.main', $data + [
            'mbTo' => $to,
            'mbBody' => ReplyComposer::newMessageBody($this->config),
            'mbUploadLimits' => ['count' => ContactMailSender::MAX_ATTACHMENTS, 'mb' => (int) (ContactMailSender::MAX_ATTACHMENT_BYTES / 1048576)],
        ]);
    }

    /** Envoi d'un nouveau message ou d'une réponse. */
    public function send(Request $request, array $params = []): Response
    {
        $threadId = (int) $request->inputString('thread_id', '0');
        $back = $threadId > 0 ? url(self::BASE . '/fil/' . $threadId) : url(self::BASE . '/nouveau');
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!$this->guard()) {
            return Response::redirect(url(self::BASE));
        }
        $input = [
            'thread_id' => $threadId > 0 ? $threadId : null,
            'reply_to_message_id' => (int) $request->inputString('reply_to_message_id', '0'),
            'draft_id' => (int) $request->inputString('draft_id', '0'),
            'to' => $request->inputString('to', ''),
            'cc' => $request->inputString('cc', ''),
            'subject' => $request->inputString('subject', ''),
            'body' => $request->inputString('body', ''),
        ];
        $files = self::uploadedFiles();
        if (is_string($files)) {
            Session::flash('error', $files);
            $this->rememberForm($input);

            return Response::redirect($back);
        }
        $input['attachments'] = $files;

        $result = ($this->sender ?? ContactMailFactory::sender())->send($input);
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Envoi impossible.'));
            $this->rememberForm($input);

            return Response::redirect($back);
        }
        Session::flash('success', 'Message envoyé.');

        return Response::redirect(url(self::BASE . '/fil/' . (int) ($result['thread_id'] ?? 0)));
    }

    public function saveDraft(Request $request, array $params = []): Response
    {
        $threadId = (int) $request->inputString('thread_id', '0');
        $back = $threadId > 0 ? url(self::BASE . '/fil/' . $threadId) : url(self::BASE . '/nouveau');
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!$this->guard()) {
            return Response::redirect(url(self::BASE));
        }
        $result = ($this->sender ?? ContactMailFactory::sender())->saveDraft([
            'thread_id' => $threadId > 0 ? $threadId : null,
            'to' => $request->inputString('to', ''),
            'cc' => $request->inputString('cc', ''),
            'subject' => $request->inputString('subject', ''),
            'body' => $request->inputString('body', ''),
        ]);
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Brouillon non enregistré.'));

            return Response::redirect($back);
        }
        Session::flash('success', 'Brouillon enregistré.');

        return Response::redirect(url(self::BASE . '/fil/' . (int) ($result['thread_id'] ?? 0)));
    }

    /** Lu, non lu, étoile, à traiter, archive, spam, corbeille, suppression définitive d'un fil de la corbeille. */
    public function action(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $back = url(self::BASE . '/fil/' . $id);
        $list = url(self::BASE . '?dossier=' . rawurlencode($request->inputString('dossier', 'inbox')));
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!$this->guard()) {
            return Response::redirect(url(self::BASE));
        }
        $thread = $this->repo->thread($id);
        if ($thread === null) {
            Session::flash('error', 'Ce fil n’existe plus.');

            return Response::redirect(url(self::BASE));
        }
        $action = $request->inputString('action', '');
        $leave = false;
        switch ($action) {
            case 'read':
                $this->repo->setThreadRead($id, true);
                $leave = true;
                break;
            case 'unread':
                $this->repo->setThreadRead($id, false);
                $leave = true;
                break;
            case 'star':
                $this->repo->setStarred($id, true);
                break;
            case 'unstar':
                $this->repo->setStarred($id, false);
                break;
            case 'todo':
                $this->repo->setTodo($id, true);
                break;
            case 'done':
                $this->repo->setTodo($id, false);
                break;
            case 'archive':
                $this->repo->setStatus($id, 'archived');
                $this->repo->setTodo($id, false);
                $leave = true;
                break;
            case 'inbox':
                $this->repo->setStatus($id, 'inbox');
                break;
            case 'spam':
                $this->repo->setStatus($id, 'spam');
                $this->repo->setTodo($id, false);
                $leave = true;
                break;
            case 'trash':
                $this->repo->setStatus($id, 'trash');
                $this->repo->setTodo($id, false);
                $leave = true;
                break;
            case 'purge':
                if (($thread['status'] ?? '') !== 'trash') {
                    Session::flash('error', 'Seul un fil de la corbeille se supprime définitivement.');

                    return Response::redirect($back);
                }
                foreach ($this->repo->purgeThread($id) as $path) {
                    $this->storage->delete($path);
                }
                AuditService::logPlatformAction('platform.contact_mail_thread_purged', ['thread_id' => $id]);
                Session::flash('success', 'Fil supprimé définitivement.');

                return Response::redirect(url(self::BASE . '?dossier=trash'));
            case 'discard_draft':
                $draft = $this->repo->draft($id);
                if ($draft !== null) {
                    $this->repo->deleteMessage((int) $draft['id']);
                }
                if (($thread['status'] ?? '') === 'draft') {
                    $this->repo->purgeThread($id);

                    return Response::redirect(url(self::BASE . '?dossier=draft'));
                }
                break;
            default:
                Session::flash('error', 'Action inconnue.');

                return Response::redirect($back);
        }
        if (in_array($action, ['archive', 'spam', 'trash'], true)) {
            Session::flash('success', match ($action) {
                'archive' => 'Fil archivé.',
                'spam' => 'Fil classé comme spam.',
                default => 'Fil placé dans la corbeille.',
            });
        }

        return Response::redirect($leave ? $list : $back);
    }

    /** « Actualiser » : une synchronisation IMAP tout de suite (au plus une toutes les 20 secondes). */
    public function sync(Request $request, array $params = []): Response
    {
        $back = url(self::BASE);
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!$this->guard()) {
            return Response::redirect($back);
        }
        if ((new FileRateLimiter())->tooManyAttempts('contact_mail_sync_now', 1, 20)) {
            Session::flash('error', 'Une actualisation vient d’avoir lieu : patientez quelques secondes.');

            return Response::redirect($back);
        }
        $r = ContactMailFactory::sync()->run(100, 25);
        if ($r['busy']) {
            Session::flash('error', 'Une synchronisation est déjà en cours.');
        } elseif (!$r['ok']) {
            Session::flash('error', 'Synchronisation impossible : ' . $r['reason']);
        } else {
            Session::flash('success', $r['imported'] > 0
                ? 'Boîte actualisée : ' . $r['imported'] . ' nouveau(x) message(s).'
                : 'Boîte actualisée : aucun nouveau message.');
        }

        return Response::redirect($back);
    }

    /** Téléchargement d'une pièce jointe : jamais exécutée, jamais servie depuis public/. */
    public function attachment(Request $request, array $params = []): Response
    {
        $data = $this->baseData('Boîte de contact');
        if ($data['mbState'] !== 'ready') {
            return (new Response())->setStatusCode(404)->setBody('Introuvable.');
        }
        $row = $this->repo->attachment((int) ($params['id'] ?? 0));
        $abs = $row !== null && $row['storage_path'] !== null ? $this->storage->resolve((string) $row['storage_path']) : null;
        if ($row === null || $abs === null) {
            return (new Response())->setStatusCode(404)->setBody('Pièce jointe introuvable.');
        }
        $mime = strtolower((string) $row['mime']);
        $type = in_array($mime, self::SAFE_MIME, true) ? $mime : 'application/octet-stream';
        if ($type === 'text/plain' || $type === 'text/csv') {
            $type .= '; charset=utf-8';
        }
        $name = DownloadName::clean((string) $row['filename']);
        $inline = $request->queryString('vue', '') === 'image' && in_array($mime, self::INLINE_IMAGE_MIME, true);
        AuditService::logPlatformAction('platform.contact_mail_attachment_downloaded', ['attachment_id' => (int) $row['id'], 'thread_id' => (int) $row['thread_id']]);

        return (new Response())
            ->header('Content-Type', $type)
            ->header('Content-Length', (string) filesize($abs))
            ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; ' . DownloadName::dispositionParams($name))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; sandbox")
            ->header('Cache-Control', 'private, no-store')
            ->setBodyStream(static function () use ($abs): void {
                readfile($abs);
            });
    }

    /**
     * Données communes : état de la boîte (non configurée, tables absentes, prête) et réglages affichables, sans secret.
     *
     * @return array<string, mixed>
     */
    private function baseData(string $title): array
    {
        $configured = $this->config->isConfigured();
        $state = !$configured ? 'unconfigured' : ($this->repo->ready() ? 'ready' : 'no_tables');

        return [
            'title' => $title,
            'content' => 'admin.system.contact_mailbox',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css', 'contact-mailbox.css'],
            'mbState' => $state,
            'mbMissing' => $this->config->missingKeys(),
            'mbKeys' => ContactMailConfig::KEYS,
            'mbAddress' => $this->config->address(),
            'mbImap' => $this->config->imapHost() . ':' . $this->config->imapPort(),
            'mbSmtp' => $this->config->smtpHost() . ':' . $this->config->smtpPort(),
        ];
    }

    /** Boîte prête, sinon message d'erreur et false. */
    private function guard(): bool
    {
        if (!$this->config->isConfigured()) {
            Session::flash('error', 'Boîte non configurée : renseignez CONTACT_MAIL_PASSWORD dans le .env du serveur.');

            return false;
        }
        if (!$this->repo->ready()) {
            Session::flash('error', 'Les tables de la boîte de contact sont absentes (migration non passée).');

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function rememberForm(array $input): void
    {
        Session::flash('mb_form', [
            'to' => (string) ($input['to'] ?? ''),
            'cc' => (string) ($input['cc'] ?? ''),
            'subject' => (string) ($input['subject'] ?? ''),
            'body' => (string) ($input['body'] ?? ''),
        ]);
    }

    /**
     * Fichiers déposés : liste prête pour le service d'envoi, ou message d'erreur.
     *
     * @return list<array{name: string, content: string}>|string
     */
    private static function uploadedFiles(): array|string
    {
        $f = $_FILES['attachments'] ?? null;
        if (!is_array($f) || !isset($f['name']) || !is_array($f['name'])) {
            return [];
        }
        $out = [];
        foreach ($f['name'] as $i => $name) {
            $error = (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $tmp = (string) ($f['tmp_name'][$i] ?? '');
            $size = (int) ($f['size'][$i] ?? 0);
            if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
                return 'Une pièce jointe n’a pas pu être reçue (taille ou envoi interrompu).';
            }
            if ($size > ContactMailSender::MAX_ATTACHMENT_BYTES) {
                return 'Une pièce jointe dépasse ' . (int) (ContactMailSender::MAX_ATTACHMENT_BYTES / 1048576) . ' Mo.';
            }
            $content = file_get_contents($tmp);
            if ($content === false) {
                return 'Une pièce jointe n’a pas pu être lue.';
            }
            $out[] = ['name' => (string) $name, 'content' => $content];
        }

        return $out;
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    private static function decodeList(mixed $json): array
    {
        $d = is_string($json) ? json_decode($json, true) : null;
        $out = [];
        foreach (is_array($d) ? $d : [] as $a) {
            if (is_array($a) && isset($a['email'])) {
                $out[] = ['name' => (string) ($a['name'] ?? ''), 'email' => (string) $a['email']];
            }
        }

        return $out;
    }
}
