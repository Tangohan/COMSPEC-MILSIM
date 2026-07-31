<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;

/**
 * Corrélation d'un dossier SSE.
 *
 * Deux natures d'arêtes, volontairement traitées différemment :
 *
 *   - **déduites** — « saisie recueillie sur P02 », « objet trouvé en pièce 03 »,
 *     « P01 rattaché au site A ». Elles existent déjà dans les données ; les
 *     stocker créerait un doublon qui se périme dès qu'une saisie est corrigée.
 *     Elles sont donc recalculées à chaque lecture.
 *   - **posées par l'analyste** — « P03 connaît P07 ». Aucune donnée ne les porte,
 *     elles sont enregistrées dans `sse_relations`.
 *
 * Chaque arête porte sa source et son niveau de fiabilité : une déduction machine
 * et une hypothèse d'analyste ne se valent pas, et le portail doit le montrer.
 */
final class SseCorrelationService
{
    /** @var array<string, string> */
    public const RELATION_LABELS = [
        'present'    => 'présent sur',
        'recovered'  => 'recueilli sur',
        'found_at'   => 'trouvé en',
        'associe'    => 'associé à',
        'possede'    => 'possède',
        'contact'    => 'en contact avec',
        'membre'     => 'membre de',
        'mentionne'  => 'mentionné par',
        // Posées par les automatismes (voir SseAutomationService) mais tout aussi
        // valables sous la main d'un analyste.
        'co_presence'   => 'contrôlé en même temps que',
        'meme_individu' => 'même individu que',
    ];

    /** Provenance d'une arête — trois natures, jamais confondues à l'écran. */
    public const SOURCE_DERIVED = 'auto';
    public const SOURCE_RULE = 'regle';
    public const SOURCE_ANALYST = 'analyste';

    /** @var array<string, string> */
    public const SOURCE_LABELS = [
        self::SOURCE_DERIVED => 'Déduit',
        self::SOURCE_RULE => 'Automatisme',
        self::SOURCE_ANALYST => 'Analyste',
    ];

    /** @var array<string, string> */
    public const RELIABILITY_LABELS = [
        'unverified'   => 'Non vérifié',
        'corroborated' => 'Corroboré',
        'confirmed'    => 'Confirmé',
        'conflicting'  => 'Contradictoire',
    ];

    private Database $db;

    public function __construct(
        ?Database $db = null,
        private ?SseCaseRepository $cases = null,
        private ?SsePersonRepository $persons = null,
        private ?SseSiteRepository $sites = null,
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->cases ??= new SseCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->sites ??= new SseSiteRepository();
    }

    public static function relationLabel(string $key): string
    {
        return self::RELATION_LABELS[$key] ?? 'lié à';
    }

    public static function reliabilityLabel(string $key): string
    {
        return self::RELIABILITY_LABELS[$key] ?? 'Non vérifié';
    }

    /**
     * Graphe du dossier : nœuds désignés en clair, arêtes déduites puis posées.
     *
     * @return array{nodes: array<string, array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function graphForCase(int $caseId, int $tenantId): array
    {
        $nodes = [];
        $edges = [];

        $people = [];
        foreach ($this->cases->listLinkedPersonIds($caseId, $tenantId) as $link) {
            $person = $this->persons->findById((int) ($link['person_id'] ?? 0), $tenantId);
            if ($person === null) {
                continue;
            }
            $people[(int) $person['id']] = $person;
        }

        // Numérotation P01, P02… stable dans l'ordre du dossier : c'est la
        // désignation utilisée dans les comptes rendus.
        $refByPerson = [];
        $i = 0;
        foreach ($people as $pid => $person) {
            $i++;
            $ref = sprintf('P%02d', $i);
            $refByPerson[$pid] = $ref;
            $nodes['person:' . $pid] = [
                'type' => 'person',
                'id' => $pid,
                'ref' => $ref,
                'label' => trim((string) ($person['display_name'] ?? '')) ?: 'identité non établie',
                'detail' => (string) ($person['status_label'] ?? ''),
                'url' => url('atak/sse/personnes'),
            ];
        }

        $sites = $this->sites->listForCase($caseId, $tenantId);
        $roomLabels = [];
        foreach ($sites as $site) {
            $sid = (int) ($site['id'] ?? 0);
            $nodes['site:' . $sid] = [
                'type' => 'site',
                'id' => $sid,
                'ref' => (string) ($site['reference_code'] ?? ''),
                'label' => (string) ($site['name'] ?? ''),
                'detail' => (string) ($site['site_type_label'] ?? ''),
                'url' => url('atak/sse/sites/' . $sid),
            ];
            foreach ($this->sites->listRooms($sid, $tenantId) as $room) {
                $roomLabels[(int) ($room['id'] ?? 0)] = [
                    'label' => (string) ($room['label'] ?? ''),
                    'site' => $sid,
                ];
            }
        }

        // --- Arêtes déduites ---
        $seizureIndex = 0;
        foreach ($sites as $site) {
            $sid = (int) ($site['id'] ?? 0);
            foreach ($this->sites->listSeizures($sid, $tenantId) as $seizure) {
                $seizureIndex++;
                $eid = (int) ($seizure['id'] ?? 0);
                $ref = sprintf('E%02d', $seizureIndex);
                $nodes['seizure:' . $eid] = [
                    'type' => 'seizure',
                    'id' => $eid,
                    'ref' => $ref,
                    'label' => (string) ($seizure['label'] ?? ''),
                    'detail' => (string) ($seizure['category_label'] ?? ''),
                    'url' => url('atak/sse/sites/' . $sid),
                ];

                $edges[] = $this->edge('seizure', $eid, 'site', $sid, 'found_at', self::SOURCE_DERIVED, 'confirmed', '');

                $roomId = (int) ($seizure['room_id'] ?? 0);
                if ($roomId > 0 && isset($roomLabels[$roomId])) {
                    $edges[] = $this->edge(
                        'seizure',
                        $eid,
                        'room',
                        $roomId,
                        'found_at',
                        self::SOURCE_DERIVED,
                        'confirmed',
                        $roomLabels[$roomId]['label']
                    );
                }

                $personId = (int) ($seizure['person_id'] ?? 0);
                if ($personId > 0 && isset($people[$personId])) {
                    $edges[] = $this->edge('seizure', $eid, 'person', $personId, 'recovered', self::SOURCE_DERIVED, 'confirmed', '');
                }
            }
        }

        // Personne rattachée au dossier et site du même dossier : présence probable,
        // pas certaine — une personne peut avoir été contrôlée hors du site.
        if (count($sites) === 1) {
            $sid = (int) ($sites[0]['id'] ?? 0);
            foreach (array_keys($people) as $pid) {
                $edges[] = $this->edge('person', $pid, 'site', $sid, 'present', self::SOURCE_DERIVED, 'corroborated', '');
            }
        }

        // --- Arêtes posées : analyste ou automatisme ---
        // La colonne `author_label` sert de discriminant. Une règle qui rapproche
        // deux fiches et un analyste qui affirme un lien n'ont pas le même poids ;
        // les afficher pareil reviendrait à faire dire à la machine ce qu'elle n'a
        // pas conclu.
        foreach ($this->listStored($caseId, $tenantId) as $row) {
            $author = (string) ($row['author_label'] ?? '');
            $source = $author === SseAutomationService::RULE_AUTHOR
                ? self::SOURCE_RULE
                : self::SOURCE_ANALYST;

            $edges[] = $this->edge(
                (string) $row['from_type'],
                (int) $row['from_id'],
                (string) $row['to_type'],
                (int) $row['to_id'],
                (string) $row['relation'],
                $source,
                (string) $row['reliability'],
                (string) ($row['note'] ?? '')
            );
        }

        // Les nœuds cités par une arête posée mais absents du dossier restent
        // affichables : on ne masque pas une piste au motif qu'elle sort du périmètre.
        foreach ($edges as $e) {
            foreach ([['from_type', 'from_id'], ['to_type', 'to_id']] as [$tk, $ik]) {
                $key = $e[$tk] . ':' . $e[$ik];
                if (!isset($nodes[$key]) && $e[$tk] === 'room' && isset($roomLabels[$e[$ik]])) {
                    $nodes[$key] = [
                        'type' => 'room',
                        'id' => $e[$ik],
                        'ref' => '',
                        'label' => $roomLabels[$e[$ik]]['label'],
                        'detail' => 'Pièce',
                        'url' => url('atak/sse/sites/' . $roomLabels[$e[$ik]]['site']),
                    ];
                }
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges, 'refs' => $refByPerson];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listStored(int $caseId, int $tenantId): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT * FROM sse_relations WHERE tenant_id = :t AND case_id = :c ORDER BY id ASC',
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addRelation(int $tenantId, int $caseId, array $data): bool
    {
        $relation = (string) ($data['relation'] ?? 'associe');
        if (!isset(self::RELATION_LABELS[$relation])) {
            $relation = 'associe';
        }
        $reliability = (string) ($data['reliability'] ?? 'unverified');
        if (!isset(self::RELIABILITY_LABELS[$reliability])) {
            $reliability = 'unverified';
        }

        $fromId = (int) ($data['from_id'] ?? 0);
        $toId = (int) ($data['to_id'] ?? 0);
        if ($fromId < 1 || $toId < 1) {
            return false;
        }
        // Une entité liée à elle-même n'apporte rien et pollue le graphe.
        if ($fromId === $toId && ($data['from_type'] ?? '') === ($data['to_type'] ?? '')) {
            return false;
        }

        try {
            $this->db->execute(
                'INSERT INTO sse_relations
                    (tenant_id, case_id, from_type, from_id, to_type, to_id, relation, reliability, note, author_label)
                 VALUES (:t, :c, :ft, :fi, :tt, :ti, :r, :rel, :n, :a)
                 ON DUPLICATE KEY UPDATE reliability = VALUES(reliability), note = VALUES(note)',
                [
                    't' => $tenantId,
                    'c' => $caseId,
                    'ft' => (string) ($data['from_type'] ?? 'person'),
                    'fi' => $fromId,
                    'tt' => (string) ($data['to_type'] ?? 'person'),
                    'ti' => $toId,
                    'r' => $relation,
                    'rel' => $reliability,
                    'n' => ($data['note'] ?? null) ?: null,
                    'a' => ($data['author_label'] ?? null) ?: null,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function deleteRelation(int $id, int $tenantId): bool
    {
        try {
            return $this->db->execute(
                'DELETE FROM sse_relations WHERE id = :id AND tenant_id = :t',
                ['id' => $id, 't' => $tenantId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function edge(
        string $fromType,
        int $fromId,
        string $toType,
        int $toId,
        string $relation,
        string $source,
        string $reliability,
        string $note
    ): array {
        return [
            'from_type' => $fromType,
            'from_id' => $fromId,
            'to_type' => $toType,
            'to_id' => $toId,
            'relation' => $relation,
            'relation_label' => self::relationLabel($relation),
            'source' => $source,
            'source_label' => self::SOURCE_LABELS[$source] ?? 'Déduit',
            'reliability' => $reliability,
            'reliability_label' => self::reliabilityLabel($reliability),
            'note' => $note,
        ];
    }
}
