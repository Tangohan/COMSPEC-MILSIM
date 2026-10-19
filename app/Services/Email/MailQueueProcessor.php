<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Repositories\AsyncJobRepository;
use App\Services\EmailService;

/**
 * Vide la file async_jobs des e-mails (MAIL_QUEUE, envois reportés par le quota quotidien).
 * Utilisé par la tâche automatique « mail_queue » et par worker-jobs.php.
 */
final class MailQueueProcessor
{
    public function __construct(
        private AsyncJobRepository $jobs,
        private EmailService $email,
    ) {}

    /**
     * @return array{processed:int, sent:int, failed:int}
     */
    public function run(int $max = 50, int $maxSeconds = 60): array
    {
        $started = time();
        $out = ['processed' => 0, 'sent' => 0, 'failed' => 0];
        for ($i = 0; $i < max(1, $max); $i++) {
            if ((time() - $started) >= $maxSeconds) {
                break;
            }
            $row = $this->jobs->claimNext();
            if ($row === null) {
                break;
            }
            $id = (int) $row['id'];
            $type = (string) ($row['job_type'] ?? '');
            if ($type !== 'email_send') {
                $this->jobs->release($id, 'Type de tâche non géré : ' . $type, 120);
                continue;
            }
            $data = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($data)) {
                $this->jobs->delete($id);
                continue;
            }
            $ok = $this->email->send(
                (string) ($data['eventCode'] ?? ''),
                (string) ($data['to'] ?? ''),
                (string) ($data['subject'] ?? ''),
                (string) ($data['htmlBody'] ?? ''),
                (string) ($data['textBody'] ?? ''),
                isset($data['tenantId']) ? (int) $data['tenantId'] : null,
                isset($data['replyTo']) ? (string) $data['replyTo'] : null,
                isset($data['payloadSummary']) && is_array($data['payloadSummary']) ? $data['payloadSummary'] : null,
                isset($data['campaignId']) ? (int) $data['campaignId'] : null,
                false,
                true,
                EmailService::attachmentsFromQueue($data['attachments'] ?? [])
            );
            $this->jobs->delete($id);
            $out['processed']++;
            if ($ok) {
                $out['sent']++;
            } else {
                $out['failed']++;
            }
        }

        return $out;
    }
}
