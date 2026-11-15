<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

use App\Core\Database;
use App\Support\SseCerbere;

/**
 * Les liens DÉJÀ présents dans les colonnes du portail (clés étrangères, rattachements). Chaque définition dit : quelle table,
 * quelles colonnes portent les deux éléments, quelle relation, d'où viennent la date, l'auteur et, quand elle existe, la
 * raison. Un lien déduit n'a pas de motif enregistré (sauf colonne de raison) : il est signalé « déduit », ce qui est le trou
 * que les liens de provenance (sse_entity_links) comblent.
 *
 * Une requête par définition et par niveau du parcours, bornée par la communauté et par les identifiants du niveau.
 */
final class SseLinkEdges
{
    /**
     * @var list<array{table: string, a: array{0: string, 1: string}, b: array{0: string, 1: string}, relation: string, date: string, actor?: string, actorLabel?: string, reasonCol?: string, where?: string, note: string}>
     */
    public const DEFS = [
        ['table' => 'sse_identities', 'a' => ['identity', 'id'], 'b' => ['person', 'person_id'], 'relation' => 'attached_to', 'date' => 'created_at', 'actor' => 'created_by_user_id', 'where' => 'person_id IS NOT NULL AND person_id > 0',
            'note' => 'L’identité du registre est rattachée à la fiche personne.'],
        ['table' => 'sse_id_documents', 'a' => ['paper', 'id'], 'b' => ['identity', 'identity_id'], 'relation' => 'attached_to', 'date' => 'created_at', 'actor' => 'created_by_user_id', 'where' => 'identity_id IS NOT NULL AND identity_id > 0',
            'note' => 'Le papier appartient à cette identité.'],
        ['table' => 'sse_id_documents', 'a' => ['paper', 'id'], 'b' => ['person', 'holder_person_id'], 'relation' => 'held_by', 'date' => 'created_at', 'actor' => 'created_by_user_id', 'where' => 'holder_person_id IS NOT NULL AND holder_person_id > 0',
            'note' => 'La personne qui détient le papier.'],
        ['table' => 'sse_id_checks', 'a' => ['check', 'id'], 'b' => ['paper', 'document_id'], 'relation' => 'checked', 'date' => 'created_at', 'actorLabel' => 'controller_callsign', 'where' => 'document_id IS NOT NULL AND document_id > 0',
            'note' => 'Le papier présenté au contrôle.'],
        ['table' => 'sse_id_checks', 'a' => ['check', 'id'], 'b' => ['identity', 'identity_id'], 'relation' => 'identified_as', 'date' => 'created_at', 'actorLabel' => 'controller_callsign', 'where' => 'identity_id IS NOT NULL AND identity_id > 0',
            'note' => 'Le contrôle a été rattaché à cette identité (papier présenté ou clé biométrique : le moyen n’est pas enregistré).'],
        ['table' => 'sse_id_checks', 'a' => ['check', 'id'], 'b' => ['person', 'person_id'], 'relation' => 'identified_as', 'date' => 'created_at', 'actorLabel' => 'controller_callsign', 'where' => 'person_id IS NOT NULL AND person_id > 0',
            'note' => 'Le contrôle a été rattaché à cette fiche personne (fiche désignée, identifiant de jeu ou clé biométrique : le moyen n’est pas enregistré).'],
        ['table' => 'sse_id_checks', 'a' => ['note', 'field_note_id'], 'b' => ['check', 'id'], 'relation' => 'created_from', 'date' => 'created_at', 'actorLabel' => 'controller_callsign', 'where' => 'field_note_id IS NOT NULL AND field_note_id > 0',
            'note' => 'La fiche de renseignement est issue de ce contrôle.'],
        ['table' => 'sse_seek_records', 'a' => ['seek', 'id'], 'b' => ['identity', 'identity_id'], 'relation' => 'seek_of', 'date' => 'created_at', 'actorLabel' => 'controller_callsign', 'where' => 'identity_id IS NOT NULL AND identity_id > 0',
            'note' => 'Relevé SEEK II rattaché à l’identité.'],
        ['table' => 'sse_seek_records', 'a' => ['seek', 'id'], 'b' => ['person', 'person_id'], 'relation' => 'seek_of', 'date' => 'created_at', 'actorLabel' => 'controller_callsign', 'where' => 'person_id IS NOT NULL AND person_id > 0',
            'note' => 'Relevé SEEK II rattaché à la fiche personne.'],
        ['table' => 'sse_seek_records', 'a' => ['seek', 'id'], 'b' => ['paper', 'document_id'], 'relation' => 'checked', 'date' => 'created_at', 'actorLabel' => 'controller_callsign', 'where' => 'document_id IS NOT NULL AND document_id > 0',
            'note' => 'Papier présenté au relevé.'],
        ['table' => 'sse_seek_records', 'a' => ['seek', 'id'], 'b' => ['check', 'check_id'], 'relation' => 'created_from', 'date' => 'created_at', 'actorLabel' => 'controller_callsign', 'where' => 'check_id IS NOT NULL AND check_id > 0',
            'note' => 'Le relevé SEEK II est issu de ce contrôle.'],
        ['table' => 'sse_wanted_notices', 'a' => ['notice', 'id'], 'b' => ['identity', 'identity_id'], 'relation' => 'notice_on', 'date' => 'issued_at', 'actor' => 'created_by_user_id',
            'note' => 'Avis de recherche posé depuis la fiche de l’identité.'],
        ['table' => 'sse_doc_issues', 'a' => ['doc_issue', 'id'], 'b' => ['person', 'person_id'], 'relation' => 'issued_for', 'date' => 'issued_at', 'actor' => 'issued_by_user_id', 'where' => 'person_id IS NOT NULL AND person_id > 0',
            'note' => 'Document émis depuis la fiche personne.'],
        ['table' => 'sse_doc_issues', 'a' => ['doc_issue', 'id'], 'b' => ['identity', 'identity_id'], 'relation' => 'issued_for', 'date' => 'issued_at', 'actor' => 'issued_by_user_id', 'where' => 'identity_id IS NOT NULL AND identity_id > 0',
            'note' => 'Document émis depuis la fiche de l’identité.'],
        ['table' => 'sse_file_photos', 'a' => ['photo', 'id'], 'b' => ['identity', 'identity_id'], 'relation' => 'photo_of', 'date' => 'created_at', 'actor' => 'uploaded_by_user_id', 'where' => 'identity_id IS NOT NULL AND identity_id > 0',
            'note' => 'Photo versée au dossier de l’identité.'],
        ['table' => 'sse_file_links', 'a' => ['attribute', 'id'], 'b' => ['identity', 'identity_id'], 'relation' => 'attached_to', 'date' => 'created_at', 'actor' => 'created_by_user_id', 'where' => "(linked_identity_id IS NULL OR linked_identity_id = 0) AND kind IN ('address','vehicle','phone','email')",
            'note' => 'Adresse, véhicule, téléphone ou courriel noté sur la fiche.'],
        ['table' => 'sse_file_links', 'a' => ['identity', 'identity_id'], 'b' => ['identity', 'linked_identity_id'], 'relation' => 'related', 'date' => 'created_at', 'actor' => 'created_by_user_id', 'reasonCol' => 'relation', 'where' => 'linked_identity_id IS NOT NULL AND linked_identity_id > 0',
            'note' => 'Personne liée notée sur la fiche (famille, associé, cellule).'],
        ['table' => 'sse_file_mentions', 'a' => ['mention', 'id'], 'b' => ['identity', 'identity_id'], 'relation' => 'mention_on', 'date' => 'created_at', 'actor' => 'created_by_user_id', 'where' => 'identity_id IS NOT NULL AND identity_id > 0 AND lifted_at IS NULL',
            'note' => 'Mention aux fichiers posée sur l’identité.'],
        ['table' => 'sse_file_mentions', 'a' => ['mention', 'id'], 'b' => ['paper', 'document_id'], 'relation' => 'mention_on', 'date' => 'created_at', 'actor' => 'created_by_user_id', 'where' => 'document_id IS NOT NULL AND document_id > 0 AND lifted_at IS NULL',
            'note' => 'Mention aux fichiers posée sur le papier.'],
        ['table' => 'sse_case_persons', 'a' => ['person', 'person_id'], 'b' => ['case', 'case_id'], 'relation' => 'filed_in', 'date' => 'created_at', 'actor' => 'linked_by', 'reasonCol' => 'note',
            'note' => 'Fiche classée dans le dossier (la note libre du classement fait office de motif).'],
        ['table' => 'sse_field_notes', 'a' => ['note', 'id'], 'b' => ['case', 'case_id'], 'relation' => 'filed_in', 'date' => 'created_at', 'actor' => 'author_user_id', 'where' => 'case_id IS NOT NULL AND case_id > 0',
            'note' => 'Fiche de renseignement rattachée au dossier.'],
        ['table' => 'sse_field_notes', 'a' => ['note', 'id'], 'b' => ['interest', 'interest_case_id'], 'relation' => 'filed_in', 'date' => 'created_at', 'actor' => 'author_user_id', 'where' => 'interest_case_id IS NOT NULL AND interest_case_id > 0',
            'note' => 'Fiche de renseignement rattachée au dossier d’intérêt.'],
        ['table' => 'sse_documents', 'a' => ['document', 'id'], 'b' => ['case', 'case_id'], 'relation' => 'filed_in', 'date' => 'created_at', 'actor' => 'created_by', 'where' => 'case_id IS NOT NULL AND case_id > 0',
            'note' => 'Document de rédaction rattaché au dossier.'],
        ['table' => 'sse_intel_events', 'a' => ['transmission', 'id'], 'b' => ['case', 'case_id'], 'relation' => 'filed_in', 'date' => 'created_at', 'where' => 'case_id IS NOT NULL AND case_id > 0',
            'note' => 'Transmission classée au dossier (le moyen du classement n’est pas enregistré).'],
        ['table' => 'sse_intel_events', 'a' => ['transmission', 'id'], 'b' => ['interest', 'interest_case_id'], 'relation' => 'filed_in', 'date' => 'created_at', 'where' => 'interest_case_id IS NOT NULL AND interest_case_id > 0',
            'note' => 'Transmission classée au dossier d’intérêt.'],
        ['table' => 'sse_cross_decisions', 'a' => ['interest', 'interest_case_id'], 'b' => ['person', 'person_id'], 'relation' => 'cross_checked', 'date' => 'created_at', 'actor' => 'decided_by', 'reasonCol' => 'reason', 'where' => 'person_id IS NOT NULL AND person_id > 0',
            'note' => 'Décision d’un analyste sur un croisement proposé par Cerbère.'],
        ['table' => 'sse_relations', 'a' => ['person', 'from_id'], 'b' => ['person', 'to_id'], 'relation' => 'related', 'date' => 'created_at', 'actorLabel' => 'author_label', 'reasonCol' => 'relation', 'where' => "from_type = 'person' AND to_type = 'person'",
            'note' => 'Relation entre deux personnes d’un dossier (analyste ou règle automatique).'],
        ['table' => 'sse_relations', 'a' => ['person', 'from_id'], 'b' => ['site', 'to_id'], 'relation' => 'related', 'date' => 'created_at', 'actorLabel' => 'author_label', 'reasonCol' => 'relation', 'where' => "from_type = 'person' AND to_type = 'site'",
            'note' => 'Relation entre une personne et un site d’un dossier.'],
    ];

    public function __construct(private ?Database $db = null)
    {
    }

    private function db(): Database
    {
        return $this->db ?? Database::getInstance();
    }

    /**
     * Les liens des tables du portail qui touchent des éléments du niveau courant : une requête par définition concernée.
     *
     * @param array<string, list<string>> $frontier type => identifiants
     * @return list<array<string, mixed>> liens normalisés (voir SseLinkGraph::edge())
     */
    public function touching(int $tenantId, array $frontier, int $limit): array
    {
        $out = [];
        foreach (self::DEFS as $def) {
            [$aType, $aCol] = $def['a'];
            [$bType, $bCol] = $def['b'];
            $aIds = $frontier[$aType] ?? [];
            $bIds = $frontier[$bType] ?? [];
            if ($aIds === [] && $bIds === []) {
                continue;
            }
            $params = ['t' => $tenantId];
            $or = [];
            $n = 0;
            foreach ([[$aCol, $aIds], [$bCol, $bIds]] as [$col, $ids]) {
                if ($ids === []) {
                    continue;
                }
                $marks = [];
                foreach (array_slice($ids, 0, 400) as $id) {
                    $key = 'p' . $n++;
                    $params[$key] = (int) $id;
                    $marks[] = ':' . $key;
                }
                $or[] = $col . ' IN (' . implode(',', $marks) . ')';
            }
            $cols = [$aCol . ' AS a_id', $bCol . ' AS b_id', $def['date'] . ' AS at'];
            foreach (['actor' => 'actor_id', 'actorLabel' => 'actor_text', 'reasonCol' => 'reason_text'] as $key => $alias) {
                if (isset($def[$key])) {
                    $cols[] = $def[$key] . ' AS ' . $alias;
                }
            }
            $sql = 'SELECT ' . implode(', ', $cols) . ' FROM ' . $def['table'] . ' WHERE tenant_id = :t AND (' . implode(' OR ', $or) . ')'
                . (isset($def['where']) ? ' AND ' . $def['where'] : '') . ' ORDER BY ' . $def['date'] . ' ASC LIMIT ' . max(1, min(1000, $limit));
            try {
                $rows = $this->db()->fetchAll($sql, $params);
            } catch (\Throwable) {
                // Table ou colonne absente d'une ancienne base : cette source est ignorée, les autres continuent.
                continue;
            }
            foreach ($rows as $row) {
                $aId = (string) (int) $row['a_id'];
                $bId = (string) (int) $row['b_id'];
                if ($aId === '0' || $bId === '0' || ($aType === $bType && $aId === $bId)) {
                    continue;
                }
                $reason = trim((string) ($row['reason_text'] ?? ''));
                $auto = (isset($row['actor_text']) && trim((string) $row['actor_text']) === \App\Services\Sse\SseAutomationService::RULE_AUTHOR)
                    || str_starts_with($reason, 'Classement automatique');
                $out[] = [
                    'a' => [$aType, $aId],
                    'b' => [$bType, $bId],
                    'relation' => $def['relation'],
                    'reason' => $reason !== '' ? $reason : $def['note'],
                    'reasoned' => $reason !== '',
                    'rule' => null,
                    'origin' => $auto ? 'cerbere' : 'derived',
                    'actor_user' => isset($row['actor_id']) && (int) $row['actor_id'] > 0 ? (int) $row['actor_id'] : null,
                    'actor_text' => $auto ? 'Cerbère' : trim((string) ($row['actor_text'] ?? '')),
                    'at' => (string) ($row['at'] ?? ''),
                    'source' => $def['table'] . ($aCol !== 'id' && $bCol === 'id' ? '.' . $aCol : ($bCol !== 'id' && $aCol === 'id' ? '.' . $bCol : '')),
                    'link_id' => null,
                    'removable' => false,
                ];
            }
        }

        return $out;
    }

    /** Libellé d'une origine : « Cerbère », « manuel », « déduit des colonnes ». */
    public static function originLabel(string $origin, ?string $rule = null): string
    {
        return match ($origin) {
            'cerbere' => SseCerbere::treatmentLabel($rule),
            'manual' => 'Manuel',
            'hidden' => '',
            default => 'Déduit',
        };
    }
}
