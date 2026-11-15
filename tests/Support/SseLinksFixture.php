<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Sse\Links\SseLinkJnetBridge;
use PDO;

/**
 * Graphe RÉALISTE du widget « Liaisons » : une personne reliée à une identité du registre, deux papiers, trois contrôles, une
 * fiche SEEK II, un avis de recherche, deux documents émis, quatre photos, un dossier validé (classé automatiquement), une fiche
 * de renseignement, une transmission, des liens de provenance Cerbère et un lien manuel ; plus des éléments qu'un lecteur sans
 * droit ne doit pas voir (identité d'un membre, identité de couverture, dossier confidentiel) et une autre communauté.
 */
final class SseLinksFixture
{
    /**
     * @return array<string, int>
     */
    public static function seed(PDO $pdo, bool $withLinks = true): array
    {
        $ids = [];
        $ins = static function (string $table, array $row) use ($pdo): int {
            $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));

            return (int) $pdo->lastInsertId();
        };
        $pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561190000000007', 'Analyste Sept'), (9, 1, '76561190000000009', 'Sergent Membre'), (11, 1, '76561190000000011', 'Autre')");

        $ids['person'] = $ins('sse_persons', ['tenant_id' => 1, 'last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'alias' => 'La Chouette', 'created_at' => '2026-11-01 10:00:00']);
        $ids['identity'] = $ins('sse_identities', ['tenant_id' => 1, 'person_id' => $ids['person'], 'kind' => 'civil', 'last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'created_by_user_id' => 7, 'created_at' => '2026-11-01 10:05:00']);
        $ids['paper1'] = $ins('sse_id_documents', ['tenant_id' => 1, 'identity_id' => $ids['identity'], 'doc_type' => 'passport', 'country_code' => 'TAK', 'doc_number' => 'TK1234567', 'workflow' => 'issued', 'st_origin' => 'official', 'created_by_user_id' => 7, 'created_at' => '2026-11-01 11:00:00']);
        $ids['paper2'] = $ins('sse_id_documents', ['tenant_id' => 1, 'identity_id' => $ids['identity'], 'doc_type' => 'id_card', 'country_code' => 'TAK', 'doc_number' => 'TK7654321', 'workflow' => 'validated', 'st_origin' => 'official', 'created_by_user_id' => 7, 'created_at' => '2026-11-01 11:30:00']);
        foreach ([['check1', 'k1', $ids['paper1'], '2026-11-02 14:00:00'], ['check2', 'k2', $ids['paper1'], '2026-11-05 09:30:00'], ['check3', 'k3', null, '2026-11-09 18:45:00']] as [$k, $key, $doc, $at]) {
            $ids[$k] = $ins('sse_id_checks', ['tenant_id' => 1, 'idempotency_key' => $key, 'document_id' => $doc, 'identity_id' => $ids['identity'], 'person_id' => $ids['person'], 'controller_callsign' => 'ALPHA-2', 'verdict' => 'ok', 'created_at' => $at]);
        }
        $ids['seek'] = $ins('sse_seek_records', ['tenant_id' => 1, 'rec' => 'REC-0001', 'verdict' => 'concordant', 'identity_id' => $ids['identity'], 'person_id' => $ids['person'], 'check_id' => $ids['check1'], 'controller_callsign' => 'ALPHA-2', 'created_at' => '2026-11-02 14:05:00']);
        $ids['notice'] = $ins('sse_wanted_notices', ['tenant_id' => 1, 'identity_id' => $ids['identity'], 'ref' => 'A-0001/2026', 'status' => 'active', 'active_key' => 1, 'issued_at' => '2026-11-06 08:00:00', 'created_by_user_id' => 7, 'created_at' => '2026-11-06 08:00:00']);
        $ids['issue1'] = $ins('sse_doc_issues', ['tenant_id' => 1, 'person_id' => $ids['person'], 'identity_id' => $ids['identity'], 'model' => 'notice', 'ref' => 'AR-2026-0001', 'classification' => 'encadrement', 'issued_by_user_id' => 7, 'issued_at' => '2026-11-06 08:10:00']);
        $ids['issue2'] = $ins('sse_doc_issues', ['tenant_id' => 1, 'person_id' => $ids['person'], 'identity_id' => $ids['identity'], 'model' => 'identification', 'ref' => 'FI-2026-0002', 'classification' => 'encadrement', 'issued_by_user_id' => 7, 'issued_at' => '2026-11-07 09:00:00']);
        foreach ([1, 2, 3, 4] as $n) {
            $ids['photo' . $n] = $ins('sse_file_photos', ['tenant_id' => 1, 'identity_id' => $ids['identity'], 'path' => 'x/' . $n . '.jpg', 'mime' => 'image/jpeg', 'kind' => 'field', 'source' => 'upload', 'caption' => 'Cliché ' . $n, 'visibility' => 'known', 'uploaded_by_user_id' => 7, 'created_at' => '2026-11-0' . (2 + $n) . ' 12:00:00']);
        }

        $ids['case'] = $ins('sse_cases', ['tenant_id' => 1, 'reference_code' => 'SSE-2026-0001', 'title' => 'Réseau des passeurs', 'status' => 'ouvert', 'classification' => 'interne', 'created_at' => '2026-11-03 08:00:00']);
        $ins('sse_case_persons', ['case_id' => $ids['case'], 'person_id' => $ids['person'], 'tenant_id' => 1, 'linked_by' => null, 'note' => 'Classement automatique — dossier ouvert unique', 'created_at' => '2026-11-03 08:01:00']);
        $ids['note'] = $ins('sse_field_notes', ['tenant_id' => 1, 'reference_code' => 'FR-2026-0004', 'title' => 'Observation du pont', 'classification' => 'interne', 'status' => 'transmise', 'case_id' => $ids['case'], 'author_user_id' => 7, 'created_at' => '2026-11-09 19:00:00']);
        $pdo->prepare('UPDATE sse_id_checks SET field_note_id = ? WHERE id = ?')->execute([$ids['note'], $ids['check3']]);
        $ids['transmission'] = $ins('sse_intel_events', ['tenant_id' => 1, 'event_type' => 'identite_relevee', 'summary' => 'Identité relevée au pont nord', 'event_time' => '2026-11-04 10:00:00', 'created_at' => '2026-11-04 10:00:00', 'case_id' => $ids['case']]);
        $ids['document'] = $ins('sse_documents', ['tenant_id' => 1, 'reference_code' => 'DOC-2026-0003', 'case_id' => $ids['case'], 'title' => 'Compte rendu du 4 novembre', 'status' => 'valide', 'classification' => 'interne', 'created_by' => 7, 'validated_by' => 7, 'created_at' => '2026-11-10 08:00:00']);
        $ids['site'] = $ins('sse_sites', ['tenant_id' => 1, 'name' => 'Entrepôt du port', 'site_type' => 'entrepot', 'status' => 'ouvert', 'created_at' => '2026-11-04 11:00:00']);

        // Ce qu'un lecteur sans droit ne doit pas voir.
        $ids['member_identity'] = $ins('sse_identities', ['tenant_id' => 1, 'person_id' => null, 'kind' => 'alias', 'user_id' => 9, 'last_name' => 'Dupuis', 'first_name' => 'Marc', 'created_at' => '2026-11-02 09:00:00']);
        $ids['cover_identity'] = $ins('sse_identities', ['tenant_id' => 1, 'person_id' => null, 'kind' => 'cover', 'last_name' => 'Faux-Nom', 'first_name' => 'Léon', 'created_at' => '2026-11-02 09:30:00']);
        $ins('sse_file_links', ['tenant_id' => 1, 'identity_id' => $ids['identity'], 'kind' => 'associate', 'value' => 'Dupuis Marc', 'value_norm' => 'dupuis marc', 'linked_identity_id' => $ids['member_identity'], 'relation' => 'associé', 'visibility' => 'known', 'created_at' => '2026-11-08 10:00:00']);
        $ins('sse_file_links', ['tenant_id' => 1, 'identity_id' => $ids['identity'], 'kind' => 'associate', 'value' => 'Faux-Nom Léon', 'value_norm' => 'faux-nom leon', 'linked_identity_id' => $ids['cover_identity'], 'relation' => 'associé', 'visibility' => 'known', 'created_at' => '2026-11-08 10:05:00']);
        $ids['vehicle'] = $ins('sse_file_links', ['tenant_id' => 1, 'identity_id' => $ids['identity'], 'kind' => 'vehicle', 'value' => 'Fourgon blanc TK-4412', 'value_norm' => 'fourgon blanc tk-4412', 'visibility' => 'known', 'created_at' => '2026-11-04 13:00:00']);
        $ids['case_secret'] = $ins('sse_cases', ['tenant_id' => 1, 'reference_code' => 'SSE-2026-0002', 'title' => 'Dossier confidentiel', 'status' => 'ouvert', 'classification' => 'confidentiel', 'created_at' => '2026-11-03 09:00:00']);
        $ins('sse_case_persons', ['case_id' => $ids['case_secret'], 'person_id' => $ids['person'], 'tenant_id' => 1, 'linked_by' => 7, 'note' => 'Rattaché à la main', 'created_at' => '2026-11-03 09:05:00']);

        if (!$withLinks) {
            $pdo->exec("INSERT INTO sse_persons (tenant_id, last_name, first_name, created_at) VALUES (2, 'Autre', 'Communauté', '2026-11-01 09:00:00')");
            $ids['person_t2'] = (int) $pdo->lastInsertId();

            return $ids;
        }

        // Liens de provenance : deux Cerbères et un lien manuel.
        $link = static fn (string $st, int $si, string $tt, int $ti, string $rel, string $reason, ?string $rule, string $by, ?int $uid, string $at) => $ins('sse_entity_links', [
            'tenant_id' => 1, 'source_type' => $st, 'source_id' => (string) $si, 'target_type' => $tt, 'target_id' => (string) $ti, 'relation' => $rel, 'reason' => $reason,
            'rule_code' => $rule, 'created_by' => $by, 'created_by_user_id' => $uid, 'created_at' => $at,
        ]);
        $ids['link_doc'] = $link('check', $ids['check1'], 'identity', $ids['identity'], 'identified_as', 'Contrôle rattaché à l’identité du papier présenté.', 'cerbere.link.check_document', 'system', null, '2026-11-02 14:00:01');
        $ids['link_bio'] = $link('check', $ids['check3'], 'identity', $ids['identity'], 'identified_as', 'Contrôle rattaché par la clé biométrique déjà connue.', 'cerbere.link.check_biokey', 'system', null, '2026-11-09 18:45:01');
        $ids['link_case'] = $link('person', $ids['person'], 'case', $ids['case'], 'filed_in', 'Fiche classée au seul dossier ouvert.', 'cerbere.link.case_single_open', 'system', null, '2026-11-03 08:01:00');
        $ids['link_manual'] = $link('note', $ids['note'], 'person', $ids['person'], 'cited_in', 'La fiche décrit son passage au pont nord.', 'manual.link', 'user', 7, '2026-11-09 19:30:00');

        // Une autre communauté : mêmes numéros de personne, jamais visibles depuis la communauté 1.
        $pdo->exec("INSERT INTO sse_persons (tenant_id, last_name, first_name, created_at) VALUES (2, 'Autre', 'Communauté', '2026-11-01 09:00:00')");
        $ids['person_t2'] = (int) $pdo->lastInsertId();
        $ids['link_t2'] = $ins('sse_entity_links', ['tenant_id' => 2, 'source_type' => 'person', 'source_id' => (string) $ids['person'], 'target_type' => 'person', 'target_id' => (string) $ids['person_t2'], 'relation' => 'related', 'reason' => 'Lien de la communauté 2', 'rule_code' => 'manual.link', 'created_by' => 'user', 'created_by_user_id' => 7, 'created_at' => '2026-11-10 10:00:00']);

        return $ids;
    }

    /** Passerelle JNET de test : un compte rendu cite le dossier. */
    public static function jnetBridge(string $ref, string $id = 'echange:5', string $title = 'Compte rendu du 4 novembre'): SseLinkJnetBridge
    {
        return new class ($ref, $id, $title) implements SseLinkJnetBridge {
            public function __construct(private string $ref, private string $id, private string $title)
            {
            }

            public function citations(int $tenantId, array $refs): array
            {
                return in_array($this->ref, $refs, true)
                    ? [['ref' => $this->ref, 'id' => $this->id, 'title' => $this->title, 'summary' => 'Compte rendu', 'href' => '/jnet/u/1?vue=echanges#ex-5', 'date' => '2026-11-11 08:00:00', 'by' => 'Analyste Sept']]
                    : [];
            }
        };
    }
}
