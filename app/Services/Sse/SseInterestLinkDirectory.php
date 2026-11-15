<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCaseRepository;
use App\Repositories\SseDocumentRepository;
use App\Repositories\SseFieldNoteRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIntelEventRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;

/**
 * Implémentation réelle du résolveur de liens : s'appuie sur les dépôts existants du portail, toujours dans la communauté de
 * l'acteur. Une identité de membre (ou de couverture) n'apparaît ni dans la recherche ni à l'affichage tant que
 * SseMemberImageGate ne l'autorise pas ; un dossier au-dessus du plafond d'habilitation est masqué.
 */
final class SseInterestLinkDirectory implements SseInterestLinkResolver
{
    /** Section SSE dont le droit de lecture ouvre chaque type. */
    private const SECTION = [
        'person' => 'fichiers', 'identity' => 'fichiers', 'fiche' => 'terrain',
        'site' => 'dossiers', 'transmission' => 'dossiers', 'document' => 'dossiers', 'case' => 'dossiers',
    ];

    public function __construct(private ?SseMemberImageGate $gate = null)
    {
    }

    public function search(SseInterestActor $actor, string $type, string $query, int $limit = 8): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2 || !$this->allowed($actor, $type)) {
            return [];
        }
        $limit = max(1, min(15, $limit));
        $t = $actor->tenantId;
        $out = [];
        try {
            switch ($type) {
                case 'person':
                    foreach ((new SsePersonRepository())->searchForTenant($t, $query, $limit) as $p) {
                        $out[] = ['id' => (string) (int) $p['id'], 'label' => (string) ($p['display_name'] ?? ('Personne ' . $p['id'])), 'hint' => trim((string) (($p['affiliation'] ?? '') ?: ($p['nationality'] ?? '')))];
                    }
                    break;
                case 'identity':
                    foreach ((new SseIdentityRepository())->list($t, ['q' => $query, 'limit' => $limit * 3]) as $i) {
                        if ($this->identityMasked($t, $i)) {
                            continue;
                        }
                        $out[] = ['id' => (string) (int) $i['id'], 'label' => (string) $i['display_name'], 'hint' => (string) ($i['ref'] ?? '')];
                    }
                    break;
                case 'site':
                    foreach ((new SseSiteRepository())->searchForTenant($t, $query, $limit) as $s) {
                        $out[] = ['id' => (string) (int) $s['id'], 'label' => (string) ($s['name'] ?? ''), 'hint' => (string) ($s['reference_code'] ?? '')];
                    }
                    break;
                case 'transmission':
                    foreach ((new SseIntelEventRepository())->listForTenant($t, ['q' => $query, 'limit' => $limit]) as $e) {
                        $out[] = ['id' => (string) (int) $e['id'], 'label' => mb_substr((string) ($e['summary'] ?? 'Transmission'), 0, 120), 'hint' => (string) ($e['event_type'] ?? '')];
                    }
                    break;
                case 'fiche':
                    foreach (array_slice((new SseFieldNoteRepository())->listForTenant($t, ['q' => $query, 'limit' => $limit]), 0, $limit) as $n) {
                        $out[] = ['id' => (string) (int) $n['id'], 'label' => (string) (($n['title'] ?? '') ?: mb_substr((string) ($n['body'] ?? 'Fiche'), 0, 80)), 'hint' => (string) ($n['reference_code'] ?? '')];
                    }
                    break;
                case 'document':
                    foreach (array_slice((new SseDocumentRepository())->listForTenant($t, ['q' => $query]), 0, $limit) as $d) {
                        $out[] = ['id' => (string) (int) $d['id'], 'label' => (string) ($d['title'] ?? ''), 'hint' => (string) ($d['reference_code'] ?? '')];
                    }
                    break;
                case 'case':
                    foreach (array_slice((new SseCaseRepository())->listForTenant($t, null, ['q' => $query]), 0, $limit * 2) as $c) {
                        if (!empty($c['is_folder']) || $this->caseMasked($actor, $c)) {
                            continue;
                        }
                        $out[] = ['id' => (string) (int) $c['id'], 'label' => (string) ($c['title'] ?? ''), 'hint' => (string) ($c['reference_code'] ?? '')];
                    }
                    break;
            }
        } catch (\Throwable) {
            return [];
        }

        return array_slice($out, 0, $limit);
    }

    public function describe(SseInterestActor $actor, string $type, string $id): ?array
    {
        $n = (int) $id;
        if ($n < 1 || !isset(self::SECTION[$type])) {
            return null;
        }
        $t = $actor->tenantId;
        $masked = ['label' => 'Élément masqué', 'hint' => 'Vous n’avez pas le droit de le consulter.', 'href' => null, 'masked' => true];
        if (!$this->allowed($actor, $type)) {
            return $masked;
        }
        try {
            switch ($type) {
                case 'person':
                    $p = (new SsePersonRepository())->findById($n, $t);

                    return $p === null ? null : ['label' => (string) ($p['display_name'] ?? ('Personne ' . $n)), 'hint' => 'Personne', 'href' => url('atak/sse/identites/' . $n), 'masked' => false];
                case 'identity':
                    $i = (new SseIdentityRepository())->find($n, $t);
                    if ($i === null) {
                        return null;
                    }
                    if ($this->identityMasked($t, $i)) {
                        return ['label' => 'Identité protégée', 'hint' => 'Identité de membre ou de couverture : accès réservé.', 'href' => null, 'masked' => true];
                    }

                    return ['label' => (string) $i['display_name'], 'hint' => (string) ($i['ref'] ?? 'Identité'), 'href' => url('atak/sse/papiers/identites/' . $n), 'masked' => false];
                case 'site':
                    $s = (new SseSiteRepository())->findById($n, $t);

                    return $s === null ? null : ['label' => (string) ($s['name'] ?? ''), 'hint' => (string) ($s['reference_code'] ?? 'Site'), 'href' => url('atak/sse/sites/' . $n), 'masked' => false];
                case 'transmission':
                    $e = (new SseIntelEventRepository())->findById($t, $n);

                    return $e === null ? null : ['label' => mb_substr((string) ($e['summary'] ?? 'Transmission'), 0, 120), 'hint' => 'Transmission', 'href' => url('atak/sse/transmissions/' . $n), 'masked' => false];
                case 'fiche':
                    $f = (new SseFieldNoteRepository())->findForTenant($t, $n);

                    return $f === null ? null : ['label' => (string) (($f['title'] ?? '') ?: mb_substr((string) ($f['body'] ?? 'Fiche'), 0, 80)), 'hint' => (string) ($f['reference_code'] ?? 'Fiche'), 'href' => url('atak/sse/fiches/' . $n), 'masked' => false];
                case 'document':
                    $d = (new SseDocumentRepository())->findById($n, $t);

                    return $d === null ? null : ['label' => (string) ($d['title'] ?? ''), 'hint' => (string) ($d['reference_code'] ?? 'Document'), 'href' => url('atak/sse/documents/' . $n), 'masked' => false];
                case 'case':
                    $c = (new SseCaseRepository())->findById($n, $t);
                    if ($c === null) {
                        return null;
                    }
                    if ($this->caseMasked($actor, $c)) {
                        return $masked;
                    }

                    return ['label' => (string) ($c['title'] ?? ''), 'hint' => (string) ($c['reference_code'] ?? 'Dossier'), 'href' => url('atak/sse/dossiers/' . $n), 'masked' => false];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function allowed(SseInterestActor $actor, string $type): bool
    {
        $section = self::SECTION[$type] ?? null;

        return $section !== null && $actor->canReadSection($section);
    }

    /** @param array<string, mixed> $identity */
    private function identityMasked(int $tenantId, array $identity): bool
    {
        $d = ($this->gate ??= SseMemberImageGate::forSession())->forIdentity($tenantId, $identity);

        return $d['confidential'] && !$d['allowed'];
    }

    /** @param array<string, mixed> $case */
    private function caseMasked(SseInterestActor $actor, array $case): bool
    {
        $class = (string) ($case['classification'] ?? '');

        return isset(SseRedactionService::LEVELS[$class]) && SseRedactionService::levelRank($class) > $actor->maxRank();
    }
}
