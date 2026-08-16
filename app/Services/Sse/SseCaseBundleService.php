<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseCaseRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;

/**
 * Import / export de dossiers fictifs complets (pack Athena + pack terrain Arma).
 */
final class SseCaseBundleService
{
    /** @var array<string, mixed> */
    private array $meta;

    public function __construct(
        private ?SseCaseRepository $cases = null,
        private ?SsePersonRepository $persons = null,
        private ?SseSiteRepository $sites = null,
    ) {
        $this->cases ??= new SseCaseRepository();
        $this->persons ??= new SsePersonRepository();
        $this->sites ??= new SseSiteRepository();
        $cfg = base_path('config/sse_case_bundle.php');
        $this->meta = is_file($cfg) ? (array) require $cfg : [
            'format' => 'comspec_sse_case_bundle',
            'format_version' => 1,
            'arma_format' => 'comspec_sse_mission_pack',
        ];
    }

    /**
     * @return array{ok:bool,errors:list<string>,bundle?:array<string,mixed>}
     */
    public function parseJson(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => false, 'errors' => ['Aucun contenu à importer.']];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['ok' => false, 'errors' => ['Le fichier n’est pas un pack valide. Vérifiez qu’il a été généré selon le modèle documenté.']];
        }
        if (!is_array($decoded)) {
            return ['ok' => false, 'errors' => ['Le contenu du pack est invalide.']];
        }

        return $this->normalizeBundle($decoded);
    }

    /**
     * Accepte un pack Athena ou un pack Arma (avec athena_bundle / case).
     *
     * @param array<string, mixed> $raw
     * @return array{ok:bool,errors:list<string>,bundle?:array<string,mixed>}
     */
    public function normalizeBundle(array $raw): array
    {
        $errors = [];
        $format = (string) ($raw['format'] ?? '');
        if ($format === (string) ($this->meta['arma_format'] ?? 'comspec_sse_mission_pack')) {
            if (isset($raw['athena_bundle']) && is_array($raw['athena_bundle'])) {
                $raw = $raw['athena_bundle'];
            } else {
                $raw = $this->missionPackToAthena($raw);
            }
            $format = (string) ($raw['format'] ?? $this->meta['format']);
        }

        $expected = (string) ($this->meta['format'] ?? 'comspec_sse_case_bundle');
        if ($format !== '' && $format !== $expected) {
            $errors[] = 'Ce fichier n’est pas un pack de dossier Athena reconnu.';
        }

        $case = is_array($raw['case'] ?? null) ? $raw['case'] : [];
        $title = trim((string) ($case['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Le pack doit indiquer un titre de dossier.';
        }

        $persons = is_array($raw['persons'] ?? null) ? $raw['persons'] : [];
        $notes = is_array($raw['notes'] ?? null) ? $raw['notes'] : [];
        $evidence = is_array($raw['evidence'] ?? null) ? $raw['evidence'] : [];
        $sites = is_array($raw['sites'] ?? null) ? $raw['sites'] : [];

        if ($persons === [] && $sites === [] && $evidence === [] && $notes === []) {
            $errors[] = 'Le pack est vide : ajoutez au moins une identité, un site, une note ou une pièce.';
        }

        foreach ($persons as $i => $p) {
            if (!is_array($p)) {
                $errors[] = 'Identité #' . ((int) $i + 1) . ' invalide.';
                continue;
            }
            $ln = trim((string) ($p['last_name'] ?? ''));
            $fn = trim((string) ($p['first_name'] ?? ''));
            $al = trim((string) ($p['alias'] ?? ''));
            if ($ln === '' && $fn === '' && $al === '') {
                $errors[] = 'Identité #' . ((int) $i + 1) . ' : indiquez un nom, un prénom ou un alias.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $bundle = [
            'format' => $expected,
            'formatVersion' => (int) ($raw['formatVersion'] ?? $this->meta['format_version'] ?? 1),
            'meta' => is_array($raw['meta'] ?? null) ? $raw['meta'] : [],
            'case' => [
                'reference_code' => trim((string) ($case['reference_code'] ?? '')),
                'title' => $title,
                'summary' => trim((string) ($case['summary'] ?? '')),
                'classification' => SseCaseRepository::normalizeClassification((string) ($case['classification'] ?? 'encadrement')),
                'status' => $this->normalizeCaseStatus((string) ($case['status'] ?? 'ouvert')),
            ],
            'persons' => array_values(array_filter($persons, 'is_array')),
            'notes' => array_values(array_filter($notes, 'is_array')),
            'evidence' => array_values(array_filter($evidence, 'is_array')),
            'sites' => array_values(array_filter($sites, 'is_array')),
        ];

        return ['ok' => true, 'errors' => [], 'bundle' => $bundle];
    }

    /**
     * @param array<string, mixed> $bundle
     * @return array{ok:bool,errors:list<string>,case_id?:int,counts?:array<string,int>}
     */
    public function import(array $bundle, int $tenantId, ?int $userId, string $submitterLabel = 'Bureau'): array
    {
        $norm = $this->normalizeBundle($bundle);
        if (!$norm['ok'] || !isset($norm['bundle'])) {
            return ['ok' => false, 'errors' => $norm['errors']];
        }
        $b = $norm['bundle'];
        $caseData = $b['case'];

        $ref = trim((string) ($caseData['reference_code'] ?? ''));
        if ($ref !== '' && $this->cases->findByReferenceCode($tenantId, $ref) !== null) {
            $ref = '';
        }

        $caseId = $this->cases->create([
            'tenant_id' => $tenantId,
            'title' => $caseData['title'],
            'summary' => $caseData['summary'],
            'classification' => $caseData['classification'],
            'status' => $caseData['status'],
            'reference_code' => $ref,
            'created_by' => $userId,
        ]);

        $personKeys = [];
        $personCount = 0;
        foreach ($b['persons'] as $p) {
            /** @var array<string,mixed> $p */
            $localKey = trim((string) ($p['key'] ?? $p['local_id'] ?? ''));
            $personId = $this->persons->create([
                'tenant_id' => $tenantId,
                'context_id' => 1,
                'status' => (string) ($p['status'] ?? 'civil'),
                'last_name' => (string) ($p['last_name'] ?? ''),
                'first_name' => (string) ($p['first_name'] ?? ''),
                'alias' => (string) ($p['alias'] ?? ''),
                'sex_apparent' => (string) ($p['sex_apparent'] ?? ''),
                'age_estimated' => $p['age_estimated'] ?? null,
                'nationality' => (string) ($p['nationality'] ?? ''),
                'language_spoken' => (string) ($p['language_spoken'] ?? ''),
                'affiliation' => (string) ($p['affiliation'] ?? ''),
                'circumstances' => (string) ($p['circumstances'] ?? ''),
                'statements' => (string) ($p['statements'] ?? ''),
                'distinguishing_marks' => (string) ($p['distinguishing_marks'] ?? ''),
                'weapons' => is_array($p['weapons'] ?? null) ? $p['weapons'] : [],
                'equipment' => is_array($p['equipment'] ?? null) ? $p['equipment'] : [],
                'grid_reference' => (string) ($p['grid_reference'] ?? ''),
                'location_description' => (string) ($p['location_description'] ?? ''),
                'submitter_callsign' => $submitterLabel,
                'submitter_user_id' => $userId,
            ]);
            $this->cases->linkPerson(
                $caseId,
                $personId,
                $tenantId,
                $userId,
                trim((string) ($p['link_note'] ?? 'Import scénario'))
            );
            if ($localKey !== '') {
                $personKeys[$localKey] = $personId;
            }
            $personCount++;
        }

        $noteCount = 0;
        foreach ($b['notes'] as $n) {
            /** @var array<string,mixed> $n */
            $body = trim((string) ($n['body'] ?? ''));
            if ($body === '') {
                continue;
            }
            $this->cases->addNote(
                $caseId,
                $tenantId,
                $body,
                (string) ($n['classification'] ?? $caseData['classification']),
                $userId,
                trim((string) ($n['author_label'] ?? 'Scénario')) ?: 'Scénario'
            );
            $noteCount++;
        }

        $evidencePresets = $this->evidencePresetMap();
        $evidenceCount = 0;
        foreach ($b['evidence'] as $e) {
            /** @var array<string,mixed> $e */
            $presetKey = trim((string) ($e['preset_key'] ?? ''));
            $label = trim((string) ($e['label'] ?? ''));
            $caption = trim((string) ($e['caption'] ?? ''));
            if ($presetKey !== '' && isset($evidencePresets[$presetKey])) {
                if ($label === '') {
                    $label = $evidencePresets[$presetKey]['label'];
                }
                if ($caption === '') {
                    $caption = $evidencePresets[$presetKey]['caption'];
                }
            }
            if ($label === '') {
                continue;
            }
            $personId = null;
            $personRef = trim((string) ($e['person_key'] ?? ''));
            if ($personRef !== '' && isset($personKeys[$personRef])) {
                $personId = $personKeys[$personRef];
            }
            $this->cases->addEvidence($caseId, $tenantId, [
                'label' => $label,
                'caption' => $caption,
                'person_id' => $personId,
                'author_label' => trim((string) ($e['author_label'] ?? 'Scénario')) ?: 'Scénario',
            ]);
            $evidenceCount++;
        }

        $siteCount = 0;
        $seizureCount = 0;
        foreach ($b['sites'] as $s) {
            /** @var array<string,mixed> $s */
            $siteId = $this->sites->create([
                'tenant_id' => $tenantId,
                'case_id' => $caseId,
                'name' => (string) ($s['name'] ?? 'Site scénario'),
                'site_type' => (string) ($s['site_type'] ?? 'habitation'),
                'summary' => (string) ($s['summary'] ?? ''),
                'grid_reference' => (string) ($s['grid_reference'] ?? ''),
                'team_label' => (string) ($s['team_label'] ?? ''),
                'rooms' => is_array($s['rooms'] ?? null) ? $s['rooms'] : null,
                'submitter_callsign' => $submitterLabel,
            ]);
            $siteCount++;
            $seizures = is_array($s['seizures'] ?? null) ? $s['seizures'] : [];
            foreach ($seizures as $sz) {
                if (!is_array($sz)) {
                    continue;
                }
                $label = trim((string) ($sz['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $this->sites->addSeizure($tenantId, [
                    'site_id' => $siteId,
                    'category' => (string) ($sz['category'] ?? 'autre'),
                    'label' => $label,
                    'quantity' => max(1, (int) ($sz['quantity'] ?? 1)),
                    'notes' => (string) ($sz['notes'] ?? ''),
                ]);
                $seizureCount++;
            }
        }

        return [
            'ok' => true,
            'errors' => [],
            'case_id' => $caseId,
            'counts' => [
                'persons' => $personCount,
                'notes' => $noteCount,
                'evidence' => $evidenceCount,
                'sites' => $siteCount,
                'seizures' => $seizureCount,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportCase(int $caseId, int $tenantId): ?array
    {
        $case = $this->cases->findById($caseId, $tenantId);
        if ($case === null || !empty($case['is_folder'])) {
            return null;
        }

        $persons = [];
        $personIndex = [];
        foreach ($this->cases->listLinkedPersonIds($caseId, $tenantId) as $i => $link) {
            $pid = (int) ($link['person_id'] ?? 0);
            $p = $this->persons->findById($pid, $tenantId);
            if ($p === null) {
                continue;
            }
            $key = 'p' . ($i + 1);
            $personIndex[$pid] = $key;
            $persons[] = [
                'key' => $key,
                'status' => (string) ($p['status'] ?? 'civil'),
                'last_name' => (string) ($p['last_name'] ?? ''),
                'first_name' => (string) ($p['first_name'] ?? ''),
                'alias' => (string) ($p['alias'] ?? ''),
                'sex_apparent' => (string) ($p['sex_apparent'] ?? ''),
                'age_estimated' => $p['age_estimated'] ?? null,
                'nationality' => (string) ($p['nationality'] ?? ''),
                'language_spoken' => (string) ($p['language_spoken'] ?? ''),
                'affiliation' => (string) ($p['affiliation'] ?? ''),
                'circumstances' => (string) ($p['circumstances'] ?? ''),
                'statements' => (string) ($p['statements'] ?? ''),
                'distinguishing_marks' => (string) ($p['distinguishing_marks'] ?? ''),
                'weapons' => is_array($p['weapons'] ?? null) ? $p['weapons'] : [],
                'equipment' => is_array($p['equipment'] ?? null) ? $p['equipment'] : [],
                'grid_reference' => (string) ($p['grid_reference'] ?? ''),
                'location_description' => (string) ($p['location_description'] ?? ''),
                'link_note' => (string) ($link['note'] ?? ''),
                'arma_profile' => $this->guessArmaProfile((string) ($p['status'] ?? '')),
                'arma_complexity' => 'DETAILED',
            ];
        }

        $notes = [];
        foreach ($this->cases->listNotes($caseId, $tenantId) as $n) {
            $notes[] = [
                'body' => (string) ($n['body'] ?? ''),
                'classification' => (string) ($n['classification'] ?? $case['classification']),
                'author_label' => (string) ($n['author_label'] ?? ''),
            ];
        }

        $evidence = [];
        foreach ($this->cases->listEvidence($caseId, $tenantId) as $e) {
            $pid = isset($e['person_id']) ? (int) $e['person_id'] : 0;
            $evidence[] = [
                'label' => (string) ($e['label'] ?? ''),
                'caption' => (string) ($e['caption'] ?? ''),
                'person_key' => $personIndex[$pid] ?? '',
                'author_label' => (string) ($e['author_label'] ?? ''),
            ];
        }

        $sites = [];
        foreach ($this->sites->listForCase($caseId, $tenantId) as $s) {
            $sid = (int) ($s['id'] ?? 0);
            $seizures = [];
            foreach ($this->sites->listSeizures($sid, $tenantId) as $sz) {
                $seizures[] = [
                    'category' => (string) ($sz['category'] ?? 'autre'),
                    'label' => (string) ($sz['label'] ?? ''),
                    'quantity' => (int) ($sz['quantity'] ?? 1),
                    'notes' => (string) ($sz['notes'] ?? ''),
                ];
            }
            $sites[] = [
                'name' => (string) ($s['name'] ?? ''),
                'site_type' => (string) ($s['site_type'] ?? 'habitation'),
                'summary' => (string) ($s['summary'] ?? ''),
                'grid_reference' => (string) ($s['grid_reference'] ?? ''),
                'team_label' => (string) ($s['team_label'] ?? ''),
                'seizures' => $seizures,
            ];
        }

        return [
            'format' => (string) ($this->meta['format'] ?? 'comspec_sse_case_bundle'),
            'formatVersion' => (int) ($this->meta['format_version'] ?? 1),
            'exportedAt' => gmdate('c'),
            'meta' => [
                'source' => 'athena',
                'case_id' => $caseId,
            ],
            'case' => [
                'reference_code' => (string) ($case['reference_code'] ?? ''),
                'title' => (string) ($case['title'] ?? ''),
                'summary' => (string) ($case['summary'] ?? ''),
                'classification' => (string) ($case['classification'] ?? 'encadrement'),
                'status' => (string) ($case['status'] ?? 'ouvert'),
            ],
            'persons' => $persons,
            'notes' => $notes,
            'evidence' => $evidence,
            'sites' => $sites,
        ];
    }

    /**
     * Pack terrain pour Arma (JSON) + script SQF d’aide mission maker.
     *
     * @param array<string, mixed> $athenaBundle
     * @return array{json:array<string,mixed>,sqf:string}
     */
    public function toArmaPack(array $athenaBundle): array
    {
        $norm = $this->normalizeBundle($athenaBundle);
        $bundle = $norm['bundle'] ?? $athenaBundle;
        $case = is_array($bundle['case'] ?? null) ? $bundle['case'] : [];
        $caseCode = trim((string) ($case['reference_code'] ?? ''));
        if ($caseCode === '') {
            $caseCode = 'SSE-PACK-' . date('Ymd');
        }

        $entities = [];
        foreach (is_array($bundle['persons'] ?? null) ? $bundle['persons'] : [] as $i => $p) {
            if (!is_array($p)) {
                continue;
            }
            $name = trim(implode(' ', array_filter([
                trim((string) ($p['first_name'] ?? '')),
                trim((string) ($p['last_name'] ?? '')),
            ])));
            if ($name === '') {
                $name = trim((string) ($p['alias'] ?? 'INCONNU'));
            }
            $uid = 'pack_' . preg_replace('/[^a-z0-9]+/i', '_', (string) ($p['key'] ?? ('p' . ($i + 1)))) . '_' . ($i + 1);
            $entities[] = [
                'type' => 'PERSON',
                'uid' => $uid,
                'classification' => strtoupper((string) ($case['classification'] ?? 'CONFIDENTIAL')),
                'profile' => (string) ($p['arma_profile'] ?? $this->guessArmaProfile((string) ($p['status'] ?? ''))),
                'complexity' => (string) ($p['arma_complexity'] ?? 'DETAILED'),
                'sections' => [
                    'identity' => [
                        'name' => $name,
                        'alias' => (string) ($p['alias'] ?? ''),
                        'nationality' => (string) ($p['nationality'] ?? ''),
                        'role' => (string) ($p['affiliation'] ?? ''),
                    ],
                    'weapons' => is_array($p['weapons'] ?? null) ? $p['weapons'] : [],
                    'equipment' => is_array($p['equipment'] ?? null) ? $p['equipment'] : [],
                    'notes' => array_values(array_filter([
                        (string) ($p['circumstances'] ?? ''),
                        (string) ($p['statements'] ?? ''),
                    ])),
                ],
            ];
        }

        $sites = [];
        foreach (is_array($bundle['sites'] ?? null) ? $bundle['sites'] : [] as $i => $s) {
            if (!is_array($s)) {
                continue;
            }
            $sites[] = [
                'uid' => 'site_' . ($i + 1),
                'name' => (string) ($s['name'] ?? 'Site'),
                'site_type' => (string) ($s['site_type'] ?? 'habitation'),
                'grid_reference' => (string) ($s['grid_reference'] ?? ''),
                'summary' => (string) ($s['summary'] ?? ''),
                'seizures' => is_array($s['seizures'] ?? null) ? $s['seizures'] : [],
            ];
        }

        $json = [
            'format' => (string) ($this->meta['arma_format'] ?? 'comspec_sse_mission_pack'),
            'formatVersion' => 1,
            'exportedAt' => gmdate('c'),
            'case_code' => $caseCode,
            'mission' => [
                'title' => (string) ($case['title'] ?? ''),
                'summary' => (string) ($case['summary'] ?? ''),
            ],
            'entities' => $entities,
            'sites' => $sites,
            'athena_bundle' => $bundle,
        ];

        return [
            'json' => $json,
            'sqf' => $this->buildArmaSqf($json),
        ];
    }

    /**
     * @return array{format:string,formatVersion:int,meta:array<string,mixed>,case:array<string,mixed>,persons:list<array<string,mixed>>,notes:list<array<string,mixed>>,evidence:list<array<string,mixed>>,sites:list<array<string,mixed>>}
     */
    public function exampleSkeleton(): array
    {
        return [
            'format' => (string) ($this->meta['format'] ?? 'comspec_sse_case_bundle'),
            'formatVersion' => (int) ($this->meta['format_version'] ?? 1),
            'meta' => [
                'theatre' => 'Irak / 2016',
                'author' => 'Scénariste',
                'fiction' => true,
            ],
            'case' => [
                'reference_code' => '',
                'title' => 'Cache logistique — secteur Nord',
                'summary' => 'Affaire fictive : réseau de ravitaillement soupçonné autour d’un dépôt rural.',
                'classification' => 'confidentiel',
                'status' => 'ouvert',
            ],
            'persons' => [
                [
                    'key' => 'p1',
                    'status' => 'prioritaire',
                    'last_name' => 'AL-RASHID',
                    'first_name' => 'Karim',
                    'alias' => 'THE DRIVER',
                    'nationality' => 'Irakienne',
                    'affiliation' => 'Cellule logistique',
                    'circumstances' => 'Repéré près du dépôt ; rôle de convoyeur probable.',
                    'weapons' => ['AK-74'],
                    'equipment' => ['Radio portable'],
                    'arma_profile' => 'LOGISTICS',
                    'arma_complexity' => 'HIGH_VALUE',
                ],
                [
                    'key' => 'p2',
                    'status' => 'civil',
                    'last_name' => 'HASSAN',
                    'first_name' => 'Omar',
                    'alias' => '',
                    'nationality' => 'Irakienne',
                    'affiliation' => '',
                    'circumstances' => 'Habitant voisin — possible fausse piste.',
                    'arma_profile' => 'CIVILIAN',
                    'arma_complexity' => 'LIGHT',
                ],
            ],
            'notes' => [
                [
                    'body' => 'Priorité : confirmer le lien entre le convoyeur et le dépôt avant toute action directe.',
                    'classification' => 'confidentiel',
                    'author_label' => 'Bureau renseignement',
                ],
            ],
            'evidence' => [
                [
                    'preset_key' => 'phone',
                    'label' => 'Téléphone saisi (convoyeur)',
                    'caption' => 'Appareil récupéré lors du contrôle',
                    'person_key' => 'p1',
                ],
                [
                    'preset_key' => 'document',
                    'label' => 'Carnet de livraisons',
                    'caption' => 'Mentions de grilles et de surnoms',
                ],
            ],
            'sites' => [
                [
                    'name' => 'Dépôt rural Nord',
                    'site_type' => 'depot',
                    'summary' => 'Hangar agricole utilisé — accès latéral.',
                    'grid_reference' => 'AB123456',
                    'seizures' => [
                        ['category' => 'munition', 'label' => 'Caisses 7,62', 'quantity' => 3, 'notes' => 'Stockage improvisé'],
                        ['category' => 'radio', 'label' => 'Poste VHF', 'quantity' => 1, 'notes' => ''],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $pack
     * @return array<string, mixed>
     */
    private function missionPackToAthena(array $pack): array
    {
        $mission = is_array($pack['mission'] ?? null) ? $pack['mission'] : [];
        $persons = [];
        foreach (is_array($pack['entities'] ?? null) ? $pack['entities'] : [] as $i => $ent) {
            if (!is_array($ent) || strtoupper((string) ($ent['type'] ?? '')) !== 'PERSON') {
                continue;
            }
            $sections = is_array($ent['sections'] ?? null) ? $ent['sections'] : [];
            $identity = is_array($sections['identity'] ?? null) ? $sections['identity'] : [];
            $fullName = trim((string) ($identity['name'] ?? ''));
            $parts = preg_split('/\s+/', $fullName, 2) ?: [];
            $persons[] = [
                'key' => (string) ($ent['uid'] ?? ('p' . ($i + 1))),
                'status' => $this->guessPersonStatus((string) ($ent['profile'] ?? '')),
                'first_name' => (string) ($parts[0] ?? ''),
                'last_name' => (string) ($parts[1] ?? ''),
                'alias' => (string) ($identity['alias'] ?? ''),
                'nationality' => (string) ($identity['nationality'] ?? ''),
                'affiliation' => (string) ($identity['role'] ?? ''),
                'circumstances' => implode("\n", array_map('strval', is_array($sections['notes'] ?? null) ? $sections['notes'] : [])),
                'weapons' => is_array($sections['weapons'] ?? null) ? $sections['weapons'] : [],
                'equipment' => is_array($sections['equipment'] ?? null) ? $sections['equipment'] : [],
                'arma_profile' => (string) ($ent['profile'] ?? 'INSURGENT'),
                'arma_complexity' => (string) ($ent['complexity'] ?? 'DETAILED'),
            ];
        }

        return [
            'format' => (string) ($this->meta['format'] ?? 'comspec_sse_case_bundle'),
            'formatVersion' => 1,
            'meta' => ['source' => 'arma_pack'],
            'case' => [
                'reference_code' => (string) ($pack['case_code'] ?? ''),
                'title' => (string) ($mission['title'] ?? 'Pack mission Arma'),
                'summary' => (string) ($mission['summary'] ?? ''),
                'classification' => 'confidentiel',
                'status' => 'ouvert',
            ],
            'persons' => $persons,
            'notes' => [],
            'evidence' => [],
            'sites' => is_array($pack['sites'] ?? null) ? $pack['sites'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $pack
     */
    private function buildArmaSqf(array $pack): string
    {
        $caseCode = $this->sqfString((string) ($pack['case_code'] ?? 'SSE-PACK'));
        $lines = [
            '// Pack mission SSE — généré depuis Athena',
            '// 1) Exécuter côté serveur / init mission',
            '// 2) Appliquer chaque identité sur une unité avec comspec_sse_fnc_setIdentity',
            '',
            'if (!isServer) exitWith {};',
            '',
            'missionNamespace setVariable ["COMSPEC_SSE_ActiveCase", ' . $caseCode . ', true];',
            '',
            'private _applyPerson = {',
            '    params ["_unit", "_name", "_alias", "_nationality", "_profile", "_complexity"];',
            '    if (isNull _unit) exitWith {};',
            '    private _data = ["PERSON", "SCRIPT", _profile, _complexity] call comspec_sse_fnc_createDataModel;',
            '    [_unit, _data] call comspec_sse_fnc_setData;',
            '    [_unit, createHashMapFromArray [',
            '        ["name", _name],',
            '        ["alias", _alias],',
            '        ["nationality", _nationality]',
            '    ]] call comspec_sse_fnc_setIdentity;',
            '};',
            '',
            '// === Identités (remplacer les objets unité) ===',
        ];

        foreach (is_array($pack['entities'] ?? null) ? $pack['entities'] : [] as $ent) {
            if (!is_array($ent)) {
                continue;
            }
            $id = is_array($ent['sections']['identity'] ?? null) ? $ent['sections']['identity'] : [];
            $uid = (string) ($ent['uid'] ?? 'person');
            $lines[] = '// ' . $uid;
            $lines[] = sprintf(
                '// [cetteUnite, %s, %s, %s, %s, %s] call _applyPerson;',
                $this->sqfString((string) ($id['name'] ?? '')),
                $this->sqfString((string) ($id['alias'] ?? '')),
                $this->sqfString((string) ($id['nationality'] ?? '')),
                $this->sqfString((string) ($ent['profile'] ?? 'INSURGENT')),
                $this->sqfString((string) ($ent['complexity'] ?? 'DETAILED'))
            );
            $lines[] = '';
        }

        $lines[] = '// Sites / saisies : créer via Zeus ou Eden, puis sync Athena (case_code ci-dessus).';
        $lines[] = 'true';

        return implode("\n", $lines) . "\n";
    }

    private function sqfString(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '""'], $value) . '"';
    }

    private function normalizeCaseStatus(string $raw): string
    {
        $s = strtolower(trim($raw));
        $allowed = is_array($this->meta['case_statuses'] ?? null)
            ? $this->meta['case_statuses']
            : ['ouvert', 'en_cours', 'clos', 'archive'];

        return in_array($s, $allowed, true) ? $s : 'ouvert';
    }

    private function guessArmaProfile(string $personStatus): string
    {
        return match ($personStatus) {
            'civil' => 'CIVILIAN',
            'prioritaire' => 'COMMANDER',
            'detenu' => 'INSURGENT',
            default => 'INSURGENT',
        };
    }

    private function guessPersonStatus(string $profile): string
    {
        return match (strtoupper($profile)) {
            'CIVILIAN' => 'civil',
            'COMMANDER', 'INTELLIGENCE' => 'prioritaire',
            default => 'combattant',
        };
    }

    /**
     * @return array<string, array{label:string,caption:string}>
     */
    private function evidencePresetMap(): array
    {
        $path = base_path('config/sse_case_presets.php');
        $cfg = is_file($path) ? (array) require $path : [];
        $out = [];
        foreach (is_array($cfg['evidence'] ?? null) ? $cfg['evidence'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $out[$key] = [
                'label' => (string) ($row['label'] ?? $key),
                'caption' => (string) ($row['caption'] ?? ''),
            ];
        }

        return $out;
    }
}
