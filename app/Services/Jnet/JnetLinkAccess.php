<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Core\Gate;
use App\Services\Sse\Links\SseLinkGraph;
use App\Services\Sse\Links\SseLinkSessionViewer;
use App\Repositories\JnetUnitWorkspaceRepository;
use App\Support\JnetSpaceTree;
use App\Support\JnetUi;

/**
 * Le lecteur réel des liens JNET : chaque réponse vient des règles qui existent déjà (arbre ORBAT filtré par
 * confidentialité pour ce lecteur, JnetSpaceService::rights, porte du renseignement, droits du partage de fichiers).
 * Rien n'est décidé ici qui ne le soit déjà ailleurs ; en cas de doute, la réponse est « non vu » et le lien s'affiche
 * « Lien masqué ».
 */
final class JnetLinkAccess implements JnetLinkViewer, JnetSseViewer
{
    private ?SseLinkGraph $sseGraph = null;

    /** @var array<string, array<string, mixed>|null> */
    private array $seen = [];

    /** @var array<int, array<string, mixed>>|null fiches du personnel par identifiant */
    private ?array $people = null;

    private ?JnetUnitFiles $files = null;

    public function __construct(
        private readonly int $tenant,
        private readonly int $user,
        private readonly JnetSpaceService $spaces,
        private readonly JnetUnitWorkspaceRepository $work,
        private readonly JnetUnitSpaceSettings $settings,
    ) {
    }

    public static function forViewer(int $tenantId, int $userId, ?JnetSpaceService $spaces = null): self
    {
        return new self($tenantId, $userId, $spaces ?? new JnetSpaceService(), new JnetUnitWorkspaceRepository(), new JnetUnitSpaceSettings());
    }

    public function tenantId(): int
    {
        return $this->tenant;
    }

    public function userId(): int
    {
        return $this->user;
    }

    public function today(): string
    {
        return date('Y-m-d');
    }

    private function tree(): JnetSpaceTree
    {
        return $this->spaces->tree($this->tenant, $this->user);
    }

    public function see(string $type, string $id): ?array
    {
        $key = $type . ':' . $id;
        if (!array_key_exists($key, $this->seen)) {
            $this->seen[$key] = JnetLinkRules::validId($type, $id) ? $this->resolve($type, $id) : null;
        }

        return $this->seen[$key];
    }

    /** @return array<string, mixed>|null */
    private function resolve(string $type, string $id): ?array
    {
        try {
            return match ($type) {
                'unite' => $this->unit((int) $id),
                'membre' => $this->member((int) $id),
                'operation' => $this->operation((int) $id),
                'page' => $this->page((int) $id),
                'tache' => $this->task((int) $id),
                'echange' => $this->exchange((int) $id),
                'cible' => $this->target($id),
                'document' => $this->document((int) $id),
                'sse' => $this->sse($id),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** Une unité : dans l'arbre de ce lecteur ET espace qu'il peut ouvrir. */
    private function unit(int $id): ?array
    {
        $tree = $this->tree();
        if ($id <= 0 || !$tree->has($id) || !$this->spaces->canRead($this->tenant, $this->user, $id)) {
            return null;
        }
        $node = (array) $tree->node($id);

        return ['type' => 'unite', 'id' => (string) $id, 'title' => (string) $node['label'], 'sub' => JnetUi::echelonLabel((string) ($node['echelon'] ?? '')), 'href' => $this->spaces->spaceUrl($id), 'group' => 'unites', 'space' => $id];
    }

    /** Un membre : la fiche que l'annuaire JNET montre déjà à tout lecteur de la communauté. */
    private function member(int $id): ?array
    {
        if ($this->people === null) {
            $this->people = [];
            foreach ($this->spaces->dashboard()->loadPersonnelCards($this->tenant) as $p) {
                $this->people[(int) ($p['id'] ?? 0)] = $p;
            }
        }
        $p = $this->people[$id] ?? null;
        if ($p === null) {
            return null;
        }
        $name = trim((string) ($p['name'] ?? ''));
        $sub = trim((string) ($p['grade'] ?? '') . ' ' . (string) ($p['function'] ?? ''));

        return ['type' => 'membre', 'id' => (string) $id, 'title' => $name !== '' ? $name : 'Membre', 'sub' => $sub, 'href' => url('jnet/personnel/' . $id), 'group' => 'membres'];
    }

    /** Une opération : celles que JNET liste, et dont l'unité de visibilité est visible de ce lecteur. */
    private function operation(int $id): ?array
    {
        $op = $this->spaces->dashboard()->findOperation($this->tenant, $id);
        if ($op === null) {
            return null;
        }
        $unit = (int) ($op['unit_id'] ?? 0);
        if ($unit > 0 && !$this->tree()->has($unit)) {
            return null;
        }

        return ['type' => 'operation', 'id' => (string) $id, 'title' => (string) $op['title'], 'sub' => (string) ($op['state'] ?? ''), 'href' => url('jnet/operations/' . $id), 'group' => 'autres'];
    }

    /** Une page : l'espace de son unité est lisible de ce lecteur et la rubrique « Pages » n'y est pas masquée. */
    private function page(int $id): ?array
    {
        $p = $this->work->pageById($this->tenant, $id);
        if ($p === null || !$this->unitModuleOpen($p['unit_id'], 'pages')) {
            return null;
        }
        $title = trim($p['title']);

        return ['type' => 'page', 'id' => (string) $id, 'title' => $title !== '' ? $title : 'Page sans titre', 'sub' => (string) ($this->tree()->node($p['unit_id'])['label'] ?? ''), 'href' => url('jnet/u/' . $p['unit_id'] . '?vue=page&p=' . $id . '&direct=1'), 'group' => 'pages', 'space' => $p['unit_id']];
    }

    private function task(int $id): ?array
    {
        $t = $this->work->taskById($this->tenant, $id);
        if ($t === null || !$this->unitModuleOpen($t['unit_id'], 'taches')) {
            return null;
        }

        return ['type' => 'tache', 'id' => (string) $id, 'title' => $t['title'], 'sub' => JnetUnitWorkspaceService::TASK_LABELS[$t['status']] ?? '', 'href' => url('jnet/u/' . $t['unit_id'] . '?vue=taches&direct=1'), 'group' => 'taches', 'space' => $t['unit_id']];
    }

    private function unitModuleOpen(int $unitId, string $module): bool
    {
        if ($unitId <= 0 || !$this->tree()->has($unitId) || !$this->spaces->canRead($this->tenant, $this->user, $unitId)) {
            return false;
        }

        return !in_array($module, (array) ($this->settings->find($this->tenant, $unitId)['hidden_modules'] ?? []), true);
    }

    /**
     * Un échange : mêmes filtres que les écrans (JnetSpaceService::viewerExchange) ; un échange de renseignement demande de
     * plus la porte du renseignement SSE.
     */
    private function exchange(int $id): ?array
    {
        $ex = $this->spaces->viewerExchange($this->tenant, $this->user, $id);
        if ($ex === null || ((string) $ex['kind'] === 'renseignement' && !$this->spaces->dashboard()->mayReadIntelligence())) {
            return null;
        }
        $kind = (string) $ex['kind'];
        $href = url('jnet');
        $tree = $this->tree();
        foreach (array_merge([(int) $ex['from_unit_id']], (array) $ex['targets']) as $unit) {
            $unit = (int) $unit;
            if ($unit > 0 && $tree->has($unit) && $this->spaces->canRead($this->tenant, $this->user, $unit)) {
                $href = url('jnet/u/' . $unit . '?vue=echanges&direct=1') . '#ex-' . $id;
                break;
            }
            if ($unit === JnetSpaceTree::ORG) {
                $href = url('jnet?commun=1') . '#ex-' . $id;
            }
        }

        return ['type' => 'echange', 'id' => (string) $id, 'title' => (string) $ex['title'], 'sub' => JnetSpaceService::KINDS[$kind] ?? 'Information', 'href' => $href, 'group' => JnetLinkRules::groupOf('echange', $kind)];
    }

    /** Une cible : seulement si le lecteur passe les deux portes du bureau SSE (mayReadIntelligence). */
    private function target(string $id): ?array
    {
        $dash = $this->spaces->dashboard();
        if (!$dash->mayReadIntelligence()) {
            return null;
        }
        $t = $dash->findTarget($this->tenant, $id);
        if ($t === null) {
            return null;
        }

        return ['type' => 'cible', 'id' => $id, 'title' => (string) ($t['name'] ?? 'Cible'), 'sub' => (string) ($t['kind'] ?? ''), 'href' => url('jnet/cibles/' . rawurlencode($id)), 'group' => 'cibles'];
    }

    /** Un fichier du partage de fichiers que le lecteur peut ouvrir (droits du partage). */
    private function document(int $id): ?array
    {
        $this->files ??= new JnetUnitFiles();
        $f = $this->files->fileForViewer($this->tenant, $this->user, $id);
        if ($f === null) {
            return null;
        }

        return ['type' => 'document', 'id' => (string) $id, 'title' => $f['title'], 'sub' => $f['photo'] ? 'Photo' : 'Document', 'href' => $f['href'], 'group' => JnetLinkRules::groupOf('document', '', $f['photo'])];
    }

    private function sseGraph(): SseLinkGraph
    {
        return $this->sseGraph ??= new SseLinkGraph(new SseLinkSessionViewer());
    }

    /**
     * Un élément SSE (« person-12 ») tel que CE lecteur le voit avec SES droits SSE : section, habilitation, identités de membres.
     * Illisible, inexistant ou d'une autre communauté : null (indiscernable). Rien du contenu SSE n'est copié ici.
     */
    private function sse(string $id): ?array
    {
        $parts = JnetLinkRules::sseParts($id);
        $viewer = new SseLinkSessionViewer();
        if ($parts === null || $viewer->tenantId() !== $this->tenant) {
            return null;
        }
        $node = $this->sseGraph()->see($parts['type'], $parts['id']);
        if ($node === null) {
            return null;
        }

        return ['type' => 'sse', 'id' => $id, 'title' => (string) $node['title'], 'sub' => $node['type_label'] . ' · ' . $node['ref'], 'href' => (string) $node['href'], 'group' => 'sse',
            'ref' => (string) $node['ref'], 'type_label' => (string) $node['type_label']];
    }

    public function ssePath(string $id): array
    {
        $parts = JnetLinkRules::sseParts($id);
        if ($parts === null) {
            return [];
        }
        try {
            $viewer = new SseLinkSessionViewer();
            if ($viewer->tenantId() !== $this->tenant) {
                return [];
            }
            $g = (new SseLinkGraph($viewer))->resolve($parts['type'], $parts['id'], 3, 40);

            return array_slice($g['chains'] ?? [], 0, 2);
        } catch (\Throwable) {
            return [];
        }
    }

    public function canWriteIn(int $spaceId): bool
    {
        return $spaceId > 0 && $this->tree()->has($spaceId) && $this->spaces->canPost($this->tenant, $this->user, $spaceId);
    }

    public function isChief(int $spaceId): bool
    {
        return $spaceId > 0 && $this->tree()->has($spaceId) && $this->spaces->isSpaceChief($this->tenant, $this->user, $spaceId);
    }

    public function canModerate(int $spaceId): bool
    {
        if ($spaceId <= 0 || !$this->tree()->has($spaceId)) {
            return false;
        }
        $r = $this->spaces->rights($this->tenant, $this->user, $spaceId);

        return $r['chief'] || $r['lead'];
    }

    public function isOrgManager(): bool
    {
        return $this->spaces->isOrgWideWriter() || Gate::getInstance()->allows('organization.orbat.manage');
    }

    public function inOwnUnits(): bool
    {
        return $this->ownUnitIds() !== [];
    }

    public function ownUnitIds(): array
    {
        $tree = $this->tree();

        return array_values(array_filter($this->spaces->viewerUnitIds($this->tenant, $this->user), static fn (int $u): bool => $tree->has($u)));
    }

    public function unitLabel(int $unitId): ?string
    {
        $tree = $this->tree();

        return $unitId > 0 && $tree->has($unitId) ? (string) ($tree->node($unitId)['label'] ?? 'Unité') : null;
    }

    public function related(int $a, int $b): bool
    {
        $tree = $this->tree();

        return $tree->has($a) && $tree->has($b) && ($tree->isWithin($a, $b) || $tree->isWithin($b, $a));
    }

    public function authors(array $userIds): array
    {
        return $this->work->userLabels($this->tenant, $userIds);
    }

    public function search(string $q, array $types, int $spaceId): array
    {
        $q = trim(mb_substr($q, 0, 80));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $types = array_values(array_filter($types, static fn (string $t): bool => isset(JnetLinkRules::TYPES[$t])));
        $types = $types === [] ? array_keys(JnetLinkRules::TYPES) : $types;
        $has = static fn (string $hay): bool => mb_stripos($hay, $q) !== false;
        $out = [];
        $push = function (string $type, string $id) use (&$out): void {
            $s = $this->see($type, $id);
            if ($s !== null && count(array_filter($out, static fn (array $r): bool => $r['type'] === $type)) < 6) {
                $out[] = $s;
            }
        };
        $dash = $this->spaces->dashboard();
        try {
            foreach ($types as $type) {
                switch ($type) {
                    case 'unite':
                        foreach ($this->tree()->unitIds() as $uid) {
                            if ($has((string) ($this->tree()->node($uid)['label'] ?? ''))) {
                                $push('unite', (string) $uid);
                            }
                        }
                        break;
                    case 'membre':
                        foreach ($dash->loadPersonnelCards($this->tenant) as $p) {
                            if ($has((string) ($p['name'] ?? '') . ' ' . (string) ($p['callsign'] ?? ''))) {
                                $push('membre', (string) (int) ($p['id'] ?? 0));
                            }
                        }
                        break;
                    case 'operation':
                        foreach ($dash->loadOperations($this->tenant) as $o) {
                            if ($has((string) ($o['title'] ?? ''))) {
                                $push('operation', (string) (int) $o['id']);
                            }
                        }
                        break;
                    case 'page':
                        foreach ($this->work->searchPages($this->tenant, $q) as $p) {
                            $push('page', (string) $p['id']);
                        }
                        break;
                    case 'tache':
                        foreach ($this->work->searchTasks($this->tenant, $q) as $t) {
                            $push('tache', (string) $t['id']);
                        }
                        break;
                    case 'echange':
                        $n = 0;
                        foreach ($this->spaces->exchangesRepo()->recentForTenant($this->tenant, 200) as $e) {
                            if ($n < 30 && $has((string) $e['title'])) {
                                ++$n;
                                $push('echange', (string) (int) $e['id']);
                            }
                        }
                        break;
                    case 'cible':
                        if ($dash->mayReadIntelligence()) {
                            foreach ($dash->loadTargets($this->tenant) as $t) {
                                if ($has((string) ($t['name'] ?? '') . ' ' . (string) ($t['code'] ?? ''))) {
                                    $push('cible', (string) $t['id']);
                                }
                            }
                        }
                        break;
                    case 'sse':
                        // Titre NEUTRE (référence seule) : c'est ce qui est écrit dans le contenu JNET ; le nom reste dans « sub », vu du rédacteur seulement.
                        $sseViewer = new SseLinkSessionViewer();
                        if ($sseViewer->tenantId() === $this->tenant) {
                            foreach (['person', 'identity', 'paper', 'check', 'case', 'interest', 'note', 'site', 'document'] as $sseType) {
                                foreach ($this->sseGraph()->search($sseType, $q, 4) as $hit) {
                                    $out[] = ['type' => 'sse', 'id' => $sseType . '-' . $hit['id'], 'title' => (string) $hit['ref'], 'sub' => $hit['type_label'] . ' · ' . $hit['title'], 'href' => '', 'group' => 'sse'];
                                }
                            }
                        }
                        break;
                    case 'document':
                        $this->files ??= new JnetUnitFiles();
                        $units = array_values(array_unique(array_merge($spaceId > 0 ? [$spaceId] : [], $this->ownUnitIds())));
                        foreach ($units as $uid) {
                            $set = $this->files->forUnit($this->tenant, $this->user, $uid);
                            foreach (array_merge($set['photos'], $set['documents']) as $f) {
                                if (($f['source'] ?? '') === 'file' && $has((string) $f['name'])) {
                                    $push('document', (string) (int) $f['id']);
                                }
                            }
                        }
                        break;
                }
            }
        } catch (\Throwable) {
            // Une source indisponible ne vide pas les autres.
        }

        return $out;
    }
}
