<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

use App\Core\Database;
use App\Services\Sse\MemberIdentityPapersService;
use App\Support\SseFileCatalog;
use App\Support\SseIdentityCatalog;
use App\Support\SseSheetCatalog;

/**
 * Registre des adaptateurs d'éléments (un par type). `standard()` donne ceux du portail ; `register()` en ajoute un (une
 * nouvelle page d'élément se branche ici, sans toucher au graphe).
 */
final class SseLinkAdapters
{
    /** @var array<string, SseLinkAdapter> */
    private array $adapters = [];

    public function register(SseLinkAdapter $adapter): self
    {
        $this->adapters[$adapter->type()] = $adapter;

        return $this;
    }

    public function get(string $type): ?SseLinkAdapter
    {
        return $this->adapters[$type] ?? null;
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->adapters);
    }

    /** Les adaptateurs du portail SSE. */
    public static function standard(?Database $db = null): self
    {
        $r = new self();
        $a = static fn (string $type, string $cols, string $from, string $idCol, string $tenantCol, \Closure $map, array $search = []): SseRowAdapter
            => new SseRowAdapter($type, $cols, $from, $idCol, $tenantCol, $map, $search, $db);
        $secret = static fn (?string $kind): bool => in_array((string) $kind, MemberIdentityPapersService::SECRET, true);
        $member = static fn (mixed $uid): ?int => (int) $uid > 0 ? (int) $uid : null;
        $name = static function (array $row, string $prefix = ''): string {
            $n = trim(trim((string) ($row[$prefix . 'first_name'] ?? '')) . ' ' . trim((string) ($row[$prefix . 'last_name'] ?? '')));

            return $n;
        };
        $idJoin = 'LEFT JOIN sse_identities i ON i.id = %s AND i.tenant_id = %s';

        $r->register($a(
            'person',
            'p.id AS id, p.first_name, p.last_name, p.alias, p.status, p.created_at',
            'FROM sse_persons p', 'p.id', 'p.tenant_id',
            static function (array $row) use ($name): array {
                $id = (int) $row['id'];
                $title = $name($row) ?: trim((string) ($row['alias'] ?? ''));
                $alias = trim((string) ($row['alias'] ?? ''));

                return ['ref' => 'IDN-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT), 'title' => $title !== '' ? $title : 'Identité non établie',
                    'summary' => $alias !== '' && $name($row) !== '' ? 'alias ' . $alias : '', 'href' => 'atak/sse/identites/' . $id, 'date' => (string) ($row['created_at'] ?? '')];
            },
            ['p.last_name', 'p.first_name', 'p.alias']
        ));

        $r->register($a(
            'identity',
            'i.id AS id, i.first_name, i.last_name, i.kind, i.user_id, i.created_at',
            'FROM sse_identities i', 'i.id', 'i.tenant_id',
            static function (array $row) use ($name, $secret, $member): array {
                $id = (int) $row['id'];

                return ['ref' => SseIdentityCatalog::identityRef($id), 'title' => $name($row) ?: 'Identité', 'summary' => SseIdentityCatalog::label(SseIdentityCatalog::KINDS, (string) ($row['kind'] ?? ''), ''),
                    'href' => 'atak/sse/papiers/identites/' . $id, 'date' => (string) ($row['created_at'] ?? ''), 'secret' => $secret($row['kind'] ?? null), 'member' => $member($row['user_id'] ?? 0)];
            },
            ['i.last_name', 'i.first_name']
        ));

        $r->register($a(
            'paper',
            'd.id AS id, d.doc_type, d.country_code, d.doc_number, d.workflow, d.st_origin, d.created_at, i.kind AS identity_kind, i.user_id AS identity_user',
            'FROM sse_id_documents d ' . sprintf($idJoin, 'd.identity_id', 'd.tenant_id'), 'd.id', 'd.tenant_id',
            static function (array $row) use ($secret, $member): array {
                $id = (int) $row['id'];
                $type = SseIdentityCatalog::label(SseIdentityCatalog::DOC_TYPES, (string) ($row['doc_type'] ?? ''), 'Papier');

                return ['ref' => SseIdentityCatalog::documentRef($id), 'title' => $type . ' ' . trim((string) ($row['country_code'] ?? '')),
                    'summary' => SseIdentityCatalog::label(SseIdentityCatalog::WORKFLOWS, (string) ($row['workflow'] ?? ''), ''),
                    'href' => 'atak/sse/papiers/documents/' . $id, 'date' => (string) ($row['created_at'] ?? ''),
                    'secret' => $secret($row['identity_kind'] ?? null) || $secret($row['st_origin'] ?? null), 'member' => $member($row['identity_user'] ?? 0)];
            },
            ['d.doc_number']
        ));

        $r->register($a(
            'check',
            'c.id AS id, c.verdict, c.controller_callsign, c.created_at, i.kind AS identity_kind, i.user_id AS identity_user',
            'FROM sse_id_checks c ' . sprintf($idJoin, 'c.identity_id', 'c.tenant_id'), 'c.id', 'c.tenant_id',
            static function (array $row) use ($secret, $member): array {
                $id = (int) $row['id'];
                $by = trim((string) ($row['controller_callsign'] ?? ''));

                return ['ref' => SseIdentityCatalog::checkRef($id), 'title' => 'Contrôle ' . SseIdentityCatalog::checkRef($id),
                    'summary' => ($by !== '' ? 'par ' . $by : '') . (($row['verdict'] ?? '') !== '' ? ($by !== '' ? ' · ' : '') . SseIdentityCatalog::label(SseIdentityCatalog::VERDICTS, (string) $row['verdict'], '') : ''),
                    'href' => 'atak/sse/papiers/controles/' . $id, 'date' => (string) ($row['created_at'] ?? ''),
                    'secret' => $secret($row['identity_kind'] ?? null), 'member' => $member($row['identity_user'] ?? 0)];
            },
            ['CAST(c.id AS CHAR)', 'c.doc_number']
        ));

        $r->register($a(
            'seek',
            's.id AS id, s.rec, s.verdict, s.controller_callsign, s.created_at, i.kind AS identity_kind, i.user_id AS identity_user',
            'FROM sse_seek_records s ' . sprintf($idJoin, 's.identity_id', 's.tenant_id'), 's.id', 's.tenant_id',
            static function (array $row) use ($secret, $member): array {
                $by = trim((string) ($row['controller_callsign'] ?? ''));

                return ['ref' => (string) $row['rec'], 'title' => 'Fiche SEEK II ' . $row['rec'],
                    'summary' => ($by !== '' ? 'par ' . $by : ''), 'href' => 'atak/sse/fichiers/seek?q=' . rawurlencode((string) $row['rec']),
                    'date' => (string) ($row['created_at'] ?? ''), 'secret' => $secret($row['identity_kind'] ?? null), 'member' => $member($row['identity_user'] ?? 0)];
            },
            ['s.rec', 's.last_name']
        ));

        $r->register($a(
            'notice',
            'n.id AS id, n.ref, n.status, n.notice_type, n.issued_at, i.kind AS identity_kind, i.user_id AS identity_user',
            'FROM sse_wanted_notices n ' . sprintf($idJoin, 'n.identity_id', 'n.tenant_id'), 'n.id', 'n.tenant_id',
            static function (array $row) use ($secret, $member): array {
                return ['ref' => (string) $row['ref'], 'title' => 'Avis de recherche ' . $row['ref'], 'summary' => ($row['status'] ?? '') === 'active' ? 'actif' : (string) ($row['status'] ?? ''),
                    'href' => 'atak/sse/avis?q=' . rawurlencode((string) $row['ref']), 'date' => (string) ($row['issued_at'] ?? ''),
                    'secret' => $secret($row['identity_kind'] ?? null), 'member' => $member($row['identity_user'] ?? 0)];
            },
            ['n.ref']
        ));

        $r->register($a(
            'doc_issue',
            'x.id AS id, x.ref, x.model, x.classification, x.issued_at, i.kind AS identity_kind, i.user_id AS identity_user',
            'FROM sse_doc_issues x ' . sprintf($idJoin, 'x.identity_id', 'x.tenant_id'), 'x.id', 'x.tenant_id',
            static function (array $row) use ($secret, $member): array {
                $id = (int) $row['id'];
                $label = (string) (SseSheetCatalog::MODELS[(string) ($row['model'] ?? '')]['label'][0] ?? 'Document');

                return ['ref' => (string) $row['ref'], 'title' => $label . ' ' . $row['ref'], 'summary' => 'document émis', 'href' => 'atak/sse/identites/emissions/' . $id . '/pdf',
                    'date' => (string) ($row['issued_at'] ?? ''), 'secret' => $secret($row['identity_kind'] ?? null), 'member' => $member($row['identity_user'] ?? 0),
                    'classification' => (string) ($row['classification'] ?? '') ?: null];
            },
            ['x.ref']
        ));

        $r->register($a(
            'photo',
            'f.id AS id, f.caption, f.kind, f.source, f.visibility, f.created_at, i.kind AS identity_kind, i.user_id AS identity_user',
            'FROM sse_file_photos f ' . sprintf($idJoin, 'f.identity_id', 'f.tenant_id'), 'f.id', 'f.tenant_id',
            static function (array $row) use ($secret, $member): array {
                $id = (int) $row['id'];
                $cap = trim((string) ($row['caption'] ?? ''));

                return ['ref' => 'PHO-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT), 'title' => $cap !== '' ? $cap : 'Photo', 'summary' => (string) ($row['source'] ?? ''),
                    'href' => 'atak/sse/fichiers/photos', 'date' => (string) ($row['created_at'] ?? ''),
                    'secret' => $secret($row['identity_kind'] ?? null) || ($row['visibility'] ?? 'known') !== 'known', 'member' => $member($row['identity_user'] ?? 0)];
            },
            ['f.caption']
        ));

        $r->register($a(
            'attribute',
            'l.id AS id, l.kind, l.value, l.identity_id, l.visibility, l.created_at, i.kind AS identity_kind, i.user_id AS identity_user',
            'FROM sse_file_links l ' . sprintf($idJoin, 'l.identity_id', 'l.tenant_id'), 'l.id', 'l.tenant_id',
            static function (array $row) use ($secret, $member): array {
                $kinds = ['address' => 'Adresse', 'vehicle' => 'Véhicule', 'phone' => 'Téléphone', 'email' => 'Courriel', 'associate' => 'Personne liée'];
                $label = $kinds[(string) ($row['kind'] ?? '')] ?? 'Élément';

                return ['sections' => ['fichiers', 'papiers'], 'ref' => strtoupper(substr((string) ($row['kind'] ?? 'x'), 0, 3)) . '-' . $row['id'], 'title' => $label . ' : ' . (string) ($row['value'] ?? ''), 'summary' => $label,
                    'href' => 'atak/sse/papiers/identites/' . (int) ($row['identity_id'] ?? 0), 'date' => (string) ($row['created_at'] ?? ''),
                    'secret' => $secret($row['identity_kind'] ?? null) || ($row['visibility'] ?? 'known') !== 'known', 'member' => $member($row['identity_user'] ?? 0)];
            },
            ['l.value']
        ));

        $r->register($a(
            'mention',
            'm.id AS id, m.mention, m.created_at, i.kind AS identity_kind, i.user_id AS identity_user',
            'FROM sse_file_mentions m ' . sprintf($idJoin, 'm.identity_id', 'm.tenant_id'), 'm.id', 'm.tenant_id',
            static function (array $row) use ($secret, $member): array {
                $label = (string) (SseFileCatalog::MENTIONS[(string) ($row['mention'] ?? '')] ?? 'Mention');

                return ['ref' => 'MEN-' . $row['id'], 'title' => 'Mention : ' . $label, 'summary' => '', 'href' => 'atak/sse/fichiers/mentions',
                    'date' => (string) ($row['created_at'] ?? ''), 'secret' => $secret($row['identity_kind'] ?? null), 'member' => $member($row['identity_user'] ?? 0)];
            }
        ));

        $r->register($a(
            'case',
            'c.id AS id, c.reference_code, c.title, c.status, c.classification, c.created_at',
            'FROM sse_cases c', 'c.id', 'c.tenant_id',
            static function (array $row): array {
                return ['ref' => (string) $row['reference_code'], 'title' => trim((string) ($row['title'] ?? '')) ?: (string) $row['reference_code'],
                    'summary' => (string) (\App\Repositories\SseCaseRepository::STATUS_LABELS[(string) ($row['status'] ?? '')] ?? ''), 'href' => 'atak/sse/dossiers/' . (int) $row['id'],
                    'date' => (string) ($row['created_at'] ?? ''), 'classification' => (string) ($row['classification'] ?? '') ?: null];
            },
            ['c.reference_code', 'c.title']
        ));

        $r->register($a(
            'interest',
            'c.id AS id, c.reference_code, c.temporary_designation, c.status, c.created_at',
            'FROM sse_interest_cases c', 'c.id', 'c.tenant_id',
            static function (array $row): array {
                $t = trim((string) ($row['temporary_designation'] ?? ''));

                return ['ref' => (string) $row['reference_code'], 'title' => $t !== '' ? $t : (string) $row['reference_code'],
                    'summary' => (string) (\App\Repositories\SseInterestCaseRepository::STATUSES[(string) ($row['status'] ?? '')] ?? ''), 'href' => 'atak/sse/interet/' . (int) $row['id'],
                    'date' => (string) ($row['created_at'] ?? ''), 'interest' => true];
            },
            ['c.reference_code', 'c.temporary_designation']
        ));

        $r->register($a(
            'document',
            'd.id AS id, d.reference_code, d.title, d.status, d.classification, d.created_at',
            'FROM sse_documents d', 'd.id', 'd.tenant_id',
            static function (array $row): array {
                return ['ref' => (string) ($row['reference_code'] ?? ''), 'title' => trim((string) ($row['title'] ?? '')) ?: 'Document',
                    'summary' => ($row['status'] ?? '') === 'valide' ? 'validé' : (string) ($row['status'] ?? ''), 'href' => 'atak/sse/documents/' . (int) $row['id'],
                    'date' => (string) ($row['created_at'] ?? ''), 'classification' => (string) ($row['classification'] ?? '') ?: null];
            },
            ['d.reference_code', 'd.title']
        ));

        $r->register($a(
            'note',
            'n.id AS id, n.reference_code, n.title, n.status, n.classification, n.created_at',
            'FROM sse_field_notes n', 'n.id', 'n.tenant_id',
            static function (array $row): array {
                return ['ref' => (string) ($row['reference_code'] ?? ''), 'title' => trim((string) ($row['title'] ?? '')) ?: 'Fiche de renseignement', 'summary' => (string) ($row['status'] ?? ''),
                    'href' => 'atak/sse/fiches/' . (int) $row['id'], 'date' => (string) ($row['created_at'] ?? ''),
                    'classification' => in_array((string) ($row['classification'] ?? ''), ['interne', 'encadrement', 'confidentiel', 'tres_restreint'], true) ? (string) $row['classification'] : null];
            },
            ['n.reference_code', 'n.title']
        ));

        $r->register($a(
            'site',
            's.id AS id, s.name, s.site_type, s.status, s.created_at',
            'FROM sse_sites s', 's.id', 's.tenant_id',
            static fn (array $row): array => ['ref' => 'SITE-' . str_pad((string) (int) $row['id'], 4, '0', STR_PAD_LEFT), 'title' => trim((string) ($row['name'] ?? '')) ?: 'Site',
                'summary' => (string) ($row['site_type'] ?? ''), 'href' => 'atak/sse/sites/' . (int) $row['id'], 'date' => (string) ($row['created_at'] ?? '')],
            ['s.name']
        ));

        $r->register($a(
            'transmission',
            'e.id AS id, e.event_type, e.summary, e.event_time, e.created_at',
            'FROM sse_intel_events e', 'e.id', 'e.tenant_id',
            static function (array $row): array {
                $s = trim((string) ($row['summary'] ?? ''));

                return ['ref' => 'TRM-' . str_pad((string) (int) $row['id'], 5, '0', STR_PAD_LEFT), 'title' => $s !== '' ? mb_substr($s, 0, 80) : (string) ($row['event_type'] ?? 'Transmission'),
                    'summary' => (string) ($row['event_type'] ?? ''), 'href' => 'atak/sse/transmissions/' . (int) $row['id'], 'date' => (string) ($row['event_time'] ?? $row['created_at'] ?? '')];
            },
            ['e.summary', 'e.event_type']
        ));

        $r->register($a(
            'campaign',
            'g.id AS id, g.name, g.type, g.status, g.created_at',
            'FROM sse_campaigns g', 'g.id', 'g.tenant_id',
            static fn (array $row): array => ['ref' => 'CAM-' . str_pad((string) (int) $row['id'], 4, '0', STR_PAD_LEFT), 'title' => trim((string) ($row['name'] ?? '')) ?: 'Campagne',
                'summary' => (string) ($row['type'] ?? ''), 'href' => 'atak/sse/campagnes/' . (int) $row['id'], 'date' => (string) ($row['created_at'] ?? '')],
            ['g.name']
        ));

        return $r;
    }
}
