<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Availability;

use App\Repositories\MemberAvailabilityCheckRepository;
use App\Services\Effectifs\Dossier\DossierCatalog;
use App\Services\Effectifs\Dossier\DossierPorts;
use App\Services\Effectifs\Dossier\DossierStore;
use App\Services\Effectifs\Dossier\MemberDossierStepActions;

/**
 * Validation d'une vérification de disponibilité par un responsable.
 *
 * Enregistre la vérification (qui, quand, conclusion proposée et retenue, note, suite, données relevées), la porte dans
 * l'historique du « Suivi du dossier » et applique la suite choisie avec les gestes EXISTANTS du suivi du dossier :
 *  - relance à J+X : étape personnalisée « Revérifier la disponibilité » (MemberDossierStepActions::addCustom) ;
 *  - suivi RH : étape personnalisée « Suivi RH : disponibilité à examiner », échéance à 7 jours ;
 *  - changement de position : rien n'est écrit ici, le responsable est conduit au formulaire « Position et statut ».
 * Les données relevées viennent toujours d'une exécution côté serveur au moment de l'enregistrement, jamais du navigateur.
 */
final class AvailabilityCheckRecorder
{
    public const FOLLOW_NONE = 'none';
    public const FOLLOW_RECHECK = 'recheck';
    public const FOLLOW_RH = 'rh';
    public const FOLLOW_POSITION = 'position';

    /** @var list<int> délais de relance proposés, en jours */
    public const RECHECK_DAYS = [7, 14, 30];

    public const RH_DUE_DAYS = 7;

    public const STEP_KEY = 'disponibilite';

    public const NOTE_MIN = 3;

    public function __construct(
        private MemberAvailabilityCheckRepository $checks,
        private ?DossierStore $store = null,
        private ?MemberDossierStepActions $actions = null,
        private ?DossierPorts $ports = null,
    ) {
    }

    /** @return array<string, string> */
    public static function followUpLabels(): array
    {
        return [
            self::FOLLOW_NONE => 'Aucune suite',
            self::FOLLOW_RECHECK => 'Programmer une relance',
            self::FOLLOW_RH => 'Ouvrir un suivi RH',
            self::FOLLOW_POSITION => 'Proposer un changement de position',
        ];
    }

    public static function degradedMessage(): string
    {
        return 'Vérification non enregistrée : l’étape de base de données « member_availability_checks » n’est pas encore appliquée (Administration, Migrations). Les résultats restent affichés.';
    }

    /**
     * Contrôle la saisie du responsable, sans rien écrire.
     *
     * @return array{ok: bool, code: string, message: string, conclusion?: string, note?: string, follow_up?: string, recheck_days?: int}
     */
    public static function validate(int $actorId, int $memberId, string $proposed, string $conclusion, string $note, string $followUp, int $recheckDays): array
    {
        $note = trim((string) preg_replace('/\s+/u', ' ', $note));
        if ($actorId > 0 && $actorId === $memberId) {
            return ['ok' => false, 'code' => 'self', 'message' => 'Vous ne vérifiez pas votre propre disponibilité.'];
        }
        if (!AvailabilityCheck::isConclusion($conclusion)) {
            return ['ok' => false, 'code' => 'conclusion', 'message' => 'Choisissez une conclusion.'];
        }
        if ($conclusion !== $proposed && mb_strlen($note) < self::NOTE_MIN) {
            return ['ok' => false, 'code' => 'note', 'message' => 'Expliquez la correction dans la note (3 caractères au moins).'];
        }
        if (!isset(self::followUpLabels()[$followUp])) {
            return ['ok' => false, 'code' => 'follow_up', 'message' => 'Choisissez une suite.'];
        }
        if ($followUp === self::FOLLOW_RECHECK && !in_array($recheckDays, self::RECHECK_DAYS, true)) {
            return ['ok' => false, 'code' => 'recheck_days', 'message' => 'Choisissez le délai de la relance.'];
        }

        return ['ok' => true, 'code' => 'ok', 'message' => '', 'conclusion' => $conclusion, 'note' => mb_substr($note, 0, 2000), 'follow_up' => $followUp, 'recheck_days' => $recheckDays];
    }

    /**
     * Enregistre une vérification déjà exécutée côté serveur.
     *
     * @param array{steps: list<array<string, mixed>>, verdict: array{key: string, label: string, detail: string}, rules?: array<string, int>} $run
     * @return array{ok: bool, code: string, message: string, status: int, id?: int, follow_up_message?: string, follow_up_ok?: bool}
     */
    public function record(int $tenantId, int $actorId, int $memberId, array $run, string $conclusion, string $note, string $followUp, int $recheckDays, ?string $batchId = null, ?string $today = null): array
    {
        $proposed = (string) ($run['verdict']['key'] ?? '');
        $v = self::validate($actorId, $memberId, $proposed, $conclusion, $note, $followUp, $recheckDays);
        if (!$v['ok']) {
            return ['ok' => false, 'code' => $v['code'], 'message' => $v['message'], 'status' => 422];
        }
        if (!$this->checks->schemaReady()) {
            return ['ok' => false, 'code' => 'schema', 'message' => self::degradedMessage(), 'status' => 503];
        }
        $today ??= date('Y-m-d');
        $note = (string) $v['note'];
        $due = match ($followUp) {
            self::FOLLOW_RECHECK => date('Y-m-d', (int) strtotime($today . ' +' . $recheckDays . ' days')),
            self::FOLLOW_RH => date('Y-m-d', (int) strtotime($today . ' +' . self::RH_DUE_DAYS . ' days')),
            default => null,
        };
        $id = $this->checks->insert($tenantId, $memberId, $actorId, [
            'proposed' => $proposed,
            'conclusion' => $conclusion,
            'corrected' => $conclusion !== $proposed,
            'note' => $note,
            'follow_up' => $followUp,
            'follow_up_due' => $due,
            'follow_up_ref' => null,
            'facts' => [
                'steps' => $run['steps'] ?? [],
                'verdict' => $run['verdict'] ?? [],
                'rules' => $run['rules'] ?? [],
            ],
            'batch_id' => $batchId,
        ]);

        // Historique du suivi du dossier (lisible dans l'onglet « Suivi du dossier »).
        if ($this->store !== null) {
            try {
                $this->store->addEvent($tenantId, $memberId, self::STEP_KEY, 'Vérification de disponibilité', DossierCatalog::ACTION_CHECK, $actorId, ['conclusion' => $proposed], ['conclusion' => $conclusion, 'follow_up' => $followUp], $note);
            } catch (\Throwable $e) {
                error_log('[vérification de disponibilité] historique du dossier : ' . $e->getMessage());
            }
        }
        $this->ports?->audit($tenantId, $actorId, $memberId, self::STEP_KEY, DossierCatalog::ACTION_CHECK, (string) json_encode(['check_id' => $id, 'proposed' => $proposed, 'conclusion' => $conclusion, 'follow_up' => $followUp], JSON_UNESCAPED_UNICODE));

        $out = ['ok' => true, 'code' => 'saved', 'message' => 'Vérification enregistrée.', 'status' => 200, 'id' => $id];
        if ($followUp === self::FOLLOW_RECHECK || $followUp === self::FOLLOW_RH) {
            $title = $followUp === self::FOLLOW_RECHECK ? 'Revérifier la disponibilité' : 'Suivi RH : disponibilité à examiner';
            $r = $this->actions !== null
                ? $this->actions->addCustom($tenantId, $actorId, $memberId, DossierCatalog::TRACK_CAREER, $title, (string) $due, $actorId, $today)
                : ['ok' => false, 'message' => 'Le suivi du dossier n’est pas disponible.'];
            $out['follow_up_ok'] = (bool) $r['ok'];
            if ($r['ok'] && isset($r['id'])) {
                $this->checks->setFollowUpRef($tenantId, $id, 'custom:' . (int) $r['id']);
                $out['follow_up_message'] = str_replace('%1', date('d/m/Y', (int) strtotime((string) $due)), $followUp === self::FOLLOW_RECHECK ? 'Relance ajoutée au suivi du dossier pour le %1.' : 'Suivi RH ouvert dans le suivi du dossier, échéance le %1.');
            } else {
                $out['follow_up_message'] = (string) ($r['message'] ?? 'La suite n’a pas pu être ajoutée au suivi du dossier.');
            }
        } elseif ($followUp === self::FOLLOW_POSITION) {
            $out['follow_up_ok'] = true;
            $out['follow_up_message'] = 'Changez la position depuis l’onglet Position et statut de la fiche.';
        }

        return $out;
    }
}
