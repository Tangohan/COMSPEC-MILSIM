<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\PersonnelProfileRepository;
use App\Repositories\SseCaseRepository;
use App\Repositories\SseEditorItemRepository;
use App\Services\Atak\AtakGameAdminService;

/**
 * Bibliothèque de l'éditeur SSE, côté site (contrat : docs/technical/ICD-ATHENA-ATAK.md, ICD-SSE-EDITOR-001).
 *
 * Passe par le canal existant de COMSPEC Link (SubmitIdCheck → POST /api/sse/identity/checks, champ « kind »), qui ne
 * rend que « OK|id|ref » : aucune nouvelle DLL n'est nécessaire. Donc `id` est un nombre et `ref` un texte d'une ligne,
 * sans « | », limités à 8 000 octets par la DLL :
 *   editor_list   id = position de la page suivante (0 : fin), ref = lignes pour parseSimpleArray
 *   editor_save   id = identifiant du contenu, ref = saved | conflict (version plus récente déjà enregistrée)
 *   editor_found  id = identifiant du contenu (0 : inconnu), ref = found | known | unknown
 */
final class SseEditorLibraryService
{
    public const KINDS = ['editor_list', 'editor_save', 'editor_found'];

    /** Une page, avec la marge pour « OK|id|ref ». */
    private const PAGE_BYTES = 7400;
    /** Un contenu enregistré laisse la place à la ligne « trouvé » ajoutée ensuite. */
    private const ITEM_BYTES = 7000;
    private const PAGE_ROWS = 50;
    private const MAX_ITEMS = 500;
    private const HISTORY_ROWS = 15;

    private const TYPES = ['intel', 'identity', 'paper', 'case', 'kit'];
    private const STATES = ['Brouillon', 'Modèle', 'Placé en mission', 'Archivé'];

    /** Droits d'un compte précis, du plus haut au plus bas : mêmes règles que SseClearanceService::maxLevel. */
    private const PERMS_EXPLICIT_OK = ['atak.sse.clearance.tres_restreint', 'atak.sse.clearance.confidentiel'];
    private const PERMS_EXPLICIT_LOW = ['atak.sse.clearance.encadrement'];
    private const PERMS_ROLE_OK = ['admin.access', 'atak.sse.grant', 'atak.sse.case.manage'];
    private const PERMS_MEMBER = ['atak.sse.access', 'atak.sse.cases'];

    /** @var \Closure(int, int, list<string>): bool */
    private \Closure $can;
    /** @var \Closure(int): string */
    private \Closure $profileLevel;

    /**
     * @param (\Closure(int, int, list<string>): bool)|null $can droit d'un compte dans une communauté
     * @param (\Closure(int): string)|null $profileLevel habilitation inscrite sur la fiche opérateur
     */
    public function __construct(
        private ?SseEditorItemRepository $items = null,
        ?\Closure $can = null,
        ?\Closure $profileLevel = null,
    ) {
        $this->items ??= new SseEditorItemRepository();
        $this->can = $can ?? static fn (int $t, int $u, array $p): bool => (new AtakGameAdminService())->can($t, $u, $p);
        $this->profileLevel = $profileLevel ?? static fn (int $u): string
            => strtolower(trim((string) (((new PersonnelProfileRepository())->getByUserId($u) ?? [])['clearance_level'] ?? '')));
    }

    /**
     * Le compte relié en jeu peut-il lire et écrire la bibliothèque ? Habilitation Confidentiel, comme le paquet : elle
     * contient des couvertures et des légendes. La fiche opérateur compte pour un membre du Bureau SSE seulement.
     */
    public function userAllowed(int $tenantId, int $userId): bool
    {
        $can = $this->can;
        if ($can($tenantId, $userId, self::PERMS_EXPLICIT_OK)) {
            return true;
        }
        if ($can($tenantId, $userId, self::PERMS_EXPLICIT_LOW)) {
            return false;
        }
        if ($can($tenantId, $userId, self::PERMS_ROLE_OK)) {
            return true;
        }
        $level = ($this->profileLevel)($userId);

        return isset(SseRedactionService::LEVELS[$level])
            && SseRedactionService::levelRank($level) >= SseRedactionService::levelRank(SseCaseRepository::CLASS_CONFIDENTIAL)
            && $can($tenantId, $userId, self::PERMS_MEMBER);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string}|array{error: string}
     */
    public function handle(int $tenantId, ?int $userId, array $body): array
    {
        return match ($body['kind'] ?? null) {
            'editor_list' => $this->listPage($tenantId, is_numeric($body['offset'] ?? null) ? (int) $body['offset'] : 0),
            'editor_save' => $this->save($tenantId, $userId, is_array($body['item'] ?? null) ? $body['item'] : []),
            'editor_found' => $this->found($tenantId, $body),
            default => ['error' => 'invalid'],
        };
    }

    /** @return array{id: int, ref: string} */
    private function listPage(int $tenantId, int $offset): array
    {
        $offset = max(0, $offset);
        $rows = [];
        $bytes = 2;
        $more = false;
        if ($offset < self::MAX_ITEMS) {
            $limit = min(self::PAGE_ROWS, self::MAX_ITEMS - $offset) + 1;
            foreach ($this->items->page($tenantId, $offset, $limit) as $row) {
                $text = self::encode($row);
                if (count($rows) >= self::PAGE_ROWS || ($rows !== [] && $bytes + strlen($text) + 1 > self::PAGE_BYTES)) {
                    $more = true;
                    break;
                }
                $rows[] = $text;
                $bytes += strlen($text) + 1;
            }
        }
        $next = $offset + count($rows);

        return ['id' => $more && $next < self::MAX_ITEMS ? $next : 0, 'ref' => '[' . implode(',', $rows) . ']'];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{id: int, ref: string}|array{error: string}
     */
    private function save(int $tenantId, ?int $userId, array $item): array
    {
        $uid = is_string($item['id'] ?? null) ? $item['id'] : '';
        $title = self::text($item['title'] ?? '', 190);
        $type = $item['type'] ?? '';
        $state = $item['state'] ?? '';
        $version = is_numeric($item['version'] ?? null) ? (int) $item['version'] : 0;
        if (preg_match('/^[A-Za-z0-9_-]{1,40}$/', $uid) !== 1 || $title === '' || $version < 1
            || !in_array($type, self::TYPES, true) || !in_array($state, self::STATES, true)) {
            return ['error' => 'invalid_item'];
        }
        $existing = $this->items->find($tenantId, $uid);
        if ($existing !== null && (int) $existing['version'] > $version) {
            return ['id' => (int) $existing['id'], 'ref' => 'conflict'];
        }
        $history = [];
        foreach (array_slice(is_array($item['history'] ?? null) ? array_values($item['history']) : [], -self::HISTORY_ROWS) as $row) {
            if (is_array($row)) {
                $history[] = [self::text($row[0] ?? '', 120), self::text($row[1] ?? '', 80), self::text($row[2] ?? '', 32)];
            }
        }
        // Découverte : le premier connu reste ; celle vue par un poste hors ligne arrive avec son enregistrement.
        $found = is_array($item['found'] ?? null) ? array_values($item['found']) : [];
        $foundBy = self::text($existing['found_by'] ?? ($found[0] ?? ''), 80);
        $foundWhen = self::text($existing['found_when'] ?? ($found[1] ?? ''), 32);
        $hasFound = $foundBy !== '' && $foundWhen !== '';
        $values = [
            'item_type' => $type,
            'title' => $title,
            'body' => self::multiline($item['body'] ?? ''),
            'marking' => self::choice($item['marking'] ?? 0, 2),
            'paper' => self::choice($item['paper'] ?? 0, 3),
            'nature' => self::choice($item['nature'] ?? 0, 2),
            'delivery' => self::choice($item['delivery'] ?? 0, 2),
            'state' => $state,
            'op_label' => self::text($item['op'] ?? '', 120),
            'link_label' => self::text($item['link'] ?? '', 190),
            'version' => $version,
            'history_json' => (string) json_encode($history, JSON_UNESCAPED_UNICODE),
            'found_by' => $hasFound ? $foundBy : null,
            'found_when' => $hasFound ? $foundWhen : null,
        ];
        // Trop long pour repasser par la DLL : refusé plutôt que coupé, le poste garde le contenu entier.
        if (strlen(self::encode($values + ['item_uid' => $uid])) > self::ITEM_BYTES) {
            return ['error' => 'too_large'];
        }
        if ($existing === null) {
            $values['author_user_id'] = $userId;
        }

        return ['id' => $this->items->save($tenantId, $uid, $values), 'ref' => 'saved'];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string}|array{error: string}
     */
    private function found(int $tenantId, array $body): array
    {
        $uid = is_string($body['uid'] ?? null) ? $body['uid'] : '';
        $by = self::text($body['player'] ?? '', 80);
        $when = self::text($body['when'] ?? '', 32);
        if (preg_match('/^[A-Za-z0-9_-]{1,40}$/', $uid) !== 1 || $by === '' || $when === '') {
            return ['error' => 'invalid_item'];
        }
        $existing = $this->items->find($tenantId, $uid);
        // Contenu resté sur le poste de son auteur : il remontera avec son enregistrement. Réponse normale, pas de renvoi.
        if ($existing === null) {
            return ['id' => 0, 'ref' => 'unknown'];
        }

        return ['id' => (int) $existing['id'], 'ref' => $this->items->markFound($tenantId, $uid, $by, $when) ? 'found' : 'known'];
    }

    /**
     * Ligne pour parseSimpleArray : [id, type, titre, texte, classification, papier, nature, remise, état, opération, lié à,
     * version, historique, [trouvé par, quand]].
     *
     * @param array<string, mixed> $row
     */
    private static function encode(array $row): string
    {
        $history = json_decode((string) ($row['history_json'] ?? '[]'), true);
        $historyText = [];
        foreach (is_array($history) ? $history : [] as $h) {
            $historyText[] = '[' . self::sqf($h[0] ?? '') . ',' . self::sqf($h[1] ?? '') . ',' . self::sqf($h[2] ?? '') . ']';
        }
        $found = ($row['found_when'] ?? null) !== null
            ? self::sqf($row['found_by'] ?? '') . ',' . self::sqf($row['found_when'])
            : '';

        return '[' . implode(',', [
            self::sqf($row['item_uid'] ?? ''),
            self::sqf($row['item_type'] ?? ''),
            self::sqf($row['title'] ?? ''),
            self::sqf($row['body'] ?? ''),
            (int) ($row['marking'] ?? 0),
            (int) ($row['paper'] ?? 0),
            (int) ($row['nature'] ?? 0),
            (int) ($row['delivery'] ?? 0),
            self::sqf($row['state'] ?? ''),
            self::sqf($row['op_label'] ?? ''),
            self::sqf($row['link_label'] ?? ''),
            (int) ($row['version'] ?? 1),
            '[' . implode(',', $historyText) . ']',
            '[' . $found . ']',
        ]) . ']';
    }

    /**
     * Texte SQF d'une ligne : guillemets doublés. La DLL remplace « | » et les retours à la ligne, qui voyagent donc sous
     * la forme \p et \n ; le mod les rétablit (fn_editorLibrary).
     */
    private static function sqf(mixed $value): string
    {
        $s = str_replace(["\r\n", "\r", "\n"], '\\n', self::clean($value));

        return '"' . str_replace(['|', '"'], ['\\p', '""'], (string) preg_replace('/[\x00-\x1F\x7F]/', ' ', $s)) . '"';
    }

    private static function clean(mixed $value): string
    {
        return is_scalar($value) ? mb_scrub((string) $value, 'UTF-8') : '';
    }

    private static function text(mixed $value, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]/', ' ', self::clean($value))), 0, $max);
    }

    private static function multiline(mixed $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", self::clean($value));
    }

    private static function choice(mixed $value, int $max): int
    {
        return is_numeric($value) ? max(0, min($max, (int) $value)) : 0;
    }
}
