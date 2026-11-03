<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\QualificationAwardRuleRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Services\Personnel\AcademyQualificationImporter;
use App\Services\Personnel\QualificationAwardRuleService;
use App\Services\Personnel\QualificationBadgeStorageService;
use App\Services\Personnel\QualificationCertificatePdfService;
use Throwable;

/**
 * Fiche d'une qualification (titulaires, en cours avec progression, historique), passage automatique à la demande,
 * délivrance / mise en formation / retrait d'un membre (matrice des effectifs, fiche) et validation des propositions.
 */
final class QualificationRulesController
{
    private const BASE = 'back-office/referentiels/qualifications';

    public function __construct(
        private QualificationAwardRuleService $service,
        private QualificationDefinitionRepository $definitions,
        private QualificationAwardRuleRepository $rules,
        private QualificationBadgeStorageService $badges,
        private QualificationCertificatePdfService $certificates,
    ) {
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $definition = $this->definitions->find($tenantId, $id);
        if ($definition === null) {
            Session::flash('error', 'Qualification introuvable.');

            return Response::redirect(url(self::BASE));
        }
        $board = $this->service->board($tenantId, $id);
        $issuerRepo = new \App\Repositories\QualificationIssuerRepository();
        $defaultIssuer = (int) ($definition['default_issuer_id'] ?? 0) > 0 ? $issuerRepo->find($tenantId, (int) $definition['default_issuer_id']) : null;

        return Response::view('layout.main', [
            'content' => 'admin.organization.qualifications.show',
            'title' => (string) ($definition['name'] ?? 'Qualification'),
            'backOfficePageCss' => ['qualifications-matrix.css'],
            'boSkipPageHead' => true,
            'definition' => $definition,
            'board' => $board,
            'names' => $this->service->names($tenantId),
            'history' => $this->rules->historyForDefinition($tenantId, $id),
            'badgeUrl' => $this->badges->publicUrl($definition['badge_media_path'] ?? null),
            'schemaReady' => $this->service->schemaReady(),
            'defaultIssuer' => $defaultIssuer,
            'issuerLinkReady' => $issuerRepo->linkReady(),
            'attestations' => $this->service->attestationCriteria($tenantId, $id),
            'attestReady' => $this->rules->attestationsReady(),
        ]);
    }

    public function evaluate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->post($request, url(self::BASE . '/' . $id), function (int $tenantId) use ($id): void {
            $r = $this->service->runAuto($tenantId, $id);
            Session::flash('success', sprintf(
                'Évaluation terminée : %d délivrée(s), %d proposée(s), %d renouvelée(s), %d attestation(s) Academy rattachée(s).',
                $r['awarded'],
                $r['proposed'],
                $r['renewed'],
                $r['linked']
            ));
        });
    }

    /** Case de la matrice ou de la fiche : délivrer, mettre en formation, retirer. */
    public function cell(Request $request, array $params = []): Response
    {
        $definitionId = (int) $request->input('definition_id', 0);
        $userId = (int) $request->input('user_id', 0);
        $back = $this->backUrl((string) $request->input('return_to', 'matrix'), $definitionId, $userId);

        return $this->post($request, $back, function (int $tenantId) use ($request, $definitionId, $userId): void {
            $target = (string) $request->input('target', 'ok');
            if (!in_array($target, ['ok', 'train', 'none'], true)) {
                $target = 'ok';
            }
            $actorId = (int) Session::get('user_id');
            $message = $this->service->setCell(
                $tenantId,
                $definitionId,
                $userId,
                $target,
                (string) $request->input('obtained_at', ''),
                (string) $request->input('notes', ''),
                (string) $request->input('reason', ''),
                $actorId,
                (string) $request->input('force', '') === '1'
            );
            if ($target === 'ok' && (string) $request->input('certificate', '') === '1') {
                $awardId = $this->latestObtainedAward($tenantId, $definitionId, $userId);
                if ($awardId > 0) {
                    try {
                        $res = $this->certificates->generate($tenantId, $awardId, $actorId);
                        $message .= ' Brevet n° ' . (string) ($res['certificate_number'] ?? '') . ' généré.';
                    } catch (Throwable $e) {
                        $message .= ' Brevet non généré : ' . $e->getMessage();
                    }
                }
            }
            Session::flash('success', $message);
        });
    }

    public function decide(Request $request, array $params = []): Response
    {
        $awardId = (int) ($params['awardId'] ?? 0);
        $definitionId = (int) $request->input('definition_id', 0);

        // Décision prise depuis la file de l'espace instructeur Academy : on y revient.
        $back = (string) $request->input('return_to', '') === 'academy' ? url('academy/instructor') . '#file' : url(self::BASE . '/' . $definitionId) . '#a-valider';

        return $this->post($request, $back, function (int $tenantId) use ($request, $awardId): void {
            $approve = (string) $request->input('decision', '') === 'approve';
            $this->service->decide($tenantId, $awardId, $approve, (int) Session::get('user_id'));
            Session::flash('success', $approve ? 'Qualification validée.' : 'Proposition refusée.');
        });
    }

    public function restore(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);

        return $this->post($request, url(self::BASE . '/' . $id), function (int $tenantId) use ($id): void {
            Database::getPdo()->prepare(
                'UPDATE personnel_qualification_definitions SET archived_at = NULL, is_active = 1, updated_by = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
            )->execute([(int) Session::get('user_id') ?: null, $tenantId, $id]);
            Session::flash('success', 'Qualification réactivée : elle peut de nouveau être délivrée.');
        });
    }

    /** Rattache les attestations Academy restées sans qualification (une qualification par formation). */
    public function importAcademy(Request $request, array $params = []): Response
    {
        return $this->post($request, effectifs_workspace_url('qualifications'), function (int $tenantId): void {
            $r = (new AcademyQualificationImporter(Database::getPdo()))->run($tenantId);
            Session::flash('success', sprintf('%d qualification(s) Academy créée(s), %d attestation(s) rattachée(s).', $r['created'], $r['linked']));
        });
    }

    private function latestObtainedAward(int $tenantId, int $definitionId, int $userId): int
    {
        try {
            $st = Database::getPdo()->prepare(
                "SELECT MAX(id) FROM personnel_qualifications WHERE tenant_id = ? AND definition_id = ? AND user_id = ? AND COALESCE(admin_status, 'obtained') = 'obtained'"
            );
            $st->execute([$tenantId, $definitionId, $userId]);

            return (int) $st->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function backUrl(string $to, int $definitionId, int $userId): string
    {
        return match ($to) {
            'fiche' => url(self::BASE . '/' . $definitionId),
            'member' => url('back-office/ressources/effectifs/membres/' . $userId) . '#qualifications',
            default => effectifs_workspace_url('qualifications'),
        };
    }

    /** @param callable(int): void $fn */
    private function post(Request $request, string $back, callable $fn): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        try {
            $fn($tenantId);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect($back);
    }
}
