<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseIdentityEventRepository;
use App\Support\SseIdentityCatalog;
use App\Support\SseWantedCatalog as C;

/**
 * Chronologie UNIQUE d'une identité fictive : les événements propres (sse_identity_events, append-only) et ce qui était déjà
 * tracé ailleurs : création de la fiche, papiers préparés, contrôles d'identité, consultations des fichiers, photos versées,
 * mentions, actions du journal d'audit, reprises Athena/jeu champ par champ (ancien puis nouveau).
 *
 * La lecture ne montre jamais ce que le lecteur n'a pas le droit de voir : mêmes droits que la fiche. Les lignes de vérité de
 * jeu (nature de l'identité, rattachement, reprises avec le jeu) exigent l'habilitation Secret (S) ; un papier ou un
 * contrôle d'une identité d'emprunt ou falsifiée est masqué sans cette habilitation. Chaque source manquante (étape de base
 * de données pas encore passée) est ignorée. Cette classe ne fait que lire.
 */
final class SseIdentityTimelineService
{
    private const SECRET_KINDS = ['cover', 'forged'];

    private Database $db;

    public function __construct(?Database $db = null, private ?SseIdentityEventRepository $events = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->events ??= new SseIdentityEventRepository($this->db);
    }

    /**
     * @param array<string, mixed> $identity ligne d'identité (SseIdentityRepository::find)
     * @param string $filter clé de C::EVENT_FILTERS, ou '' pour tout
     * @return list<array{key: string, type: string, filter: string, summary: string, details: list<array{label: string, old: ?string, new: ?string}>, actor: string, at: string, source: string, href: string}>
     */
    public function build(int $tenantId, array $identity, SseWantedAccess $access, string $lang = 'fr', string $filter = '', int $limit = 200): array
    {
        $identityId = (int) ($identity['id'] ?? 0);
        if ($tenantId < 1 || $identityId < 1) {
            return [];
        }
        // L'identité doit appartenir à la communauté demandée (la ligne hydratée ne porte pas la communauté).
        try {
            if ($this->db->fetchOne('SELECT id FROM sse_identities WHERE id = :i AND tenant_id = :t', ['i' => $identityId, 't' => $tenantId]) === null) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }
        $truth = $access->canSeeTruth();
        $secretIdentity = in_array((string) ($identity['kind'] ?? ''), self::SECRET_KINDS, true) && !$truth;
        $lang = C::lang($lang);
        $wanted = isset(C::EVENT_FILTERS[$filter]) ? C::EVENT_FILTERS[$filter][2] : null;
        $rows = [];

        // Événements propres (jamais effacés).
        $rows = array_merge($rows, $this->safe(fn (): array => $this->nativeEvents($tenantId, $identityId, $access, $lang)));
        $hasCreated = false;
        foreach ($rows as $r) {
            if ($r['type'] === 'identity.created') {
                $hasCreated = true;
            }
        }
        if (!$hasCreated) {
            $rows[] = $this->row('identity.created', 'row-' . $identityId, $this->tr('Identité créée.', 'Identity created.', $lang), [], '', (string) ($identity['created_at'] ?? ''), 'web', '');
        }
        // Tracés ailleurs.
        $kind = (string) ($identity['kind'] ?? 'civil');
        $rows = array_merge($rows, $this->safe(fn (): array => $this->documents($tenantId, $identityId, $truth, $lang, $kind)));
        $rows = array_merge($rows, $this->safe(fn (): array => $this->checks($tenantId, $identityId, $truth, $secretIdentity, $lang, $kind)));
        $rows = array_merge($rows, $this->safe(fn (): array => $this->queries($tenantId, $identityId, $lang)));
        $rows = array_merge($rows, $this->safe(fn (): array => $this->photos($tenantId, $identityId, $lang)));
        $rows = array_merge($rows, $this->safe(fn (): array => $this->mentions($tenantId, $identityId, $lang)));
        $rows = array_merge($rows, $this->safe(fn (): array => $this->audit($tenantId, $identityId, $lang)));
        if ($truth) {
            $rows = array_merge($rows, $this->safe(fn (): array => $this->syncHistory($tenantId, $identityId, $lang)));
        }

        if ($wanted !== null) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => in_array($r['type'], $wanted, true)));
        }
        // Avis, détections, mentions, photos et consultations des fichiers : section « fichiers », à lire pour les voir.
        if (!$access->canRead()) {
            $filesTypes = ['notice.placed', 'notice.lifted', 'notice.captured', 'notice.expired', 'notice.pdf', 'document.issued', 'detection.raised', 'detection.handled', 'file.mention', 'file.photo', 'query.recorded'];
            $rows = array_values(array_filter($rows, static fn (array $r): bool => !in_array($r['type'], $filesTypes, true)));
        }
        // Une modification de photo déjà notée par un événement propre n'est pas répétée par le journal d'audit.
        $photoTimes = [];
        foreach ($rows as $r) {
            if ($r['type'] === 'identity.photo' && str_starts_with($r['key'], 'e')) {
                $photoTimes[] = (int) strtotime($r['at'] . ' UTC');
            }
        }
        if ($photoTimes !== []) {
            $rows = array_values(array_filter($rows, static function (array $r) use ($photoTimes): bool {
                if ($r['type'] !== 'identity.photo' || !str_starts_with($r['key'], 'a')) {
                    return true;
                }
                $t = (int) strtotime($r['at'] . ' UTC');
                foreach ($photoTimes as $p) {
                    if (abs($p - $t) <= 120) {
                        return false;
                    }
                }

                return true;
            }));
        }
        usort($rows, static fn (array $a, array $b): int => [$b['at'], $b['key']] <=> [$a['at'], $a['key']]);

        return array_slice($rows, 0, max(1, min(500, $limit)));
    }

    /** @return list<array<string, mixed>> */
    private function nativeEvents(int $tenantId, int $identityId, SseWantedAccess $access, string $lang): array
    {
        $out = [];
        foreach ($this->events->listForIdentity($tenantId, $identityId, [], $access->timelineSections(), 300) as $e) {
            $d = $e['details'];
            $details = [];
            foreach (is_array($d['changes'] ?? null) ? $d['changes'] : [] as $c) {
                if (is_array($c) && isset($c['label'])) {
                    $meta = SseIdentityEventRepository::TRACKED_FIELDS[(string) ($c['field'] ?? '')] ?? null;
                    if (($meta === null || $meta[2]) && !$access->canSeeTruth()) {
                        continue;
                    }
                    $label = $meta !== null ? (string) $meta[$lang === 'en' ? 1 : 0] : (string) $c['label'];
                    $details[] = ['label' => $label, 'old' => isset($c['old']) ? (string) $c['old'] : null, 'new' => isset($c['new']) ? (string) $c['new'] : null];
                }
            }
            $out[] = $this->row((string) $e['type'], 'e' . $e['id'], $this->describeEvent($e, $lang), $details, (string) ($e['actor_label'] ?? ''), (string) $e['at'], (string) $e['source'], '');
        }

        return $out;
    }

    /**
     * Résumé localisé d'un événement propre : reconstruit depuis le type et les détails (le texte enregistré ne sert que de repli).
     *
     * @param array<string, mixed> $e
     */
    private function describeEvent(array $e, string $lang): string
    {
        $d = is_array($e['details'] ?? null) ? $e['details'] : [];
        $ref = (string) ($d['ref'] ?? '');
        $typeLabel = static fn (string $k): string => C::typeLabel($k, [], $lang);

        return match ((string) $e['type']) {
            'notice.placed' => $this->tr(
                sprintf('Avis de recherche %s placé : %s, menace %s.', $ref, $typeLabel((string) ($d['type'] ?? '')), mb_strtolower(C::label(C::THREATS, (string) ($d['threat'] ?? ''), 'fr'))),
                sprintf('Wanted notice %s placed: %s, threat %s.', $ref, $typeLabel((string) ($d['type'] ?? '')), mb_strtolower(C::label(C::THREATS, (string) ($d['threat'] ?? ''), 'en'))),
                $lang
            ),
            'notice.lifted' => $this->tr(sprintf('Avis %s levé. Motif : %s', $ref, (string) ($d['reason'] ?? '')), sprintf('Notice %s lifted. Reason: %s', $ref, (string) ($d['reason'] ?? '')), $lang),
            'notice.captured' => $this->tr(sprintf('Avis %s clos : personne capturée. %s', $ref, (string) ($d['reason'] ?? '')), sprintf('Notice %s closed: subject captured. %s', $ref, (string) ($d['reason'] ?? '')), $lang),
            'notice.expired' => $this->tr(sprintf('Avis %s expiré (date d’expiration atteinte).', $ref), sprintf('Notice %s expired (expiry date reached).', $ref), $lang),
            'notice.pdf' => $this->tr(sprintf('Notice PDF %s générée pour l’avis %s.', strtoupper((string) ($d['lang'] ?? 'fr')), $ref), sprintf('%s PDF notice generated for notice %s.', strtoupper((string) ($d['lang'] ?? 'fr')), $ref), $lang),
            'document.issued' => $this->tr(
                sprintf('Document émis : %s %s (%s).', (string) ($d['model_label_fr'] ?? ''), (string) ($d['doc_ref'] ?? ''), strtoupper((string) ($d['lang'] ?? 'fr'))),
                sprintf('Document issued: %s %s (%s).', (string) ($d['model_label_en'] ?? ''), (string) ($d['doc_ref'] ?? ''), strtoupper((string) ($d['lang'] ?? 'fr'))),
                $lang
            ),
            'detection.raised' => $this->tr(
                sprintf('Cerbère-Détection : %s (avis %s).', C::label(C::DETECTION_TYPES, (string) ($d['type'] ?? ''), 'fr'), $ref),
                sprintf('Cerbère-Détection: %s (notice %s).', C::label(C::DETECTION_TYPES, (string) ($d['type'] ?? ''), 'en'), $ref),
                $lang
            ),
            'detection.handled' => $this->tr(sprintf('Détection traitée : %s.', (string) ($d['status_label_fr'] ?? '')), sprintf('Detection handled: %s.', (string) ($d['status_label_en'] ?? '')), $lang),
            'identity.created' => $this->tr('Identité créée.', 'Identity created.', $lang),
            'identity.updated' => $this->tr('Fiche modifiée.', 'Record edited.', $lang),
            default => (string) $e['summary'],
        };
    }

    /** @return list<array<string, mixed>> */
    private function documents(int $tenantId, int $identityId, bool $truth, string $lang, string $kind): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM sse_id_documents WHERE tenant_id = :t AND identity_id = :i ORDER BY id DESC LIMIT 100', ['t' => $tenantId, 'i' => $identityId]) as $d) {
            if (!$truth && MemberIdentityPapersService::docIsSecret($d + ['identity' => ['kind' => $kind]])) {
                continue;
            }
            $label = SseIdentityCatalog::label(SseIdentityCatalog::DOC_TYPES, (string) ($d['doc_type'] ?? ''), (string) ($d['doc_type'] ?? ''));
            $ref = SseIdentityCatalog::documentRef((int) $d['id']);
            $out[] = $this->row('document.created', 'd' . $d['id'], $this->tr(
                sprintf('Papier préparé : %s (%s).', $label, $ref),
                sprintf('Document prepared: %s (%s).', $label, $ref),
                $lang
            ), [], '', (string) ($d['created_at'] ?? ''), 'web', 'atak/sse/papiers/documents/' . (int) $d['id']);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function checks(int $tenantId, int $identityId, bool $truth, bool $secretIdentity, string $lang, string $kind): array
    {
        if ($secretIdentity) {
            return [];
        }
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM sse_id_checks WHERE tenant_id = :t AND identity_id = :i ORDER BY id DESC LIMIT 100', ['t' => $tenantId, 'i' => $identityId]) as $c) {
            $docId = (int) ($c['document_id'] ?? 0);
            if (!$truth && $docId > 0) {
                $doc = $this->db->fetchOne('SELECT * FROM sse_id_documents WHERE id = :id AND tenant_id = :t', ['id' => $docId, 't' => $tenantId]);
                if ($doc !== null && MemberIdentityPapersService::docIsSecret($doc + ['identity' => ['kind' => $kind]])) {
                    continue;
                }
            }
            $ref = SseIdentityCatalog::checkRef((int) $c['id']);
            $verdict = (string) ($c['verdict'] ?? '');
            $out[] = $this->row('check.recorded', 'c' . $c['id'], $this->tr(
                sprintf('Contrôle d’identité %s : %s.', $ref, SseIdentityCatalog::label(SseIdentityCatalog::VERDICTS, $verdict, 'sans verdict')),
                sprintf('Identity check %s: %s.', $ref, $verdict !== '' ? $verdict : 'no verdict'),
                $lang
            ), [], (string) ($c['controller_callsign'] ?? ''), (string) ($c['created_at'] ?? ''), 'game', 'atak/sse/papiers/controles/' . (int) $c['id']);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function queries(int $tenantId, int $identityId, string $lang): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM sse_file_queries WHERE tenant_id = :t AND identity_id = :i ORDER BY id DESC LIMIT 60', ['t' => $tenantId, 'i' => $identityId]) as $q) {
            $qrn = (string) ($q['qrn'] ?? '');
            $src = (string) ($q['source'] ?? 'web');
            $out[] = $this->row('query.recorded', 'q' . $q['id'], $this->tr(
                sprintf('Consultation des fichiers %s (%s).', $qrn !== '' ? $qrn : '#' . $q['id'], (string) ($q['query_type'] ?? '')),
                sprintf('File query %s (%s).', $qrn !== '' ? $qrn : '#' . $q['id'], (string) ($q['query_type'] ?? '')),
                $lang
            ), [], (string) ($q['operator_label'] ?? ''), (string) ($q['created_at'] ?? ''), $src === 'game' ? 'game' : 'web', '');
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function photos(int $tenantId, int $identityId, string $lang): array
    {
        $out = [];
        foreach ($this->db->fetchAll("SELECT id, kind, source, created_at FROM sse_file_photos WHERE tenant_id = :t AND identity_id = :i AND visibility <> 'hidden' ORDER BY id DESC LIMIT 60", ['t' => $tenantId, 'i' => $identityId]) as $p) {
            $out[] = $this->row('file.photo', 'p' . $p['id'], $this->tr('Photo versée au dossier.', 'Photo added to the file.', $lang), [], '', (string) ($p['created_at'] ?? ''), 'web', '');
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function mentions(int $tenantId, int $identityId, string $lang): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM sse_file_mentions WHERE tenant_id = :t AND identity_id = :i ORDER BY id DESC LIMIT 60', ['t' => $tenantId, 'i' => $identityId]) as $m) {
            $label = (string) (\App\Support\SseFileCatalog::MENTIONS[(string) ($m['mention'] ?? '')] ?? $m['mention']);
            $out[] = $this->row('file.mention', 'm' . $m['id'], $this->tr(sprintf('Mention posée aux fichiers : %s.', $label), sprintf('File mention placed: %s.', (string) ($m['mention'] ?? '')), $lang), [], '', (string) ($m['created_at'] ?? ''), 'web', 'atak/sse/fichiers/mentions');
            if (($m['lifted_at'] ?? null) !== null) {
                $out[] = $this->row('file.mention', 'ml' . $m['id'], $this->tr(sprintf('Mention levée aux fichiers : %s.', $label), sprintf('File mention lifted: %s.', (string) ($m['mention'] ?? '')), $lang), [], '', (string) $m['lifted_at'], 'web', 'atak/sse/fichiers/mentions');
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function audit(int $tenantId, int $identityId, string $lang): array
    {
        $out = [];
        foreach ($this->db->fetchAll("SELECT * FROM sse_audit_log WHERE tenant_id = :t AND object_type = 'sse_identity' AND object_id = :i ORDER BY id DESC LIMIT 60", ['t' => $tenantId, 'i' => $identityId]) as $a) {
            $action = (string) ($a['action'] ?? '');
            $photo = str_contains($action, 'images') || str_contains($action, 'photo');
            $out[] = $this->row($photo ? 'identity.photo' : 'audit', 'a' . $a['id'], $photo
                ? $this->tr('Photo ou signature du titulaire modifiée.', 'Holder photo or signature changed.', $lang)
                : $this->tr('Action enregistrée au journal : ' . $action . '.', 'Action logged in the audit trail: ' . $action . '.', $lang), [], (string) ($a['actor_label'] ?? ''), (string) ($a['created_at'] ?? ''), 'web', '');
        }

        return $out;
    }

    /** Reprises Athena et jeu, champ par champ : la valeur gardée puis la valeur écartée. @return list<array<string, mixed>> */
    private function syncHistory(int $tenantId, int $identityId, string $lang): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM sse_identity_history WHERE tenant_id = :t AND identity_id = :i ORDER BY id DESC LIMIT 60', ['t' => $tenantId, 'i' => $identityId]) as $h) {
            $field = (string) ($h['field'] ?? '');
            $out[] = $this->row('identity.updated', 'h' . $h['id'], $this->tr(
                sprintf('Reprise Athena et jeu : champ « %s ».', $field),
                sprintf('Athena and game sync: field "%s".', $field),
                $lang
            ), [['label' => $field, 'old' => isset($h['lost_value']) ? (string) $h['lost_value'] : null, 'new' => isset($h['kept_value']) ? (string) $h['kept_value'] : null]], '', (string) ($h['created_at'] ?? ''), 'system', '');
        }

        return $out;
    }

    /**
     * @param list<array{label: string, old: ?string, new: ?string}> $details
     * @return array{key: string, type: string, filter: string, summary: string, details: list<array{label: string, old: ?string, new: ?string}>, actor: string, at: string, source: string, href: string}
     */
    private function row(string $type, string $key, string $summary, array $details, string $actor, string $at, string $source, string $href): array
    {
        $filter = '';
        foreach (C::EVENT_FILTERS as $k => $f) {
            if (in_array($type, $f[2], true)) {
                $filter = $k;
                break;
            }
        }

        return ['key' => $key, 'type' => $type, 'filter' => $filter, 'summary' => $summary, 'details' => $details, 'actor' => $actor, 'at' => $at, 'source' => $source, 'href' => $href];
    }

    private function tr(string $fr, string $en, string $lang): string
    {
        return $lang === 'en' ? $en : $fr;
    }

    /**
     * Une source manquante (table pas encore migrée) ou en erreur ne vide pas la chronologie.
     *
     * @param \Closure(): list<array<string, mixed>> $fn
     * @return list<array<string, mixed>>
     */
    private function safe(\Closure $fn): array
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return [];
        }
    }
}
