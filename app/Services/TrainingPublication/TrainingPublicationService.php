<?php

declare(strict_types=1);

namespace App\Services\TrainingPublication;

use App\Repositories\TrainingPublicationAnnexRepository;
use App\Repositories\TrainingPublicationEvidenceRepository;
use App\Repositories\TrainingPublicationReadReceiptRepository;
use App\Repositories\TrainingPublicationRepository;
use App\Repositories\TrainingPublicationRevisionRepository;

class TrainingPublicationService
{
    public function __construct(
        private TrainingPublicationRepository $publicationRepository,
        private TrainingPublicationRevisionRepository $revisionRepository,
        private TrainingPublicationReadReceiptRepository $readReceiptRepository,
        private TrainingPublicationAnnexRepository $annexRepository,
        private TrainingPublicationEvidenceRepository $evidenceRepository,
        private LmsSourceService $lmsSource,
        private DocumentBuildService $builder,
        private SecurityPolicyService $security,
        private PublicationWorkflowService $workflow,
    ) {}

    public function createDraft(int $tenantId, int $userId, array $payload): array
    {
        $courseId = (int) ($payload['course_id'] ?? 0);
        if ($courseId <= 0) {
            throw new \RuntimeException('course_id requis.', 422);
        }

        $this->lmsSource->normalizedCourse($courseId, $tenantId);
        $validationChain = $this->workflow->defaultValidationChain();

        $id = $this->publicationRepository->create([
            'tenant_id' => $tenantId,
            'course_id' => $courseId,
            'courrier_template_id' => $payload['courrier_template_id'] ?? null,
            'document_id' => $payload['document_id'] ?? null,
            'status' => 'draft',
            'created_by' => $userId,
            'updated_by' => $userId,
            'access_policy_json' => $payload['access_policy_json'] ?? [],
            'validation_chain_json' => $validationChain,
            'publication_targets_json' => $payload['publication_targets_json'] ?? [],
            'expires_at' => $payload['expires_at'] ?? null,
            'diffusion_classification' => $payload['diffusion_classification'] ?? 'interne',
            'institutional_signature_json' => [
                'document_reference' => $payload['document_reference'] ?? ('TRN-' . date('Y') . '-' . $courseId),
                'author' => $payload['author'] ?? $userId,
            ],
        ]);

        $publication = $this->mustFind($id, $tenantId);
        $this->revisionRepository->create($id, $tenantId, [
            'change_summary' => 'Création brouillon',
            'compiled_payload_json' => [],
            'created_by' => $userId,
        ]);
        $this->evidenceRepository->log($tenantId, $id, $userId, 'publication.created', ['status' => 'draft']);

        return $publication;
    }

    public function compile(int $publicationId, int $tenantId, int $userId): array
    {
        $publication = $this->mustFind($publicationId, $tenantId);
        $this->security->assertClassificationAccess((string) ($publication['diffusion_classification'] ?? 'interne'));
        $normalizedLms = $this->lmsSource->normalizedCourse((int) $publication['course_id'], $tenantId);
        $annexes = $this->annexRepository->listByPublication($publicationId, $tenantId);

        $compiled = $this->builder->compile($normalizedLms, $publication, $annexes, $this->defaultReusableBlocks());
        $consistency = $this->computeLmsDocumentConsistency($normalizedLms, $compiled['compiled_payload']);
        $complianceScore = $this->computeComplianceScore($publication, $compiled, $consistency);
        $diff = $this->computeRevisionDiff($publicationId, $tenantId, $compiled['compiled_payload']);

        $this->publicationRepository->update($publicationId, $tenantId, [
            'hash_integrity' => $compiled['checksum'],
            'updated_by' => $userId,
            'qr_payload_json' => $compiled['compiled_payload']['qr'] ?? [],
            'watermark_payload_json' => [
                'identity' => $this->security->buildWatermarkIdentity($tenantId, $userId),
                'hash' => $compiled['watermark_hash'],
            ],
            'format_payload_json' => $compiled['formats'],
            'compliance_score' => $complianceScore,
        ]);

        $this->revisionRepository->create($publicationId, $tenantId, [
            'change_summary' => 'Compilation stateless',
            'pdf_snapshot_path' => $compiled['formats']['pdf_official'] ?? null,
            'compiled_payload_json' => $compiled['compiled_payload'],
            'diff_payload_json' => $diff,
            'qr_hash' => $compiled['qr_hash'],
            'watermark_hash' => $compiled['watermark_hash'],
            'integrity_check_passed' => 1,
            'created_by' => $userId,
        ]);

        $this->evidenceRepository->log($tenantId, $publicationId, $userId, 'publication.compiled', [
            'checksum' => $compiled['checksum'],
            'compliance_score' => $complianceScore,
            'impact' => $diff['impact'] ?? 'faible',
        ]);

        return [
            'pdf_url' => $compiled['formats']['pdf_official'] ?? '',
            'checksum' => $compiled['checksum'],
            'pages' => $compiled['pages'],
            'formats' => $compiled['formats'],
            'consistency' => $consistency,
            'diff' => $diff,
            'compliance_score' => $complianceScore,
        ];
    }

    public function validate(int $publicationId, int $tenantId, int $userId, string $actorRole, string $decision, ?string $comment): array
    {
        $publication = $this->mustFind($publicationId, $tenantId);
        if ((string) ($publication['hash_integrity'] ?? '') === '') {
            throw new \RuntimeException('Compilation requise avant validation.', 422);
        }
        $this->security->assertCrossModulePermissions();

        $chain = $this->decodeJson((string) ($publication['validation_chain_json'] ?? '[]'));
        $chain = $this->workflow->applyDecision($chain, $actorRole, $decision, $comment);

        if ($decision === 'rejected') {
            $this->workflow->transition((string) $publication['status'], 'draft');
            $nextStatus = 'draft';
        } elseif ($this->workflow->isFullyApproved($chain)) {
            $this->workflow->transition((string) $publication['status'], 'validated');
            $nextStatus = 'validated';
        } else {
            $this->workflow->transition((string) $publication['status'], 'review');
            $nextStatus = 'review';
        }

        $this->publicationRepository->update($publicationId, $tenantId, [
            'status' => $nextStatus,
            'validation_chain_json' => $chain,
            'updated_by' => $userId,
        ]);

        $this->evidenceRepository->log($tenantId, $publicationId, $userId, 'publication.validation.decision', [
            'actor_role' => $actorRole,
            'decision' => $decision,
            'comment' => $comment,
            'status' => $nextStatus,
        ]);

        return $this->mustFind($publicationId, $tenantId);
    }

    public function release(int $publicationId, int $tenantId, int $userId): array
    {
        $publication = $this->mustFind($publicationId, $tenantId);
        $this->security->assertCrossModulePermissions();
        if ((int) ($publication['compliance_score'] ?? 0) < 70) {
            throw new \RuntimeException('Score de conformité insuffisant.', 422);
        }

        $this->workflow->transition((string) $publication['status'], 'published');

        $this->publicationRepository->update($publicationId, $tenantId, [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
            'institutional_signature_json' => [
                'validated_stamp' => 'VALIDÉ',
                'validator_authority' => $userId,
                'signed_at' => gmdate('c'),
                'integrity_fingerprint' => (string) ($publication['hash_integrity'] ?? ''),
            ],
        ]);

        $this->revisionRepository->create($publicationId, $tenantId, [
            'change_summary' => 'Publication',
            'created_by' => $userId,
            'integrity_check_passed' => 1,
        ]);

        $this->evidenceRepository->log($tenantId, $publicationId, $userId, 'publication.released', [
            'targets' => $this->decodeJson((string) ($publication['publication_targets_json'] ?? '[]')),
        ]);

        return $this->mustFind($publicationId, $tenantId);
    }

    public function captureReadProof(int $publicationId, int $tenantId, int $userId, int $secondsRead, int $lastPage): void
    {
        $this->mustFind($publicationId, $tenantId);
        $this->readReceiptRepository->upsertProgress($publicationId, $tenantId, $userId, $secondsRead, $lastPage);
        $this->evidenceRepository->log($tenantId, $publicationId, $userId, 'publication.read.progress', [
            'seconds' => $secondsRead,
            'last_page' => $lastPage,
        ]);
    }

    public function attestReadProof(int $publicationId, int $tenantId, int $userId, ?int $quizScore, ?string $attestation): void
    {
        $this->mustFind($publicationId, $tenantId);
        $this->readReceiptRepository->attest($publicationId, $tenantId, $userId, $quizScore, $attestation);
        $this->evidenceRepository->log($tenantId, $publicationId, $userId, 'publication.read.attested', [
            'quiz_score' => $quizScore,
            'attestation' => $attestation,
        ]);
    }

    public function addAnnex(int $publicationId, int $tenantId, int $userId, array $payload): int
    {
        $this->mustFind($publicationId, $tenantId);
        $id = $this->annexRepository->create($publicationId, $tenantId, $userId, $payload);
        $this->evidenceRepository->log($tenantId, $publicationId, $userId, 'publication.annex.added', ['annex_id' => $id]);

        return $id;
    }

    public function markObsolete(int $publicationId, int $tenantId, int $userId, ?int $replacementPublicationId): array
    {
        $this->mustFind($publicationId, $tenantId);
        $this->publicationRepository->update($publicationId, $tenantId, [
            'status' => 'archived',
            'obsolete_at' => date('Y-m-d H:i:s'),
            'replacement_publication_id' => $replacementPublicationId,
            'updated_by' => $userId,
        ]);
        $this->evidenceRepository->log($tenantId, $publicationId, $userId, 'publication.obsolete', ['replacement_publication_id' => $replacementPublicationId]);

        return $this->mustFind($publicationId, $tenantId);
    }

    private function mustFind(int $id, int $tenantId): array
    {
        $row = $this->publicationRepository->findById($id, $tenantId);

        return $this->security->assertTenantScoped($row, $tenantId, $id);
    }

    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function computeRevisionDiff(int $publicationId, int $tenantId, array $newPayload): array
    {
        $previous = $this->revisionRepository->latestForPublication($publicationId, $tenantId);
        if (!$previous) {
            return ['impact' => 'majeur', 'chapters_added' => [], 'paragraphs_modified' => [], 'annexes_removed' => []];
        }

        $previousPayload = $this->decodeJson((string) ($previous['compiled_payload_json'] ?? '{}'));
        $oldChapters = array_map(static fn (array $c): string => (string) ($c['title'] ?? ''), (array) ($previousPayload['pages'] ?? []));
        $newChapters = array_map(static fn (array $c): string => (string) ($c['title'] ?? ''), (array) ($newPayload['pages'] ?? []));
        $chaptersAdded = array_values(array_diff($newChapters, $oldChapters));

        $oldAnnexes = array_map(static fn (array $a): string => (string) ($a['title'] ?? ''), (array) ($previousPayload['annexes'] ?? []));
        $newAnnexes = array_map(static fn (array $a): string => (string) ($a['title'] ?? ''), (array) ($newPayload['annexes'] ?? []));
        $annexesRemoved = array_values(array_diff($oldAnnexes, $newAnnexes));

        $paragraphsModified = [];
        if (hash('sha256', json_encode($previousPayload['pages'] ?? []) ?: '') !== hash('sha256', json_encode($newPayload['pages'] ?? []) ?: '')) {
            $paragraphsModified[] = 'content_blocks';
        }

        $impact = 'faible';
        if ($chaptersAdded !== [] || $annexesRemoved !== []) {
            $impact = 'majeur';
        } elseif ($paragraphsModified !== []) {
            $impact = 'moyen';
        }

        return [
            'chapters_added' => $chaptersAdded,
            'paragraphs_modified' => $paragraphsModified,
            'annexes_removed' => $annexesRemoved,
            'impact' => $impact,
        ];
    }

    private function computeLmsDocumentConsistency(array $normalizedLms, array $compiledPayload): array
    {
        $issues = [];
        $lmsObjectives = (array) ($normalizedLms['objectives'] ?? []);
        if ($lmsObjectives !== [] && !isset($compiledPayload['pages'])) {
            $issues[] = 'objectif LMS sans contenu documentaire';
        }
        foreach ((array) ($normalizedLms['chapters'] ?? []) as $chapter) {
            if ((string) ($chapter['title'] ?? '') === '') {
                $issues[] = 'module publié sans correspondance LMS';
            }
        }
        foreach ((array) ($compiledPayload['annexes'] ?? []) as $annex) {
            if ((string) ($annex['title'] ?? '') === '') {
                $issues[] = 'annexe non référencée dans le sommaire';
            }
        }

        return [
            'ok' => $issues === [],
            'issues' => $issues,
        ];
    }

    private function computeComplianceScore(array $publication, array $compiled, array $consistency): int
    {
        $score = 0;
        $score += (($compiled['formats']['pdf_official'] ?? '') !== '') ? 15 : 0;
        $score += (($publication['diffusion_classification'] ?? '') !== '') ? 15 : 0;
        $score += (($compiled['compiled_payload']['qr'] ?? []) !== []) ? 15 : 0;
        $score += (($compiled['compiled_payload']['watermark'] ?? []) !== []) ? 15 : 0;
        $score += (($publication['validation_chain_json'] ?? '') !== '') ? 15 : 0;
        $score += (($compiled['compiled_payload']['annexes'] ?? []) !== []) ? 10 : 0;
        $score += (($compiled['checksum'] ?? '') !== '') ? 10 : 0;
        $score += (($consistency['ok'] ?? false) ? 5 : 0);

        return min(100, $score);
    }

    private function defaultReusableBlocks(): array
    {
        return [
            ['component' => 'cadre_legal', 'version' => '1.0.0'],
            ['component' => 'securite', 'version' => '1.0.0'],
            ['component' => 'doctrine_unite', 'version' => '1.0.0'],
            ['component' => 'procedure_radio', 'version' => '1.0.0'],
            ['component' => 'regles_engagement', 'version' => '1.0.0'],
        ];
    }
}
