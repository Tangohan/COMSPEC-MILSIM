<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Services\Media\SignatureImageService;
use App\Support\SseImageStorage;
use App\Support\SseRichText;
use PDO;

/**
 * Signature d'un document SSE au pad (souris, doigt, stylet).
 *
 * Le dessin arrive en data URL PNG et est validé par SignatureImageService (vrai PNG, taille et encre contrôlées, PNG reconstruit),
 * puis rangé dans le stockage privé des images SSE (dossier sse-doc-signatures, nom aléatoire) : jamais dans le HTML. Chaque
 * signature est liée à un utilisateur authentifié (celui de la session, jamais un identifiant reçu du formulaire), horodatée, et
 * porte l'empreinte SHA-256 du document au moment de la signature (titre, classification, référence, thème et corps assaini).
 * Un document signé est verrouillé : plus aucune modification du contenu ; l'empreinte recalculée doit rester égale à celle de
 * chaque signature (vérification affichée sur la fiche).
 */
final class SseDocumentSignatureService
{
    /** @var callable|null */
    private $audit;

    public function __construct(
        private ?PDO $pdo = null,
        private ?SignatureImageService $images = null,
        ?callable $audit = null,
        private ?string $privateDir = null
    ) {
        $this->audit = $audit;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Empreinte SHA-256 du contenu qui fait foi (hors état, dates et signatures). @param array<string,mixed> $doc */
    public static function documentHash(array $doc): string
    {
        $parts = [
            (string) ($doc['title'] ?? ''),
            (string) ($doc['classification'] ?? ''),
            (string) ($doc['reference_code'] ?? ''),
            (string) ($doc['sheet_theme'] ?? ''),
            SseRichText::htmlOf((string) ($doc['body'] ?? '')),
        ];

        return hash('sha256', implode("\x1f", $parts));
    }

    /** @return list<array<string,mixed>> signatures du document, la plus ancienne d'abord */
    public function forDocument(int $tenantId, int $documentId): array
    {
        try {
            $st = $this->pdo()->prepare('SELECT * FROM sse_document_signatures WHERE tenant_id = :t AND document_id = :d ORDER BY id ASC');
            $st->execute(['t' => $tenantId, 'd' => $documentId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return []; // étape 5070 non passée : aucun document n'est signé
        }
    }

    public function isSigned(int $tenantId, int $documentId): bool
    {
        return $this->forDocument($tenantId, $documentId) !== [];
    }

    /**
     * Appose la signature de l'utilisateur authentifié.
     *
     * @param array<string,mixed> $doc document hydraté (de cette communauté)
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function sign(array $doc, int $tenantId, int $userId, string $signerLabel, string $dataUrl): array
    {
        $docId = (int) ($doc['id'] ?? 0);
        if ($tenantId < 1 || $docId < 1 || (int) ($doc['tenant_id'] ?? 0) !== $tenantId) {
            return ['ok' => false, 'error' => 'Document introuvable.'];
        }
        if ($userId < 1) {
            return ['ok' => false, 'error' => 'Seul un utilisateur connecté peut signer.'];
        }
        foreach ($this->forDocument($tenantId, $docId) as $existing) {
            if ((int) $existing['user_id'] === $userId) {
                return ['ok' => false, 'error' => 'Vous avez déjà signé ce document.'];
            }
        }
        $signerLabel = trim(mb_substr($signerLabel, 0, 120));
        if ($signerLabel === '') {
            return ['ok' => false, 'error' => 'Nom du signataire inconnu.'];
        }
        $images = $this->images ?? new SignatureImageService(new SseIdImageService(null, null, $this->privateDir));
        $stored = $images->fromDataUrl($dataUrl, $tenantId, 'sse_document');
        if (empty($stored['ok']) || !isset($stored['path'])) {
            return ['ok' => false, 'error' => (string) ($stored['error'] ?? 'Signature refusée.')];
        }
        $hash = self::documentHash($doc);
        try {
            $st = $this->pdo()->prepare(
                'INSERT INTO sse_document_signatures (tenant_id, document_id, user_id, signer_label, image_path, doc_hash, signed_at)
                 VALUES (:t, :d, :u, :l, :p, :h, :at)'
            );
            $st->execute(['t' => $tenantId, 'd' => $docId, 'u' => $userId, 'l' => $signerLabel, 'p' => $stored['path'], 'h' => $hash, 'at' => date('Y-m-d H:i:s')]);
            $id = (int) $this->pdo()->lastInsertId();
        } catch (\Throwable $e) {
            error_log('[sse.signature] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Signature non enregistrée : mise à jour de la base à lancer.'];
        }
        $this->journal([
            'tenant_id' => $tenantId, 'actor_user_id' => $userId, 'actor_label' => $signerLabel,
            'action' => 'sse.document.signed', 'object_type' => 'document', 'object_id' => $docId,
            'reason' => 'Signature au pad', 'after' => ['signature_id' => $id, 'doc_hash' => $hash],
        ]);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Les signatures sont-elles toujours valables pour ce contenu ?
     *
     * @param array<string,mixed> $doc
     * @param list<array<string,mixed>> $signatures
     * @return array<int,bool> id de signature => empreinte concordante
     */
    public static function verify(array $doc, array $signatures): array
    {
        $hash = self::documentHash($doc);
        $out = [];
        foreach ($signatures as $s) {
            $out[(int) $s['id']] = hash_equals((string) ($s['doc_hash'] ?? ''), $hash);
        }

        return $out;
    }

    /** Fichier image d'une signature de cette communauté (null si le chemin ou le fichier n'est pas valable). @param array<string,mixed> $sig */
    public function imageFile(array $sig, int $tenantId): ?string
    {
        $path = (string) ($sig['image_path'] ?? '');
        if ((int) ($sig['tenant_id'] ?? 0) !== $tenantId
            || preg_match('#^sse-private/sse-doc-signatures/' . $tenantId . '_[a-f0-9]{16}\.png$#', $path) !== 1) {
            return null;
        }

        return SseImageStorage::absolute($path, null, $this->privateDir);
    }

    /** @param array<string,mixed> $entry */
    private function journal(array $entry): void
    {
        try {
            if ($this->audit !== null) {
                ($this->audit)($entry);

                return;
            }
            (new \App\Repositories\SseAuditLogRepository())->write($entry);
        } catch (\Throwable $e) {
            error_log('[sse.signature] journal : ' . $e->getMessage());
        }
    }
}
