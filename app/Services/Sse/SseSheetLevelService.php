<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Session;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseSheetRepository;
use App\Support\SseRedactionExplain as X;
use App\Support\SseUsClassification;

/**
 * Télécharger un document émis À UN NIVEAU DE DIFFUSION choisi, sans rien émettre : le PDF est rendu depuis l'instantané du document
 * (mêmes textes, même référence), avec le marquage et le caviardage du niveau demandé. L'original n'est jamais modifié.
 *
 *  - niveau plus BAS que celui de l'émission : les catégories qui exigent plus que ce niveau sont masquées dans les données ET dans
 *    les textes imprimés (valeurs remplacées par la barre de leur catégorie : la valeur masquée n'est écrite nulle part) ;
 *  - niveau plus HAUT : possible seulement si l'instantané contient déjà les données (aucune catégorie masquée à l'émission ne devient
 *    lisible à ce niveau) ; sinon c'est la réémission à un niveau supérieur qui s'applique ;
 *  - jamais au-dessus de l'habilitation de l'opérateur ; l'avis de recherche, sans catégories caviardables, ne se télécharge qu'à
 *    son niveau d'émission.
 * Chaque téléchargement à un niveau est journalisé (sse_audit_log) et le débit est limité.
 */
final class SseSheetLevelService
{
    /** Téléchargements à un niveau par minute et par session. */
    public const LIMIT = 30;

    public function __construct(
        private ?SseSheetRepository $repo = null,
        private ?SseSheetFactsService $facts = null,
        private ?SseSheetService $service = null,
    ) {
        $this->repo ??= new SseSheetRepository();
        $this->facts ??= new SseSheetFactsService();
        $this->service ??= new SseSheetService($this->facts);
    }

    /**
     * Ce que donne le document à un niveau : état (origin, lower, higher, reissue, unavailable), passages masqués par catégorie,
     * données et textes à imprimer. Ne porte aucune valeur masquée.
     *
     * @param array<string, mixed> $issue ligne de SseSheetRepository
     * @return array{state: string, by: array<string, int>, total: int, facts: ?array<string, mixed>, texts: array<string, string>, need: string}
     */
    public function plan(array $issue, string $level): array
    {
        $origin = SseUsClassification::normalize((string) $issue['classification']);
        $x = X::forIssue($issue);
        $texts = array_map('strval', (array) ($issue['texts'] ?? []));
        $res = static fn (string $state, array $by, ?array $facts = null, ?array $t = null, string $need = ''): array => [
            'state' => $state, 'by' => $by, 'total' => array_sum($by), 'facts' => $facts, 'texts' => $t ?? $texts, 'need' => $need,
        ];
        if (!isset(SseRedactionService::LEVELS[$level])) {
            return $res('unavailable', $x['by']);
        }
        if ($level === $origin) {
            return $res('origin', $x['by']);
        }
        if ($issue['model'] === 'notice') {
            return $res('unavailable', $x['by']);
        }
        $snap = is_array($issue['snapshot'] ?? null) ? $issue['snapshot'] : [];
        $data = is_array($snap['data'] ?? null) ? $snap['data'] : [];
        if (!is_array($data['facts'] ?? null)) {
            return $res('unavailable', $x['by']);
        }
        $required = SseSheetVisibility::effective(is_array($data['visibility'] ?? null) ? $data['visibility'] : []);
        $facts = X::retag($data['facts']);
        if (SseRedactionService::levelRank($level) > SseRedactionService::levelRank($origin)) {
            // Niveau plus haut : seulement si rien de ce qui est masqué ne deviendrait lisible (sinon, réémission).
            $need = '';
            $blocked = false;
            foreach (array_keys($x['by']) as $cat) {
                $cat = (string) $cat;
                $needCode = $cat === '' ? '' : X::needCode($cat, $required);
                if ($cat === '' || SseRedactionService::levelRank($needCode) <= SseRedactionService::levelRank($level)) {
                    $blocked = true;
                    if ($needCode !== '' && ($need === '' || SseRedactionService::levelRank($needCode) > SseRedactionService::levelRank($need))) {
                        $need = $needCode;
                    }
                }
            }
            if ($blocked) {
                return $res('reissue', $x['by'], null, null, $need);
            }

            return $res('higher', $x['by']);
        }
        $masked = $this->facts->mask($facts, $level, $required)['facts'];
        $scrubbed = $this->facts->scrub($facts, $texts, $level, $required);
        $by = X::scanData($masked);
        foreach (X::scanData($scrubbed) as $c => $n) {
            $by[$c] = ($by[$c] ?? 0) + $n;
        }

        return $res('lower', $by, $masked, $scrubbed);
    }

    /**
     * Données du PDF à ce niveau (le document déjà reconstruit par SseSheetService::reissue : photos, emblème, levée).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    public static function apply(array $data, array $plan, string $origin, string $level): array
    {
        $photos = $data['facts']['photos'] ?? [];
        if (is_array($plan['facts'] ?? null)) {
            $data['facts'] = $plan['facts'];
            $data['facts']['photos'] = $photos;
            $data['texts'] = $plan['texts'];
        }
        $data['level'] = $level;
        $data['classification'] = SseSheetService::levelLabels($level);
        $data['copy_level'] = $origin;

        return $data;
    }

    /**
     * Niveaux proposés à l'opérateur pour ce document : du plus bas jusqu'à son habilitation, avec ce que chacun masque.
     *
     * @param array<string, mixed> $issue
     * @return list<array{level: string, abbr: string, state: string, by: array<string, int>, total: int, origin: bool, need: string}>
     */
    public function options(array $issue, SseSheetAccess $access): array
    {
        $origin = SseUsClassification::normalize((string) $issue['classification']);
        $out = [];
        foreach (SseSheetService::levelsFor($access) as $level) {
            $plan = $this->plan($issue, $level);
            if ($plan['state'] === 'unavailable') {
                continue;
            }
            $out[] = [
                'level' => $level, 'abbr' => SseUsClassification::abbr($level), 'state' => $plan['state'], 'by' => $plan['by'], 'total' => $plan['total'],
                'origin' => $level === $origin, 'need' => $plan['need'],
            ];
        }

        return $out;
    }

    /**
     * Débit : téléchargements à un niveau par minute et par session (compteur en session).
     */
    public static function allowed(): bool
    {
        $now = time();
        $hits = array_values(array_filter((array) Session::get('sse_sheet_level_downloads', []), static fn ($t): bool => is_int($t) && $now - $t < 60));
        if (count($hits) >= self::LIMIT) {
            Session::set('sse_sheet_level_downloads', $hits);

            return false;
        }
        $hits[] = $now;
        Session::set('sse_sheet_level_downloads', $hits);

        return true;
    }

    /**
     * Rend le PDF d'un document émis à un niveau. Erreurs : notfound (document absent, autre communauté, classé au-dessus de
     * l'habilitation : la même réponse), level (niveau inconnu ou non disponible), ceiling (au-dessus de l'habilitation), reissue
     * (des passages masqués ne se lisent qu'à la réémission), format, engine.
     *
     * @return array{ok: bool, error: string, bytes: ?string, filename: string, issue: ?array<string, mixed>}
     */
    public function download(int $tenantId, int $issueId, string $level, SseSheetAccess $access, string $format = 'pdf'): array
    {
        $fail = static fn (string $e, ?array $issue = null): array => ['ok' => false, 'error' => $e, 'bytes' => null, 'filename' => '', 'issue' => $issue];
        if (!SseSheetRepository::ready()) {
            return $fail('notfound');
        }
        $issue = $this->repo->findIssue($tenantId, $issueId);
        if ($issue === null || !$access->canDraft() || SseRedactionService::levelRank((string) $issue['classification']) > SseRedactionService::levelRank($access->level())) {
            return $fail('notfound');
        }
        if ($format !== 'pdf') {
            return $fail('format', $issue);
        }
        if (!isset(SseRedactionService::LEVELS[$level])) {
            return $fail('level', $issue);
        }
        if (SseRedactionService::levelRank($level) > SseRedactionService::levelRank($access->level())) {
            return $fail('ceiling', $issue);
        }
        $plan = $this->plan($issue, $level);
        if ($plan['state'] === 'unavailable') {
            return $fail('level', $issue);
        }
        if ($plan['state'] === 'reissue') {
            return $fail('reissue', $issue);
        }
        $res = $this->service->reissue($tenantId, $issueId, $access, $level);
        if (!$res['ok'] || $res['bytes'] === null) {
            return $fail($res['error'] === 'forbidden' ? 'notfound' : $res['error'], $issue);
        }
        $this->audit($tenantId, $issue, $level, $format, $access);

        return $res;
    }

    /** @param array<string, mixed> $issue */
    private function audit(int $tenantId, array $issue, string $level, string $format, SseSheetAccess $access): void
    {
        try {
            (new SseAuditLogRepository())->write([
                'tenant_id' => $tenantId, 'actor_user_id' => $access->userId(), 'actor_label' => $access->actorLabel() !== '' ? $access->actorLabel() : 'Fiches PDF',
                'action' => 'document.downloaded_at_level', 'object_type' => 'sse_doc_issue', 'object_id' => (int) $issue['id'], 'reason' => '',
                'after' => [
                    'ref' => (string) $issue['ref'], 'model' => (string) $issue['model'], 'level' => SseUsClassification::abbr($level), 'level_code' => $level,
                    'origin' => SseUsClassification::abbr((string) $issue['classification']), 'format' => $format,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('Fiches PDF : audit du téléchargement à un niveau indisponible : ' . $e->getMessage());
        }
    }
}
