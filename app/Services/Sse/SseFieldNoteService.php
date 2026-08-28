<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCaseRepository;
use App\Repositories\SseFieldNoteRepository;
use App\Services\Media\ImageCompressionService;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\SseFieldNoteCatalog;

/**
 * Enregistrement des fiches de renseignement simplifiées.
 *
 * Le service reste le point d'entrée unique pour les deux surfaces de saisie :
 * le rédacteur du portail et le rédacteur plein écran de l'ATAK. Les règles
 * (longueur du texte, nombre de pièces jointes, classement, journalisation)
 * sont donc identiques des deux côtés.
 */
final class SseFieldNoteService
{
    private const UPLOAD_RELATIVE_DIR = 'uploads/sse/fiches';

    /** @var array<string, string> Formats de document acceptés en pièce jointe. */
    private const DOCUMENT_MIMES = [
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
    ];

    private const DOCUMENT_MAX_BYTES = 8_000_000;

    public function __construct(
        private ?SseFieldNoteRepository $notes = null,
        private ?SseCaseRepository $cases = null,
        private ?SseIntelFoundationService $intelFoundation = null,
        private ?AtakActivityLogService $activityLog = null,
        private ?ImageCompressionService $images = null,
    ) {
        $this->notes ??= new SseFieldNoteRepository();
        $this->cases ??= new SseCaseRepository();
        $this->intelFoundation ??= new SseIntelFoundationService();
        $this->activityLog ??= new AtakActivityLogService();
        $this->images ??= new ImageCompressionService();
    }

    public function repository(): SseFieldNoteRepository
    {
        return $this->notes;
    }

    /**
     * Valide une saisie avant enregistrement.
     *
     * @param array<string, mixed> $input
     * @return list<string> Messages destinés au rédacteur, vide si tout va bien
     */
    public function validate(array $input): array
    {
        $errors = [];

        $body = SseFieldNoteCatalog::normalizeBody($input['body'] ?? '');
        if ($body === '') {
            $errors[] = 'Écrivez le renseignement dans le cadre de rédaction avant de valider.';
        } elseif (mb_strlen($body) < 10) {
            $errors[] = 'Le renseignement est trop court pour être exploitable. Précisez ce que vous avez constaté.';
        }

        $observedRaw = trim((string) ($input['observed_at'] ?? ''));
        if ($observedRaw !== '') {
            $ts = strtotime(str_replace('T', ' ', $observedRaw));
            if ($ts === false) {
                $errors[] = 'La date de l’événement n’est pas lisible. Reprenez-la au format jour/mois/année.';
            } elseif ($ts > time() + 3600) {
                $errors[] = 'La date de l’événement est dans le futur. Corrigez-la avant de valider.';
            }
        }

        if (SseFieldNoteCatalog::normalizeThemes($input['themes'] ?? []) === []) {
            $errors[] = 'Choisissez au moins un thème : c’est ce qui orientera la fiche vers le bon analyste.';
        }

        return $errors;
    }

    /**
     * Crée une fiche puis la publie au journal des transmissions.
     *
     * @param array<string, mixed> $input
     * @return array{note: array<string, mixed>, created: bool}
     */
    public function create(int $tenantId, array $input): array
    {
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            $existing = $this->notes->findByIdempotencyKey($tenantId, $idempotencyKey);
            if ($existing !== null) {
                return ['note' => $existing, 'created' => false];
            }
        }

        $caseId = $this->resolveCaseId($tenantId, $input);

        $noteId = $this->notes->create($tenantId, [
            'context_id' => (int) ($input['context_id'] ?? 1),
            'note_kind' => $input['note_kind'] ?? null,
            'themes' => $input['themes'] ?? [],
            'title' => $input['title'] ?? '',
            'body' => $input['body'] ?? '',
            'observed_at' => $input['observed_at'] ?? null,
            'place_label' => $input['place_label'] ?? null,
            'grid_reference' => $input['grid_reference'] ?? null,
            'pos_x' => $input['pos_x'] ?? null,
            'pos_y' => $input['pos_y'] ?? null,
            'pos_z' => $input['pos_z'] ?? null,
            'lat' => $input['lat'] ?? null,
            'lng' => $input['lng'] ?? null,
            'urgency' => $input['urgency'] ?? null,
            'intel_source' => $input['intel_source'] ?? null,
            'classification' => $input['classification'] ?? null,
            'source_reliability' => $input['source_reliability'] ?? 'C',
            'info_credibility' => $input['info_credibility'] ?? 3,
            'status' => $input['status'] ?? SseFieldNoteCatalog::DEFAULT_STATUS,
            'origin' => $input['origin'] ?? 'web',
            'author_label' => $input['author_label'] ?? null,
            'author_user_id' => $input['author_user_id'] ?? null,
            'author_steam_id' => $input['author_steam_id'] ?? null,
            'author_unit' => $input['author_unit'] ?? null,
            'case_id' => $caseId,
            'interest_case_id' => $input['interest_case_id'] ?? null,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
        ]);

        $note = $this->notes->findForTenant($tenantId, $noteId);
        if ($note === null) {
            return ['note' => ['id' => $noteId], 'created' => true];
        }
        $note['attachments'] = [];

        $this->publish($tenantId, $note);

        return ['note' => $note, 'created' => true];
    }

    /**
     * Journal d'activité + événement de renseignement (visible dans les
     * transmissions terrain et l'inbox du bureau).
     *
     * @param array<string, mixed> $note
     */
    public function publish(int $tenantId, array $note): void
    {
        $author = trim((string) ($note['author_label'] ?? '')) ?: 'Terrain';
        $reference = (string) ($note['reference_code'] ?? '');
        $summary = sprintf(
            'Fiche de renseignement %s — %s%s',
            $reference,
            (string) ($note['note_kind_label'] ?? 'Fiche'),
            trim((string) ($note['place_label'] ?? '')) !== ''
                ? ' (' . (string) $note['place_label'] . ')'
                : ''
        );

        try {
            $this->activityLog->record(
                $tenantId,
                (int) ($note['context_id'] ?? 1),
                'SSE_FIELD_NOTE',
                $summary,
                $author
            );
        } catch (\Throwable) {
            // Le journal d'activité ne doit jamais faire perdre une fiche.
        }

        try {
            $this->intelFoundation->recordEvent([
                'tenant_id' => $tenantId,
                'context_id' => (int) ($note['context_id'] ?? 1),
                'case_id' => $note['case_id'] ?? null,
                'event_type' => 'REPORT_RECEIVED',
                'source_system' => match ((string) ($note['origin'] ?? 'web')) {
                    'atak' => 'CTAB',
                    'arma' => 'ARMA_SSE',
                    default => 'MANUAL',
                },
                'identity_tier' => 'DECLARED',
                'event_time' => (string) ($note['observed_at'] ?? gmdate('Y-m-d H:i:s')),
                'author_label' => $author,
                'unit_label' => $note['author_unit'] ?? null,
                'lat' => $note['lat'] ?? $note['pos_x'] ?? null,
                'lng' => $note['lng'] ?? $note['pos_y'] ?? null,
                'source_reliability' => $note['source_reliability'] ?? 'C',
                'info_credibility' => $note['info_credibility'] ?? 3,
                'summary' => $summary,
                'payload' => [
                    'field_note_id' => (int) ($note['id'] ?? 0),
                    'reference_code' => $reference,
                    'note_kind' => (string) ($note['note_kind'] ?? ''),
                    'note_kind_label' => (string) ($note['note_kind_label'] ?? ''),
                    'themes' => $note['theme_labels'] ?? [],
                    'theme_codes' => $note['themes'] ?? [],
                    'urgency_label' => (string) ($note['urgency_label'] ?? ''),
                    'intel_source' => $note['intel_source'] ?? null,
                    'title' => $note['title'] ?? '',
                    'place_label' => $note['place_label'] ?? null,
                    'grid_reference' => $note['grid_reference'] ?? null,
                    'body' => (string) ($note['body'] ?? ''),
                    'origin_label' => (string) ($note['origin_label'] ?? ''),
                    'attachment_count' => (int) ($note['attachment_count'] ?? 0),
                ],
                'idempotency_key' => 'field-note-' . $tenantId . '-' . (int) ($note['id'] ?? 0),
            ]);
        } catch (\Throwable) {
            // Idem : l'indexation est un confort, pas une condition d'enregistrement.
        }
    }

    /**
     * Attache un fichier reçu en envoi multipart.
     *
     * @param array<string, mixed> $file entrée $_FILES[...]
     * @param array<string, mixed> $meta légende, position, auteur
     * @return array{ok: bool, error: ?string, attachment: ?array<string, mixed>}
     */
    public function attachUploadedFile(int $tenantId, int $noteId, array $file, array $meta = []): array
    {
        if ($this->notes->countAttachments($tenantId, $noteId) >= SseFieldNoteCatalog::ATTACHMENTS_MAX) {
            return [
                'ok' => false,
                'error' => sprintf(
                    'Cette fiche porte déjà %d pièces jointes, le maximum autorisé.',
                    SseFieldNoteCatalog::ATTACHMENTS_MAX
                ),
                'attachment' => null,
            ];
        }

        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Aucune pièce jointe reçue.', 'attachment' => null];
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            return [
                'ok' => false,
                'error' => 'La pièce jointe n’a pas pu être reçue. Réessayez.',
                'attachment' => null,
            ];
        }

        $tmp = (string) $file['tmp_name'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = is_file($tmp) ? (string) ($finfo->file($tmp) ?: '') : '';
        $originalName = trim((string) ($file['name'] ?? ''));

        $absoluteDir = base_path('public/' . self::UPLOAD_RELATIVE_DIR);
        $relativePath = null;
        $bytes = 0;

        if (str_starts_with($mime, 'image/')) {
            $stored = $this->images->storeUpload(
                $file,
                $absoluteDir,
                self::UPLOAD_RELATIVE_DIR,
                'fiche' . $noteId
            );
            if (!($stored['ok'] ?? false) || empty($stored['relative'])) {
                return [
                    'ok' => false,
                    'error' => (string) ($stored['error'] ?? 'Impossible d’enregistrer la pièce jointe.'),
                    'attachment' => null,
                ];
            }
            $relativePath = (string) $stored['relative'];
            $bytes = (int) ($stored['bytes'] ?? 0);
        } elseif (isset(self::DOCUMENT_MIMES[$mime])) {
            $size = (int) ($file['size'] ?? 0);
            if ($size > self::DOCUMENT_MAX_BYTES) {
                return [
                    'ok' => false,
                    'error' => 'Le document dépasse 8 Mo. Joignez une version plus légère.',
                    'attachment' => null,
                ];
            }
            if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
                return ['ok' => false, 'error' => 'Stockage indisponible.', 'attachment' => null];
            }
            $name = sprintf(
                'fiche%d_%d_%s.%s',
                $noteId,
                time(),
                bin2hex(random_bytes(4)),
                self::DOCUMENT_MIMES[$mime]
            );
            if (!@move_uploaded_file($tmp, $absoluteDir . DIRECTORY_SEPARATOR . $name)) {
                return ['ok' => false, 'error' => 'Impossible d’enregistrer la pièce jointe.', 'attachment' => null];
            }
            $relativePath = self::UPLOAD_RELATIVE_DIR . '/' . $name;
            $bytes = $size;
        } else {
            return [
                'ok' => false,
                'error' => 'Formats acceptés : photo (JPEG, PNG, WebP), document PDF ou texte.',
                'attachment' => null,
            ];
        }

        $kind = strtolower(trim((string) ($meta['kind'] ?? '')));
        if (!isset(SseFieldNoteCatalog::ATTACHMENT_KINDS[$kind])) {
            $kind = str_starts_with($mime, 'image/') ? 'photo' : 'document';
        }

        $attachmentId = $this->notes->addAttachment($tenantId, $noteId, [
            'file_path' => $relativePath,
            'original_name' => $originalName !== '' ? $originalName : null,
            'mime_type' => $mime,
            'byte_size' => $bytes,
            'kind' => $kind,
            'caption' => $meta['caption'] ?? null,
            'grid_reference' => $meta['grid_reference'] ?? null,
            'pos_x' => $meta['pos_x'] ?? null,
            'pos_y' => $meta['pos_y'] ?? null,
            'pos_z' => $meta['pos_z'] ?? null,
            'author_label' => $meta['author_label'] ?? null,
        ]);

        $attachment = null;
        foreach ($this->notes->listAttachments($tenantId, $noteId) as $row) {
            if ((int) ($row['id'] ?? 0) === $attachmentId) {
                $attachment = $row;
                break;
            }
        }

        return ['ok' => true, 'error' => null, 'attachment' => $attachment];
    }

    /**
     * Attache un ou plusieurs fichiers reçus dans le même envoi de formulaire.
     *
     * @param array<string, mixed> $filesEntry entrée $_FILES['pieces'] (tableau multiple)
     * @param array<string, mixed> $meta
     * @return array{stored: int, errors: list<string>}
     */
    public function attachUploadedBatch(int $tenantId, int $noteId, array $filesEntry, array $meta = []): array
    {
        $stored = 0;
        $errors = [];

        $names = $filesEntry['name'] ?? null;
        if (!is_array($names)) {
            if (($filesEntry['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return ['stored' => 0, 'errors' => []];
            }
            $result = $this->attachUploadedFile($tenantId, $noteId, $filesEntry, $meta);
            if ($result['ok']) {
                $stored++;
            } elseif ($result['error'] !== null) {
                $errors[] = $result['error'];
            }

            return ['stored' => $stored, 'errors' => $errors];
        }

        foreach (array_keys($names) as $index) {
            $single = [
                'name' => $filesEntry['name'][$index] ?? '',
                'type' => $filesEntry['type'][$index] ?? '',
                'tmp_name' => $filesEntry['tmp_name'][$index] ?? '',
                'error' => $filesEntry['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesEntry['size'][$index] ?? 0,
            ];
            if ((int) $single['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $result = $this->attachUploadedFile($tenantId, $noteId, $single, $meta);
            if ($result['ok']) {
                $stored++;
            } elseif ($result['error'] !== null) {
                $errors[] = $result['error'];
            }
        }

        return ['stored' => $stored, 'errors' => array_values(array_unique($errors))];
    }

    public function deleteAttachment(int $tenantId, int $noteId, int $attachmentId): bool
    {
        $row = $this->notes->findAttachment($tenantId, $noteId, $attachmentId);
        if ($row === null) {
            return false;
        }
        $path = trim((string) ($row['file_path'] ?? ''));
        if ($path !== '' && !str_contains($path, '..')) {
            $absolute = base_path('public/' . ltrim($path, '/'));
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        return $this->notes->deleteAttachment($tenantId, $noteId, $attachmentId);
    }

    /**
     * Charge une fiche complète (pièces jointes incluses).
     *
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, int $noteId): ?array
    {
        $note = $this->notes->findForTenant($tenantId, $noteId);
        if ($note === null) {
            return null;
        }
        $note['attachments'] = $this->notes->listAttachments($tenantId, $noteId);
        $note['attachment_count'] = count($note['attachments']);

        return $note;
    }

    /**
     * Résout le classement : identifiant direct, sinon code de dossier saisi
     * sur le terrain.
     *
     * @param array<string, mixed> $input
     */
    private function resolveCaseId(int $tenantId, array $input): ?int
    {
        $caseId = (int) ($input['case_id'] ?? 0);
        if ($caseId > 0) {
            return $caseId;
        }

        $code = strtoupper(trim((string) ($input['case_code'] ?? '')));
        if ($code === '') {
            return null;
        }

        try {
            $case = $this->cases->findByReferenceCode($tenantId, $code);
        } catch (\Throwable) {
            return null;
        }

        return is_array($case) && isset($case['id']) ? (int) $case['id'] : null;
    }
}
