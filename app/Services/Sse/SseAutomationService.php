<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;
use App\Services\Tactical\AtakActivityLogService;

/**
 * Automatismes d'exploitation SSE.
 *
 * Ce que fait le serveur tout seul quand une fiche arrive du terrain, et surtout
 * ce qu'il ne fait pas.
 *
 * Principe retenu : **un automatisme propose, il ne décide pas**. Il classe, il
 * signale, il pose une relation marquée comme automatique — mais il ne clôt jamais
 * un site, ne déclare jamais une identité, et ne supprime rien. Toute action
 * irréversible reste la main de l'opérateur, parce qu'une règle qui se trompe en
 * silence coûte plus cher que dix rappels à faire à la main.
 *
 * Chaque règle laisse une trace dans le journal d'activité sous le libellé de
 * la règle : on doit pouvoir répondre à « pourquoi cette fiche est-elle dans ce
 * dossier ? » sans lire le code.
 */
final class SseAutomationService
{
    /** Auteur porté par les relations posées par une règle — jamais un nom d'analyste. */
    public const RULE_AUTHOR = 'Automatisme';

    /** Score de croisement au-delà duquel on interrompt le poste de commandement. */
    public const HARD_MATCH_SCORE = 85;

    /** Fenêtre de co-présence, en minutes : deux fiches du même dossier saisies
     *  coup sur coup décrivent très probablement le même contrôle. */
    public const CO_PRESENCE_MINUTES = 45;

    /** Au-delà, on cesse de relier : un contrôle de vingt personnes ne produit pas
     *  190 arêtes utiles, il produit du bruit. */
    public const CO_PRESENCE_MAX_LINKS = 5;

    private Database $db;

    public function __construct(
        ?Database $db = null,
        private ?SseCaseRepository $cases = null,
        private ?SsePersonRepository $persons = null,
        private ?SseSiteRepository $sites = null,
        private ?SseCorrelationService $correlation = null,
        private ?AtakActivityLogService $activityLog = null,
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->cases ??= new SseCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->sites ??= new SseSiteRepository();
        $this->correlation ??= new SseCorrelationService();
        $this->activityLog ??= new AtakActivityLogService();
    }

    /**
     * Déclenché à l'enregistrement d'une fiche personne.
     *
     * @param array<string, mixed> $person   Fiche telle qu'enregistrée.
     * @param list<array<string, mixed>> $watchlistHits Croisement déjà calculé par l'appelant.
     * @param int|null $caseId Dossier de classement si le terrain en a désigné un.
     * @return list<array{rule: string, label: string, detail: string}> Ce qui a été fait, en clair.
     */
    public function onPersonRecorded(
        int $tenantId,
        int $mapId,
        array $person,
        array $watchlistHits = [],
        ?int $caseId = null,
        ?string $actor = null
    ): array {
        $applied = [];
        $personId = (int) ($person['id'] ?? 0);
        if ($personId < 1 || $tenantId < 1) {
            return $applied;
        }
        $name = trim((string) ($person['display_name'] ?? '')) ?: 'fiche sans nom';

        // A1 — Classement automatique quand le terrain n'a pas donné de code.
        if ($caseId === null || $caseId < 1) {
            $caseId = $this->autoFile($tenantId, $personId, $name, $mapId, $actor, $applied);
        }

        // A2 — Doublon probable : un relevé biométrique déjà versé sous la même
        // référence de laboratoire désigne le même individu.
        $this->flagDuplicate($tenantId, $mapId, $personId, $name, $caseId, $actor, $applied);

        // A3 — Correspondance forte avec une liste de surveillance.
        $this->escalateOnHardMatch($tenantId, $mapId, $person, $watchlistHits, $caseId, $actor, $applied);

        // A4 — Co-présence : fiches du même dossier saisies dans la foulée.
        if ($caseId !== null && $caseId > 0) {
            $this->linkCoPresence($tenantId, $mapId, $personId, $name, $caseId, $applied);
        }

        return $applied;
    }

    /**
     * Déclenché après mise à jour d'une pièce du site.
     * Ne clôt rien : signale seulement que la checklist est complète.
     *
     * @return list<array{rule: string, label: string, detail: string}>
     */
    public function onSiteProgress(int $tenantId, int $mapId, int $siteId, ?string $actor = null): array
    {
        $applied = [];
        $site = $this->sites->findById($siteId, $tenantId);
        if ($site === null || ($site['status'] ?? '') === 'cloture') {
            return $applied;
        }
        $rooms = $this->sites->listRooms($siteId, $tenantId);
        if ($rooms === []) {
            return $applied;
        }
        $checked = 0;
        foreach ($rooms as $r) {
            if (!empty($r['checked'])) {
                $checked++;
            }
        }
        if ($checked < count($rooms)) {
            return $applied;
        }

        $ref = (string) ($site['reference_code'] ?? '');
        $detail = sprintf(
            'Site %s : les %d pièces de la checklist sont fouillées. Le compte rendu de clôture peut être rédigé.',
            $ref,
            count($rooms)
        );
        $this->activityLog->record($tenantId, $mapId, 'SSE_AUTO', $detail, $actor ?? self::RULE_AUTHOR);
        $applied[] = ['rule' => 'A5', 'label' => 'Site prêt pour clôture', 'detail' => $detail];

        $caseId = (int) ($site['case_id'] ?? 0);
        if ($caseId > 0) {
            $this->note($caseId, $tenantId, $detail);
        }

        return $applied;
    }

    /**
     * Déclenché à chaque saisie versée : signale les natures qui appellent une
     * remontée immédiate, sans attendre la clôture du site.
     *
     * @param array<string, mixed> $seizure
     * @return list<array{rule: string, label: string, detail: string}>
     */
    public function onSeizureRecorded(
        int $tenantId,
        int $mapId,
        int $siteId,
        array $seizure,
        ?string $actor = null
    ): array {
        $applied = [];
        // Ces natures justifient d'interrompre le poste de commandement : elles
        // changent l'appréciation de la menace, pas seulement l'inventaire.
        $urgent = ['arme', 'munition', 'numerique', 'document'];
        $category = (string) ($seizure['category'] ?? '');
        if (!in_array($category, $urgent, true)) {
            return $applied;
        }

        $site = $this->sites->findById($siteId, $tenantId);
        $detail = sprintf(
            'Saisie à remonter : %s (%s) sur %s.',
            (string) ($seizure['label'] ?? 'objet'),
            (string) ($seizure['category_label'] ?? $category),
            (string) ($site['reference_code'] ?? 'site en cours')
        );
        $this->activityLog->record($tenantId, $mapId, 'SSE_AUTO', $detail, $actor ?? self::RULE_AUTHOR);
        $applied[] = ['rule' => 'A6', 'label' => 'Saisie sensible', 'detail' => $detail];

        $caseId = (int) ($site['case_id'] ?? 0);
        if ($caseId > 0) {
            $this->note($caseId, $tenantId, $detail);
        }

        return $applied;
    }

    // ------------------------------------------------------------------
    // Règles
    // ------------------------------------------------------------------

    /**
     * A1 — Classement automatique.
     *
     * Rattache la fiche seulement s'il n'existe **qu'un seul** dossier ouvert :
     * dès qu'il y a un choix à faire, se tromper de dossier est pire que ne rien
     * classer, parce que personne ne va relire un rattachement qui a l'air correct.
     *
     * @param list<array{rule: string, label: string, detail: string}> $applied
     */
    private function autoFile(
        int $tenantId,
        int $personId,
        string $name,
        int $mapId,
        ?string $actor,
        array &$applied
    ): ?int {
        $open = [];
        foreach ($this->cases->listForTenant($tenantId, null, []) as $c) {
            if (in_array((string) ($c['status'] ?? ''), ['ouvert', 'en_cours'], true)) {
                $open[] = $c;
            }
        }
        if (count($open) !== 1) {
            return null;
        }

        $case = $open[0];
        $caseId = (int) ($case['id'] ?? 0);
        if ($caseId < 1) {
            return null;
        }

        $this->cases->linkPerson($caseId, $personId, $tenantId, null, 'Classement automatique — dossier ouvert unique');
        $detail = sprintf(
            '%s classée au dossier %s : c\'était le seul dossier ouvert.',
            $name,
            (string) ($case['reference_code'] ?? '')
        );
        $this->activityLog->record($tenantId, $mapId, 'SSE_AUTO', $detail, $actor ?? self::RULE_AUTHOR);
        $applied[] = ['rule' => 'A1', 'label' => 'Classement automatique', 'detail' => $detail];

        return $caseId;
    }

    /**
     * A2 — Doublon probable.
     *
     * Les relevés simulés dérivent d'une graine stable par individu : deux fiches
     * portant la même référence de laboratoire décrivent la même personne, contrôlée
     * deux fois. On le signale, on ne fusionne pas — une fusion automatique
     * détruirait la fiche la moins complète sans que personne l'ait décidé.
     *
     * @param list<array{rule: string, label: string, detail: string}> $applied
     */
    private function flagDuplicate(
        int $tenantId,
        int $mapId,
        int $personId,
        string $name,
        ?int $caseId,
        ?string $actor,
        array &$applied
    ): void {
        $samples = $this->persons->listBiometricSamples($personId, $tenantId);
        $refs = [];
        foreach ($samples as $s) {
            $ref = trim((string) ($s['lab_reference'] ?? ''));
            if ($ref !== '') {
                $refs[] = $ref;
            }
        }
        if ($refs === []) {
            return;
        }

        try {
            $placeholders = [];
            $params = ['t' => $tenantId, 'p' => $personId];
            foreach ($refs as $i => $ref) {
                $placeholders[] = ':r' . $i;
                $params['r' . $i] = $ref;
            }
            $rows = $this->db->fetchAll(
                'SELECT DISTINCT person_id, lab_reference FROM sse_biometric_samples
                 WHERE tenant_id = :t AND person_id <> :p
                   AND lab_reference IN (' . implode(',', $placeholders) . ')
                 LIMIT 5',
                $params
            );
        } catch (\Throwable) {
            return;
        }

        foreach ($rows as $row) {
            $otherId = (int) ($row['person_id'] ?? 0);
            if ($otherId < 1) {
                continue;
            }
            $other = $this->persons->findById($otherId, $tenantId);
            $otherName = trim((string) ($other['display_name'] ?? '')) ?: 'fiche antérieure';

            $detail = sprintf(
                'Doublon probable : %s et %s partagent le relevé %s. Aucune fusion effectuée.',
                $name,
                $otherName,
                (string) ($row['lab_reference'] ?? '')
            );
            $this->activityLog->record($tenantId, $mapId, 'SSE_AUTO', $detail, $actor ?? self::RULE_AUTHOR);
            $applied[] = ['rule' => 'A2', 'label' => 'Doublon probable', 'detail' => $detail];

            if ($caseId !== null && $caseId > 0) {
                $this->correlation->addRelation($tenantId, $caseId, [
                    'from_type' => 'person',
                    'from_id' => $personId,
                    'to_type' => 'person',
                    'to_id' => $otherId,
                    'relation' => 'meme_individu',
                    'reliability' => 'corroborated',
                    'note' => 'Relevé biométrique identique',
                    'author_label' => self::RULE_AUTHOR,
                ]);
                $this->note($caseId, $tenantId, $detail);
            }
        }
    }

    /**
     * A3 — Correspondance forte avec une liste de surveillance.
     *
     * Fait passer le dossier en exploitation et y dépose une note. Le verdict reste
     * un score, pas une identification : le libellé le dit explicitement, parce que
     * c'est cette note-là qui sera relue dans le débriefing.
     *
     * @param array<string, mixed> $person
     * @param list<array<string, mixed>> $hits
     * @param list<array{rule: string, label: string, detail: string}> $applied
     */
    private function escalateOnHardMatch(
        int $tenantId,
        int $mapId,
        array $person,
        array $hits,
        ?int $caseId,
        ?string $actor,
        array &$applied
    ): void {
        if ($hits === []) {
            return;
        }
        $top = $hits[0];
        $score = (int) ($top['score'] ?? 0);
        if ($score < self::HARD_MATCH_SCORE) {
            return;
        }

        $entry = is_array($top['entry'] ?? null) ? $top['entry'] : [];
        $watched = trim(sprintf(
            '%s %s',
            (string) ($entry['first_name'] ?? ''),
            (string) ($entry['last_name'] ?? '')
        ));
        if ($watched === '') {
            $watched = (string) ($entry['alias'] ?? 'entrée surveillée');
        }

        $detail = sprintf(
            'Correspondance forte (%d %%) entre %s et %s. Score de similarité — identification non établie.',
            $score,
            trim((string) ($person['display_name'] ?? '')) ?: 'fiche sans nom',
            $watched
        );
        $this->activityLog->record($tenantId, $mapId, 'SSE_AUTO', $detail, $actor ?? self::RULE_AUTHOR);
        $applied[] = ['rule' => 'A3', 'label' => 'Correspondance forte', 'detail' => $detail];

        if ($caseId === null || $caseId < 1) {
            return;
        }
        $this->note($caseId, $tenantId, $detail);

        $case = $this->cases->findById($caseId, $tenantId);
        if ($case !== null && (string) ($case['status'] ?? '') === 'ouvert') {
            $this->cases->update($caseId, $tenantId, ['status' => 'en_cours']);
            $applied[] = [
                'rule' => 'A3',
                'label' => 'Dossier passé en exploitation',
                'detail' => sprintf('Dossier %s passé en exploitation.', (string) ($case['reference_code'] ?? '')),
            ];
        }
    }

    /**
     * A4 — Co-présence.
     *
     * Deux fiches du même dossier saisies à quelques minutes d'écart décrivent
     * presque toujours le même contrôle. La relation est posée en « non vérifié » :
     * c'est une proximité d'horodatage, pas un lien constaté.
     *
     * @param list<array{rule: string, label: string, detail: string}> $applied
     */
    private function linkCoPresence(
        int $tenantId,
        int $mapId,
        int $personId,
        string $name,
        int $caseId,
        array &$applied
    ): void {
        // Fenêtre et plafond sont des constantes de classe, jamais des entrées
        // utilisateur : MySQL n'accepte pas de paramètre lié dans INTERVAL ni
        // dans LIMIT lorsque l'émulation des requêtes préparées est désactivée.
        $sql = sprintf(
            'SELECT p.id, p.created_at
               FROM sse_case_persons cp
               JOIN sse_persons p ON p.id = cp.person_id
              WHERE cp.case_id = :c AND cp.tenant_id = :t AND p.id <> :p
                AND p.created_at >= (NOW() - INTERVAL %d MINUTE)
              ORDER BY p.created_at DESC
              LIMIT %d',
            self::CO_PRESENCE_MINUTES,
            self::CO_PRESENCE_MAX_LINKS
        );

        try {
            $rows = $this->db->fetchAll($sql, [
                'c' => $caseId,
                't' => $tenantId,
                'p' => $personId,
            ]);
        } catch (\Throwable) {
            return;
        }

        $linked = 0;
        foreach ($rows as $row) {
            $otherId = (int) ($row['id'] ?? 0);
            if ($otherId < 1) {
                continue;
            }
            $ok = $this->correlation->addRelation($tenantId, $caseId, [
                'from_type' => 'person',
                'from_id' => $personId,
                'to_type' => 'person',
                'to_id' => $otherId,
                'relation' => 'co_presence',
                'reliability' => 'unverified',
                'note' => sprintf('Contrôlés à moins de %d minutes d\'écart', self::CO_PRESENCE_MINUTES),
                'author_label' => self::RULE_AUTHOR,
            ]);
            if ($ok) {
                $linked++;
            }
        }

        if ($linked > 0) {
            $detail = sprintf(
                '%s reliée à %d fiche%s du même contrôle (proximité d\'horodatage, à vérifier).',
                $name,
                $linked,
                $linked > 1 ? 's' : ''
            );
            $this->activityLog->record($tenantId, $mapId, 'SSE_AUTO', $detail, self::RULE_AUTHOR);
            $applied[] = ['rule' => 'A4', 'label' => 'Co-présence', 'detail' => $detail];
        }
    }

    /** Note de dossier signée par la règle, jamais par un opérateur. */
    private function note(int $caseId, int $tenantId, string $body): void
    {
        try {
            $this->cases->addNote(
                $caseId,
                $tenantId,
                $body,
                SseCaseRepository::CLASS_COMMAND,
                null,
                self::RULE_AUTHOR
            );
        } catch (\Throwable) {
            // Une note perdue ne doit pas faire échouer une transmission terrain.
        }
    }
}
