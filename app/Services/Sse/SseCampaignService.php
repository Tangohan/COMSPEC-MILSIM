<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCampaignRepository;
use App\Support\SqfArrayText;

/**
 * Campagnes SSE dans Athena : lecture du texte exporté par le jeu, fiche lisible,
 * duplication pour un drill, remise à zéro, partage entre communautés.
 *
 * Le texte stocké reste toujours celui que le jeu relit (["COMSPEC_SSE_CAMPAIGN", 1, paquet]) :
 * ce qu'Athena modifie repart en jeu par « Campagne : récupérer depuis Athena » ou par
 * copier-coller dans « Campagne : importer ».
 *
 * Identifiants globaux : la graine biométrique d'une campagne est unique (le jeu la tire au hasard
 * à la création, Athena signale toute graine reprise par une campagne qui n'en est pas une copie).
 * Un personnage récurrent est donc identifié partout par graine + clé : deux communautés ne peuvent
 * pas produire la même biométrie pour deux personnages différents, et une campagne copiée depuis
 * le catalogue garde la biométrie de ses personnages.
 */
final class SseCampaignService
{
    public const HEADER = 'COMSPEC_SSE_CAMPAIGN';

    public const TYPES = [
        'permanente' => 'Permanente',
        'recurrente' => 'Récurrente (bootcamp)',
        'temporaire' => 'Temporaire (drill SOT)',
    ];

    public const TYPE_HINTS = [
        'permanente' => 'La vraie campagne : tout est gardé d’une mission à l’autre.',
        'recurrente' => 'Mêmes personnages et organisations à chaque drill ; tout redevient inconnu au chargement.',
        'temporaire' => 'Bac à sable jetable : rien n’est sauvegardé en jeu, vidé à la fin.',
    ];

    public const SHARE_SCOPES = [
        'structure' => 'Structure seule (personnages, organisations, rôles ; rien de découvert, aucune pièce)',
        'complete' => 'Campagne complète (avec ce que les joueurs ont découvert et le registre des scellés)',
    ];

    public const KINDS = [
        'reseau' => 'Réseau', 'cellule' => 'Cellule', 'groupe' => 'Groupe armé', 'milice' => 'Milice',
        'criminel' => 'Réseau criminel', 'clan' => 'Clan, famille', 'entreprise' => 'Entreprise écran',
        'service' => 'Service de renseignement', 'soutien' => 'Réseau de soutien',
    ];

    public const ROLES = [
        'chef' => ['Chef', 0], 'adjoint' => ['Adjoint', 1], 'chefcellule' => ['Chef de cellule', 2],
        'financier' => ['Financier', 3], 'logisticien' => ['Logisticien', 3], 'artificier' => ['Artificier', 3],
        'recruteur' => ['Recruteur', 3], 'propagande' => ['Propagandiste', 3], 'transmissions' => ['Transmissions', 3],
        'courrier' => ['Courrier', 4], 'guetteur' => ['Guetteur', 4], 'combattant' => ['Combattant', 4],
        'membre' => ['Membre', 4], 'facilitateur' => ['Facilitateur', 5], 'sympathisant' => ['Sympathisant', 5],
    ];

    public const THREATS = ['faible' => 'Faible', 'moyenne' => 'Moyenne', 'elevee' => 'Élevée', 'critique' => 'Critique'];

    public const KNOWN = ['Non identifié', 'Soupçonné', 'Confirmé'];

    /** Taille maximale d'un texte de campagne (le jeu en produit rarement plus de quelques centaines de Ko). */
    public const MAX_TEXT_BYTES = 4_000_000;

    public function __construct(private ?SseCampaignRepository $repo = null)
    {
    }

    /** Base ouverte au premier besoin : la lecture d'un texte de campagne n'en a pas besoin. */
    public function repository(): SseCampaignRepository
    {
        return $this->repo ??= new SseCampaignRepository();
    }

    public static function slug(string $s): string
    {
        $s = trim($s);
        $parts = preg_split("/[ '\\/\\\\.,:;]+/u", $s) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));

        return mb_substr(mb_strtoupper(implode('-', $parts)), 0, 64);
    }

    /**
     * Texte exporté par le jeu → campagne dépaquetée (tableau associatif marqué).
     *
     * @return array<string, mixed>
     */
    public function parse(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Collez le texte exporté par le module Zeus « Campagne : exporter ».');
        }
        if (strlen($text) > self::MAX_TEXT_BYTES) {
            throw new \InvalidArgumentException('Texte de campagne trop volumineux.');
        }
        $arr = SqfArrayText::decode($text);
        if (!is_array($arr) || ($arr[0] ?? null) !== self::HEADER || !isset($arr[2])) {
            throw new \InvalidArgumentException('Ce texte n’est pas une campagne COMSPEC SSE.');
        }
        $c = SqfArrayText::unpack($arr[2]);
        if (!SqfArrayText::isMap($c)) {
            throw new \InvalidArgumentException('Campagne illisible.');
        }

        return $c;
    }

    /** @param array<string, mixed> $c campagne dépaquetée */
    public function toText(array $c): string
    {
        return SqfArrayText::encode([self::HEADER, 1, SqfArrayText::pack($c)]);
    }

    /**
     * Colonnes calculées depuis la campagne (comptes, graine, date du jeu).
     *
     * @param array<string, mixed> $c
     * @return array<string, mixed>
     */
    public function columns(array $c, string $text): array
    {
        $type = strtolower((string) ($c['type'] ?? 'permanente'));
        if (!isset(self::TYPES[$type])) {
            $type = 'permanente';
        }

        return [
            'name' => mb_substr((string) ($c['name'] ?? $c['id'] ?? ''), 0, 160),
            'type' => $type,
            'seed' => mb_substr((string) ($c['seed'] ?? ''), 0, 120),
            'payload' => $text,
            'payload_hash' => hash('sha256', $text),
            'missions' => max(0, (int) ($c['missions'] ?? 0)),
            'persons_count' => count(SqfArrayText::entries($c['roster'] ?? null) + SqfArrayText::entries($c['members'] ?? null)),
            'orgs_count' => count(SqfArrayText::entries($c['orgs'] ?? null)),
            'pieces_count' => count(SqfArrayText::entries($c['custody'] ?? null)),
            'game_updated' => mb_substr((string) ($c['updated'] ?? ''), 0, 40),
        ];
    }

    /**
     * Enregistre (ou met à jour) une campagne à partir du texte du jeu.
     *
     * @return array{id:int, created:bool, key:string}
     */
    public function ingest(int $tenantId, string $text, string $keyOverride, string $origin, string $actor): array
    {
        $c = $this->parse($text);
        $key = self::slug($keyOverride !== '' ? $keyOverride : (string) ($c['id'] ?? ''));
        if ($key === '') {
            throw new \InvalidArgumentException('Identifiant de campagne manquant.');
        }
        if ($keyOverride !== '') {
            $c['id'] = $key;
            $text = $this->toText($c);
        }
        $cols = $this->columns($c, $text);
        $existing = $this->repository()->findByKey($tenantId, $key);
        if ($existing !== null) {
            if (($existing['payload_hash'] ?? '') === $cols['payload_hash']) {
                return ['id' => (int) $existing['id'], 'created' => false, 'key' => $key];
            }
            $this->repository()->update($tenantId, (int) $existing['id'], $cols + ['origin' => $origin]);
            $this->repository()->log($tenantId, (int) $existing['id'], $actor, 'mise à jour', $this->originLabel($origin) . ', ' . $cols['missions'] . ' mission(s)');

            return ['id' => (int) $existing['id'], 'created' => false, 'key' => $key];
        }
        $id = $this->repository()->insert($tenantId, $cols + ['campaign_key' => $key, 'origin' => $origin]);
        $this->repository()->log($tenantId, $id, $actor, 'création', $this->originLabel($origin));

        return ['id' => $id, 'created' => true, 'key' => $key];
    }

    public function originLabel(string $origin): string
    {
        return match ($origin) {
            'jeu' => 'envoyée depuis le jeu',
            'import' => 'collée depuis un export Zeus',
            'partage' => 'copiée depuis le catalogue partagé',
            'athena' => 'modifiée dans Athena',
            default => $origin,
        };
    }

    /**
     * Fiche lisible d'une campagne.
     *
     * @param array<string, mixed> $row ligne sse_campaigns
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        try {
            $c = $this->parse((string) ($row['payload'] ?? ''));
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage(), 'orgs' => [], 'people' => [], 'log' => [], 'pieces' => [], 'stats' => []];
        }
        $seed = (string) ($c['seed'] ?? $row['seed'] ?? '');
        $roster = SqfArrayText::entries($c['roster'] ?? null);
        $members = SqfArrayText::entries($c['members'] ?? null);
        $seen = SqfArrayText::entries($c['seen'] ?? null);
        $bio = SqfArrayText::entries($c['bio'] ?? null);

        $people = [];
        foreach (array_unique(array_merge(array_map('strval', array_keys($roster)), array_map('strval', array_keys($members)))) as $key) {
            $r = SqfArrayText::entries($roster[$key] ?? null);
            $m = SqfArrayText::entries($members[$key] ?? null);
            $role = strtolower((string) ($m['role'] ?? ''));
            $people[$key] = [
                'key' => $key,
                'global_id' => $this->personGlobalId($seed, $key),
                'name' => (string) (($r['name'] ?? '') !== '' ? $r['name'] : ($m['name'] ?? '')),
                'alias' => (string) (($m['alias'] ?? '') !== '' ? $m['alias'] : ($r['alias'] ?? '')),
                'nationality' => (string) ($r['nationality'] ?? ''),
                'status' => (string) ($r['status'] ?? ''),
                'last_seen' => (string) ($r['lastSeen'] ?? ''),
                'org' => (string) ($m['org'] ?? ''),
                'role' => $role,
                'role_label' => self::ROLES[$role][0] ?? ($role !== '' ? ucfirst($role) : ''),
                'rank' => self::ROLES[$role][1] ?? 4,
                'known' => (int) ($m['known'] ?? 0),
                'how' => (string) ($m['how'] ?? ''),
                'when' => (string) ($m['when'] ?? ''),
                'seen' => array_key_exists($key, $seen),
                'encounters' => is_array($bio[$key] ?? null) ? count(SqfArrayText::entries($bio[$key])) : 0,
                'recurring' => $r !== [],
            ];
        }

        $orgs = [];
        foreach (SqfArrayText::entries($c['orgs'] ?? null) as $id => $o) {
            $o = SqfArrayText::entries($o);
            $kind = strtolower((string) ($o['kind'] ?? ''));
            $threat = strtolower((string) ($o['threat'] ?? ''));
            $orgs[(string) $id] = [
                'id' => (string) $id,
                'name' => (string) ($o['name'] ?? $id),
                'kind' => $kind,
                'kind_label' => self::KINDS[$kind] ?? ucfirst($kind),
                'parent' => (string) ($o['parent'] ?? ''),
                'threat' => $threat,
                'threat_label' => self::THREATS[$threat] ?? ucfirst($threat),
                'desc' => (string) ($o['desc'] ?? ''),
                'known' => (int) ($o['known'] ?? 0),
                'how' => (string) ($o['how'] ?? ''),
                'members' => [],
                'children' => [],
            ];
        }
        foreach ($people as $p) {
            if ($p['org'] !== '' && isset($orgs[$p['org']])) {
                $orgs[$p['org']]['members'][] = $p;
            }
        }
        foreach ($orgs as $id => &$o) {
            usort($o['members'], static fn (array $a, array $b): int => [$a['rank'], $a['name']] <=> [$b['rank'], $b['name']]);
            if ($o['parent'] !== '' && isset($orgs[$o['parent']]) && $o['parent'] !== $id) {
                $orgs[$o['parent']]['children'][] = $id;
            }
        }
        unset($o);
        $roots = array_values(array_filter(array_keys($orgs), static fn (string $id): bool => $orgs[$id]['parent'] === '' || !isset($orgs[$orgs[$id]['parent']])));

        $pieces = [];
        foreach (SqfArrayText::entries($c['custody'] ?? null) as $id => $p) {
            $p = SqfArrayText::entries($p);
            $events = is_array($p['events'] ?? null) ? $p['events'] : [];
            $pieces[] = [
                'id' => (string) $id,
                'seal' => (string) ($p['seal'] ?? ''),
                'label' => (string) ($p['label'] ?? ''),
                'desc' => (string) ($p['desc'] ?? ''),
                'state' => (string) ($p['state'] ?? ''),
                'holder' => (string) ($p['holder'] ?? ''),
                'tampered' => !empty($p['tampered']),
                'archived' => !empty($p['archived']),
                'events' => array_map(static fn ($e): array => is_array($e) ? [
                    'dtg' => (string) ($e[0] ?? ''), 'action' => (string) ($e[1] ?? ''), 'who' => (string) ($e[2] ?? ''),
                    'grid' => (string) ($e[4] ?? ''), 'remark' => (string) ($e[5] ?? ''),
                ] : [], $events),
            ];
        }
        usort($pieces, static fn (array $a, array $b): int => strnatcmp($a['id'], $b['id']));

        $log = [];
        foreach (is_array($c['log'] ?? null) ? $c['log'] : [] as $e) {
            if (is_array($e)) {
                $log[] = ['date' => (string) ($e[0] ?? ''), 'mission' => (string) ($e[1] ?? ''), 'world' => (string) ($e[2] ?? ''), 'why' => (string) ($e[3] ?? ''), 'summary' => (string) ($e[4] ?? '')];
            }
        }

        $known = count(array_filter($people, static fn (array $p): bool => $p['known'] >= 1));

        return [
            'error' => null,
            'campaign' => $c,
            'seed' => $seed,
            'created' => (string) ($c['created'] ?? ''),
            'updated' => (string) ($c['updated'] ?? ''),
            'copy_from' => (string) ($c['copyFrom'] ?? ''),
            'people' => array_values($people),
            'orgs' => $orgs,
            'org_roots' => $roots,
            'pieces' => array_reverse($pieces),
            'log' => array_reverse($log),
            'stats' => [
                'people' => count($people),
                'recurring' => count($roster),
                'known' => $known,
                'orgs' => count($orgs),
                'orgs_known' => count(array_filter($orgs, static fn (array $o): bool => $o['known'] >= 1)),
                'pieces' => count($pieces),
                'tampered' => count(array_filter($pieces, static fn (array $p): bool => $p['tampered'])),
                'seen' => count($seen),
            ],
        ];
    }

    /** Identifiant global d'un personnage : le même partout où cette campagne est jouée ou copiée. */
    public function personGlobalId(string $seed, string $key): string
    {
        return strtoupper(substr(hash('sha256', $seed . '|' . $key), 0, 12));
    }

    /** Identifiant global d'une campagne : communauté + clé. */
    public static function campaignGlobalId(array $row, ?array $tenant): string
    {
        $code = trim((string) ($tenant['community_code'] ?? ''));
        if ($code === '') {
            $code = trim((string) ($tenant['slug'] ?? ''));
        }
        if ($code === '') {
            $code = 'C' . (int) ($row['tenant_id'] ?? 0);
        }

        return strtoupper($code) . ':' . (string) ($row['campaign_key'] ?? '');
    }

    /**
     * Ce que les joueurs ont découvert disparaît : connaissance, rencontres, pièces, relevés.
     *
     * @param array<string, mixed> $c
     * @return array<string, mixed>
     */
    public function withoutDiscoveries(array $c): array
    {
        foreach (['members', 'orgs'] as $section) {
            if (!SqfArrayText::isMap($c[$section] ?? null)) {
                continue;
            }
            foreach (SqfArrayText::entries($c[$section]) as $k => $v) {
                if (SqfArrayText::isMap($v)) {
                    $v['known'] = 0;
                    $v['how'] = '';
                    if ($section === 'members') {
                        $v['when'] = '';
                        $v['netId'] = '';
                    }
                    $c[$section][$k] = $v;
                }
            }
        }
        foreach (['seen', 'bio', 'custody'] as $section) {
            unset($c[$section]);
        }
        foreach (['custodyN', 'sealN'] as $counter) {
            unset($c[$counter]);
        }

        return $c;
    }

    /**
     * Copie d'une campagne : même graine (mêmes personnages, même biométrie), nouvel identifiant.
     *
     * @param array<string, mixed> $row
     * @return array{id:int, key:string}
     */
    public function duplicate(int $tenantId, array $row, string $newKey, string $newType, string $newName, string $actor): array
    {
        $c = $this->parse((string) $row['payload']);
        $key = self::slug($newKey);
        if ($key === '') {
            throw new \InvalidArgumentException('Indiquez un identifiant pour la copie.');
        }
        if ($this->repository()->keyExists($tenantId, $key)) {
            throw new \InvalidArgumentException('Une campagne porte déjà l’identifiant ' . $key . '.');
        }
        if (!isset(self::TYPES[$newType])) {
            $newType = 'temporaire';
        }
        $c = $this->withoutDiscoveries($c);
        $c['id'] = $key;
        $c['type'] = $newType;
        $c['name'] = $newName !== '' ? $newName : ((string) ($c['name'] ?? $key)) . ' (copie)';
        $c['copyFrom'] = (string) $row['campaign_key'];
        $c['missions'] = 0;
        $c['log'] = [];
        $text = $this->toText($c);
        $id = $this->repository()->insert($tenantId, $this->columns($c, $text) + ['campaign_key' => $key, 'origin' => 'athena']);
        $this->repository()->log($tenantId, $id, $actor, 'création', 'copie de ' . $row['campaign_key'] . ' (' . self::TYPES[$newType] . ')');
        $this->repository()->log($tenantId, (int) $row['id'], $actor, 'copie', 'copiée sous ' . $key);

        return ['id' => $id, 'key' => $key];
    }

    /** @param array<string, mixed> $row */
    public function resetDiscoveries(int $tenantId, array $row, string $actor): void
    {
        $c = $this->withoutDiscoveries($this->parse((string) $row['payload']));
        $text = $this->toText($c);
        $this->repository()->update($tenantId, (int) $row['id'], $this->columns($c, $text) + ['origin' => 'athena']);
        $this->repository()->log($tenantId, (int) $row['id'], $actor, 'remise à zéro', 'plus rien de découvert, registre vidé');
    }

    /** @param array<string, mixed> $row */
    public function edit(int $tenantId, array $row, string $name, string $type, string $actor): void
    {
        $c = $this->parse((string) $row['payload']);
        $changes = [];
        if ($name !== '' && $name !== (string) ($c['name'] ?? '')) {
            $c['name'] = $name;
            $changes[] = 'nom « ' . $name . ' »';
        }
        if (isset(self::TYPES[$type]) && $type !== (string) ($c['type'] ?? '')) {
            $c['type'] = $type;
            $changes[] = 'type ' . self::TYPES[$type];
        }
        if ($changes === []) {
            return;
        }
        $text = $this->toText($c);
        $this->repository()->update($tenantId, (int) $row['id'], $this->columns($c, $text) + ['origin' => 'athena']);
        $this->repository()->log($tenantId, (int) $row['id'], $actor, 'modification', implode(', ', $changes));
    }

    /**
     * Texte publié dans le catalogue, selon ce que la communauté accepte de partager.
     *
     * @param array<string, mixed> $row
     */
    public function sharedText(array $row): string
    {
        $c = $this->parse((string) $row['payload']);
        if (($row['share_scope'] ?? 'structure') !== 'complete') {
            $c = $this->withoutDiscoveries($c);
        }

        return $this->toText($c);
    }

    /**
     * Copie dans sa communauté une campagne du catalogue (ou la met à jour si déjà copiée).
     *
     * @param array<string, mixed> $source ligne partagée
     * @return array{id:int, key:string, updated:bool}
     */
    public function importShared(int $tenantId, array $source, string $actor, ?string $sourceLabel = null): array
    {
        $text = $this->sharedText($source);
        $c = $this->parse($text);
        foreach ($this->repository()->listForTenant($tenantId) as $mine) {
            if ((int) ($mine['source_campaign_id'] ?? 0) === (int) $source['id']) {
                $c['id'] = (string) $mine['campaign_key'];
                $text = $this->toText($c);
                $this->repository()->update($tenantId, (int) $mine['id'], $this->columns($c, $text) + ['source_hash' => (string) $source['payload_hash'], 'origin' => 'partage']);
                $this->repository()->log($tenantId, (int) $mine['id'], $actor, 'mise à jour', 'depuis le catalogue' . ($sourceLabel ? ' (' . $sourceLabel . ')' : ''));

                return ['id' => (int) $mine['id'], 'key' => (string) $mine['campaign_key'], 'updated' => true];
            }
        }
        $base = (string) $source['campaign_key'];
        $key = $base;
        for ($n = 2; $this->repository()->keyExists($tenantId, $key); $n++) {
            $key = mb_substr($base, 0, 58) . '-' . $n;
        }
        $c['id'] = $key;
        $text = $this->toText($c);
        $id = $this->repository()->insert($tenantId, $this->columns($c, $text) + [
            'campaign_key' => $key,
            'origin' => 'partage',
            'source_campaign_id' => (int) $source['id'],
            'source_tenant_id' => (int) $source['tenant_id'],
            'source_hash' => (string) $source['payload_hash'],
        ]);
        $this->repository()->log($tenantId, $id, $actor, 'création', 'copiée depuis le catalogue' . ($sourceLabel ? ' (' . $sourceLabel . ')' : ''));
        $this->repository()->log((int) $source['tenant_id'], (int) $source['id'], $actor, 'copiée', 'par une autre communauté');

        return ['id' => $id, 'key' => $key, 'updated' => false];
    }
}
