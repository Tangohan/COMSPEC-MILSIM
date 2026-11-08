<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Core\Database;
use App\Support\SseIdentityCatalog;

/**
 * Documents RH > « Documents d'identité » : les papiers fictifs de COMSPEC Identity, regroupés par membre.
 *
 * Ce sont des papiers de jeu (aucune vraie pièce d'identité). Un infiltré ne doit pas être démasqué par une
 * consultation RH : sans l'habilitation Confidentiel (`$truth`), la requête elle-même écarte toute identité qui n'est
 * pas la civile (alias, couverture, falsifiée, générée) et tout papier qui n'est pas « délivré officiellement ».
 * Rien n'est filtré après coup dans la vue : ce qui est écarté n'est ni compté ni nommé. Aucune adresse e-mail,
 * aucun numéro de papier, aucun signalement de recherche ou de surveillance n'est lu.
 */
final class RhIdentityPapersOverview
{
    /** Papiers et membres lus au plus : la page est un aperçu, la fiche et l'atelier donnent le détail. */
    public const DOCUMENT_LIMIT = 600;

    /** Échéance proche : papier qui expire dans ce nombre de jours. */
    public const EXPIRY_WARN_DAYS = 60;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @return array{
     *   ready: bool,
     *   truth: bool,
     *   members: list<array{id: int, name: string, documents: list<array<string, mixed>>}>,
     *   summary: array{members: int, documents: int, to_renew: int, secret: int}
     * }
     */
    public function forTenant(int $tenantId, bool $truth): array
    {
        $empty = ['ready' => false, 'truth' => $truth, 'members' => [], 'summary' => ['members' => 0, 'documents' => 0, 'to_renew' => 0, 'secret' => 0]];
        if ($tenantId < 1 || !$this->tablesExist()) {
            return $empty;
        }
        $empty['ready'] = true;
        $limit = self::DOCUMENT_LIMIT;
        $mask = $truth ? '' : " AND i.kind = 'civil' AND d.st_origin = 'official'";
        try {
            $rows = $this->db->fetchAll(
                "SELECT i.user_id AS uid, u.display_name, u.callsign, d.id AS doc_id, d.doc_type, d.workflow, d.st_validity,
                        d.st_report, d.st_origin, d.expires_on, i.kind AS identity_kind
                 FROM sse_id_documents d
                 JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id
                 JOIN users u ON u.id = i.user_id AND u.tenant_id = d.tenant_id
                 WHERE d.tenant_id = :t AND u.status = 'active'" . $mask . "
                 ORDER BY u.display_name ASC, i.user_id ASC, d.id ASC
                 LIMIT {$limit}",
                ['t' => $tenantId]
            );
        } catch (\Throwable $e) {
            error_log('Documents RH : papiers d’identité indisponibles : ' . $e->getMessage());

            return $empty;
        }

        return self::group($rows, $truth, $empty['ready']);
    }

    /**
     * Regroupe les lignes par membre. Pure : testable sans base.
     *
     * @param list<array<string, mixed>> $rows
     * @return array{ready: bool, truth: bool, members: list<array{id: int, name: string, documents: list<array<string, mixed>>}>, summary: array{members: int, documents: int, to_renew: int, secret: int}}
     */
    public static function group(array $rows, bool $truth, bool $ready = true, ?int $today = null): array
    {
        $today ??= strtotime(date('Y-m-d')) ?: time();
        $byMember = [];
        $summary = ['members' => 0, 'documents' => 0, 'to_renew' => 0, 'secret' => 0];
        foreach ($rows as $row) {
            // Donnée manquante = traitée comme confidentielle.
            $secret = ($row['identity_kind'] ?? '') !== 'civil' || ($row['st_origin'] ?? '') !== 'official';
            if ($secret && !$truth) {
                // Garde-fou : même si la requête laissait passer une ligne, elle n'est ni montrée ni comptée.
                continue;
            }
            $uid = (int) ($row['uid'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $name = trim((string) ($row['display_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['callsign'] ?? ''));
            }
            if ($name === '') {
                $name = 'Membre n° ' . $uid;
            }
            $expires = substr(trim((string) ($row['expires_on'] ?? '')), 0, 10);
            $expTs = $expires !== '' ? strtotime($expires) : false;
            $daysLeft = $expTs !== false ? (int) round(($expTs - $today) / 86400) : null;
            $validity = (string) ($row['st_validity'] ?? 'valid');
            $report = (string) ($row['st_report'] ?? 'none');
            $workflow = (string) ($row['workflow'] ?? 'draft');
            $toRenew = $workflow !== 'revoked' && (
                in_array($validity, ['expired', 'suspended', 'revoked'], true)
                || ($daysLeft !== null && $daysLeft <= self::EXPIRY_WARN_DAYS)
            );
            $byMember[$uid]['id'] = $uid;
            $byMember[$uid]['name'] = $name;
            $byMember[$uid]['documents'][] = [
                'id' => (int) ($row['doc_id'] ?? 0),
                'type' => (string) (SseIdentityCatalog::DOC_TYPES[(string) ($row['doc_type'] ?? '')] ?? 'Papier d’identité'),
                'state' => (string) (SseIdentityCatalog::WORKFLOWS[$workflow] ?? 'Brouillon'),
                'workflow' => $workflow,
                'validity' => $validity === 'valid' ? '' : (string) (SseIdentityCatalog::VALIDITIES[$validity] ?? ''),
                // Seuls les signalements administratifs : jamais « recherché » ni « sous surveillance ».
                'report' => in_array($report, ['lost', 'stolen'], true) ? (string) SseIdentityCatalog::REPORTS[$report] : '',
                'expires_on' => $expires,
                'days_left' => $daysLeft,
                'to_renew' => $toRenew,
                'secret' => $secret,
            ];
            ++$summary['documents'];
            if ($toRenew) {
                ++$summary['to_renew'];
            }
            if ($secret) {
                ++$summary['secret'];
            }
        }
        $members = array_values($byMember);
        $summary['members'] = count($members);

        return ['ready' => $ready, 'truth' => $truth, 'members' => $members, 'summary' => $summary];
    }

    private function tablesExist(): bool
    {
        try {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS n FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('sse_id_documents', 'sse_identities')"
            );

            return (int) ($row['n'] ?? 0) === 2;
        } catch (\Throwable) {
            return false;
        }
    }
}
