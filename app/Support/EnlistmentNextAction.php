<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Prochaine action d’un dossier de candidature : ce qu’il faut faire, qui doit le faire, à quel point c’est urgent
 * et où le faire sur la fiche. Calcul pur à partir de la ligne `enlistments` (aucune écriture, aucun schéma nouveau).
 *
 * Sert la file en colonnes (une colonne par action à mener) et la barre de pilotage de la fiche candidat,
 * pour que les deux écrans disent exactement la même chose.
 */
final class EnlistmentNextAction
{
    /** Colonnes de la file, dans l’ordre du parcours. */
    public const COLUMNS = [
        'take' => ['label' => 'À prendre', 'hint' => 'Aucun recruteur désigné'],
        'instruct' => ['label' => 'En instruction', 'hint' => 'Étudier, échanger, trancher'],
        'interview' => ['label' => 'Entretien', 'hint' => 'À tenir ou à conclure'],
        'on_hold' => ['label' => 'En attente', 'hint' => 'Décision reportée'],
        'integrate' => ['label' => 'Intégration', 'hint' => 'Acceptés à finaliser'],
        'retro' => ['label' => 'Bilan 30 jours', 'hint' => 'Retour d’équipe attendu'],
    ];

    /** Rang d’urgence (plus petit = plus pressant) pour trier les cartes. */
    private const URGENCY_RANK = ['late' => 0, 'watch' => 1, 'soon' => 2, 'ok' => 3, 'none' => 4];

    /**
     * @param array<string, mixed> $row ligne enlistments (éventuellement enrichie par EnlistmentStaffRowEnricher)
     * @param string|null $retroDoneAt date du bilan équipe s’il est déjà rédigé (sinon lu dans la ligne enrichie)
     * @return array{key: string, column: ?string, label: string, detail: string, owner: string, ownerLabel: string,
     *     urgency: string, urgencyLabel: string, anchor: string, cta: string, canAssign: bool}|null
     *     null pour un dossier clos (refusé, non admis, annulé).
     */
    public static function for(array $row, int $slaHours, int $viewerUserId = 0, ?int $now = null, ?string $retroDoneAt = null): ?array
    {
        $now ??= time();
        $slaHours = max(1, $slaHours);
        $stage = is_array($row['stage'] ?? null) && isset($row['stage']['key']) ? $row['stage'] : EnlistmentStatusCatalog::stage($row);
        $key = (string) $stage['key'];
        if ($key === 'closed' || $key === 'unknown') {
            return null;
        }

        $reviewer = (int) ($row['reviewed_by'] ?? 0);
        $reviewerLabel = trim((string) ($row['instructor_label'] ?? ''));
        $owner = $reviewer < 1 ? 'nobody' : ($viewerUserId > 0 && $reviewer === $viewerUserId ? 'you' : 'other');
        $ownerLabel = match ($owner) {
            'you' => 'Vous',
            'other' => $reviewerLabel !== '' ? $reviewerLabel : 'Un recruteur',
            default => 'Personne',
        };
        $delay = is_array($row['delay'] ?? null) && isset($row['delay']['key']) ? $row['delay'] : EnlistmentStatusCatalog::delay($row, $slaHours, $now);
        $fromDelay = static fn (): string => match ((string) $delay['key']) {
            'late' => 'late',
            'watch' => 'watch',
            default => 'ok',
        };

        $action = match ($key) {
            'reception' => [
                'column' => 'take',
                'label' => 'Prendre en charge',
                'detail' => 'Désignez un référent pour lancer l’instruction.',
                'urgency' => $fromDelay(),
                'anchor' => 'coordination-dossier',
                'cta' => 'Me désigner',
            ],
            'instruction' => [
                'column' => 'instruct',
                'label' => 'Instruire et décider',
                'detail' => 'Proposer un entretien, mettre en attente ou rendre la décision.',
                'urgency' => $fromDelay(),
                'anchor' => 'instruction-dossier',
                'cta' => 'Ouvrir la décision',
            ],
            'interview' => self::interviewAction($row, $slaHours, $now),
            'on_hold' => [
                'column' => 'on_hold',
                'label' => 'Relancer la décision',
                'detail' => 'Dossier suspendu : reprenez-le ou tranchez.',
                'urgency' => $fromDelay(),
                'anchor' => 'instruction-dossier',
                'cta' => 'Reprendre le dossier',
            ],
            'onboarding' => [
                'column' => 'integrate',
                'label' => 'Finaliser l’intégration',
                'detail' => 'Rôles, unité et profil Steam du nouveau membre.',
                'urgency' => 'watch',
                'anchor' => 'integration-membre',
                'cta' => 'Finaliser',
            ],
            'link_account' => [
                'column' => 'integrate',
                'label' => 'Rattacher le compte membre',
                'detail' => 'Accepté, mais aucun compte membre n’est lié au dossier.',
                'urgency' => 'watch',
                'anchor' => 'rattachement-membre',
                'cta' => 'Rattacher',
            ],
            default => self::retroAction($row, $retroDoneAt, $now),
        };

        $urgency = (string) $action['urgency'];

        return [
            'key' => (string) ($action['key'] ?? ($action['column'] === 'retro' ? 'retro' : $key)),
            'column' => $action['column'],
            'label' => (string) $action['label'],
            'detail' => (string) $action['detail'],
            'owner' => $owner,
            'ownerLabel' => $ownerLabel,
            'urgency' => $urgency,
            'urgencyLabel' => self::urgencyLabel($urgency, $delay, $action),
            'anchor' => (string) $action['anchor'],
            'cta' => (string) $action['cta'],
            'canAssign' => $key === 'reception' && $viewerUserId > 0,
        ];
    }

    /**
     * Range les dossiers dans les colonnes de la file, triés du plus pressant au moins pressant.
     * Les dossiers sans action (clos, intégrés sans bilan dû) ne figurent pas sur le tableau.
     *
     * @param list<array<string, mixed>> $rows lignes portant déjà `next_action` (voir annotate)
     * @return list<array{key: string, label: string, hint: string, cards: list<array<string, mixed>>, late: int}>
     */
    public static function columns(array $rows): array
    {
        $buckets = array_fill_keys(array_keys(self::COLUMNS), []);
        foreach ($rows as $row) {
            $col = $row['next_action']['column'] ?? null;
            if (is_string($col) && isset($buckets[$col])) {
                $buckets[$col][] = $row;
            }
        }
        $out = [];
        foreach (self::COLUMNS as $key => $meta) {
            $cards = $buckets[$key];
            usort($cards, static function (array $a, array $b): int {
                $ra = self::URGENCY_RANK[$a['next_action']['urgency'] ?? 'none'] ?? 9;
                $rb = self::URGENCY_RANK[$b['next_action']['urgency'] ?? 'none'] ?? 9;
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }

                // À urgence égale : le plus ancien d’abord.
                return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
            });
            $late = count(array_filter($cards, static fn (array $c): bool => ($c['next_action']['urgency'] ?? '') === 'late'));
            $out[] = ['key' => $key, 'label' => $meta['label'], 'hint' => $meta['hint'], 'cards' => $cards, 'late' => $late];
        }

        return $out;
    }

    /**
     * Ajoute `next_action` à chaque ligne.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function annotate(array $rows, int $slaHours, int $viewerUserId, ?int $now = null): array
    {
        foreach ($rows as $i => $row) {
            $rows[$i]['next_action'] = self::for($row, $slaHours, $viewerUserId, $now);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function interviewAction(array $row, int $slaHours, int $now): array
    {
        $raw = trim((string) ($row['discord_interview_at'] ?? ''));
        $at = $raw !== '' ? strtotime($raw) : false;
        if ($at === false || $at <= 0) {
            return [
                'column' => 'interview',
                'label' => 'Fixer la date de l’entretien',
                'detail' => 'Entretien proposé, sans date confirmée.',
                'urgency' => 'watch',
                'anchor' => 'discord',
                'cta' => 'Planifier',
            ];
        }
        if ($at > $now) {
            $hoursLeft = (int) floor(($at - $now) / 3600);

            return [
                'column' => 'interview',
                'label' => 'Tenir l’entretien',
                'detail' => 'Prévu le ' . date('d/m à H:i', $at) . '.',
                'urgency' => $hoursLeft < 24 ? 'soon' : 'ok',
                'anchor' => 'discord',
                'cta' => 'Préparer l’entretien',
                'when' => $hoursLeft,
            ];
        }
        $since = (int) floor(($now - $at) / 3600);

        return [
            'column' => 'interview',
            'label' => 'Conclure l’entretien',
            'detail' => 'Entretien passé le ' . date('d/m', $at) . ' : saisissez le compte rendu et décidez.',
            'urgency' => $since > $slaHours ? 'late' : 'watch',
            'anchor' => 'instruction-dossier',
            'cta' => 'Rendre la décision',
            'since' => $since,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function retroAction(array $row, ?string $retroDoneAt, int $now): array
    {
        $retro = is_array($row['retro'] ?? null) && isset($row['retro']['key'])
            ? $row['retro']
            : EnlistmentStatusCatalog::retro($row, $retroDoneAt ?? (isset($row['staff_retro_done_at']) ? (string) $row['staff_retro_done_at'] : null), true, $now);
        $rk = (string) $retro['key'];
        if ($rk === 'due') {
            return [
                'column' => 'retro',
                'label' => 'Rédiger le bilan 30 jours',
                'detail' => 'Comment se passe l’intégration ? ' . (string) $retro['meta'] . '.',
                'urgency' => (string) $retro['tone'] === 'rose' ? 'late' : 'watch',
                'anchor' => 'bilan-recrutement',
                'cta' => 'Rédiger le bilan',
            ];
        }
        if ($rk === 'done') {
            return [
                'key' => 'done',
                'column' => null,
                'label' => 'Parcours terminé',
                'detail' => 'Membre intégré et bilan 30 jours rédigé.',
                'urgency' => 'none',
                'anchor' => 'bilan-recrutement',
                'cta' => 'Voir le bilan',
            ];
        }

        return [
            'key' => 'integrated',
            'column' => null,
            'label' => 'Rien à faire pour l’instant',
            'detail' => 'Membre intégré. Bilan 30 jours ' . ($retro['meta'] !== '' ? mb_strtolower((string) $retro['meta']) : 'à venir') . '.',
            'urgency' => 'none',
            'anchor' => 'bilan-recrutement',
            'cta' => 'Voir le dossier',
        ];
    }

    /**
     * @param array<string, mixed> $delay
     * @param array<string, mixed> $action
     */
    private static function urgencyLabel(string $urgency, array $delay, array $action): string
    {
        if (($action['column'] ?? '') === 'interview') {
            if (isset($action['since'])) {
                return 'Passé depuis ' . EnlistmentStatusCatalog::formatHours((int) $action['since']);
            }
            if (isset($action['when'])) {
                return 'Dans ' . EnlistmentStatusCatalog::formatHours((int) $action['when']);
            }

            return 'Date à fixer';
        }
        if (in_array($action['column'] ?? '', ['take', 'instruct', 'on_hold'], true)) {
            return match ((string) ($delay['key'] ?? '')) {
                'late' => 'En retard',
                'watch' => 'À surveiller',
                'ok' => 'Dans les délais',
                default => '',
            };
        }

        return match ($urgency) {
            'late' => 'En retard',
            'watch' => 'À faire',
            default => '',
        };
    }
}
