<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseIdCoverLinkRepository;

/**
 * Couverture née en jeu, remontée à Athena (contrat : docs/technical/ICD-ATHENA-ATAK.md, ICD-SSE-COVER-001).
 *
 * Canal SubmitIdCheck de COMSPEC Link (champ « kind » = cover_report), aucune nouvelle DLL. Le compte est celui du jeton
 * de jeu, jamais une donnée du corps : un poste n'écrit que la couverture de son propre compte. Une clé de communauté
 * seule ne dit pas qui appelle : réponse « unlinked », rien n'est écrit.
 *
 * Confidentialité : ces lignes ne vont ni dans le registre des identités, ni dans les listes de surveillance, ni dans le
 * paquet. Elles se lisent dans Mes papiers (le joueur lui-même) et, avec l'habilitation Confidentiel, sur sa fiche.
 */
final class SseIdGameCoverService
{
    public const KIND = 'cover_report';

    private const MAX_CONTACTS = 10;
    private const MAX_DOCS = 10;
    private const TEXT_FIELDS = [
        'name' => 160, 'dob' => 32, 'pob' => 120, 'country' => 80, 'address' => 255, 'city' => 120,
        'profession' => 120, 'employer' => 190, 'phone' => 60, 'travel' => 255, 'family' => 190,
    ];

    public function __construct(private ?SseIdCoverLinkRepository $covers = null)
    {
        $this->covers ??= new SseIdCoverLinkRepository();
    }

    /**
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string}|array{error: string}
     */
    public function store(int $tenantId, ?int $userId, array $body): array
    {
        if ($userId === null || $userId < 1) {
            return ['id' => 0, 'ref' => 'unlinked'];
        }
        $cover = self::normalize(is_array($body['cover'] ?? null) ? $body['cover'] : []);
        if ($cover === null) {
            return ['error' => 'invalid_cover'];
        }
        $map = is_numeric($body['map'] ?? null) && (int) $body['map'] > 0 ? (int) $body['map'] : null;
        $op = self::text($body['op'] ?? '', 64);
        $op = $op !== '' ? $op : null;
        $json = (string) json_encode($cover, JSON_UNESCAPED_UNICODE);
        $hash = sha1($json);
        $existing = $this->covers->findGameCover($tenantId, $userId, SseIdCoverLinkRepository::scopeKey($map, $op));
        // Même contenu : pas d'écriture, la date reste celle du dernier changement.
        if ($existing !== null && (string) $existing['content_hash'] === $hash) {
            return ['id' => (int) $existing['id'], 'ref' => 'unchanged;' . substr((string) $existing['updated_at'], 0, 16)];
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = $this->covers->saveGameCover($tenantId, $userId, $map, $op, $hash, $json, $now);

        return ['id' => $id, 'ref' => 'saved;' . substr($now, 0, 16)];
    }

    /**
     * Couvertures nées en jeu, décodées pour l'affichage.
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(int $tenantId, int $userId): array
    {
        $out = [];
        foreach ($this->covers->gameCoversForUser($tenantId, $userId) as $row) {
            $cover = json_decode((string) $row['cover_json'], true);
            if (is_array($cover)) {
                $out[] = $cover + [
                    'context_id' => ($row['context_id'] ?? null) !== null ? (int) $row['context_id'] : null,
                    'op_key' => ($row['op_key'] ?? null) !== null ? (string) $row['op_key'] : null,
                    'updated_at' => (string) $row['updated_at'],
                ];
            }
        }

        return $out;
    }

    /**
     * État montré au joueur dans Mes papiers : d'où vient sa couverture, de quand, et ce qu'il doit faire.
     *
     * @param list<array<string, mixed>> $briefs fiches de couverture préparées pour lui
     * @param list<array<string, mixed>> $kits kits de papiers en jeu
     * @param list<array<string, mixed>> $gameCovers self::forUser()
     * @return array{steam_linked: bool, source: string, updated_at: string, todo: string}
     */
    public static function status(bool $steamLinked, array $briefs, array $kits, array $gameCovers): array
    {
        $prepared = '';
        foreach ($briefs as $b) {
            $prepared = max($prepared, (string) ($b['updated_at'] ?? ($b['created_at'] ?? '')));
        }
        $hasPrepared = $briefs !== [] || $kits !== [];
        $source = match (true) {
            $hasPrepared => 'athena',
            $gameCovers !== [] => 'game',
            default => 'none',
        };
        $todo = match (true) {
            !$steamLinked && $hasPrepared => 'Liez votre compte Steam : sans lui, le serveur de jeu ne peut pas vous remettre cette couverture.',
            !$steamLinked => 'Liez votre compte Steam : sans lui, le serveur de jeu ne peut rien vous remettre.',
            $source === 'athena' => 'Rien à faire : vous la recevez en jeu au début de la mission, ou depuis Ma couverture › Demander la mise à jour.',
            $source === 'game' => 'Rien à faire : elle vaut pour cette partie. Pour la retrouver à chaque mission, demandez à vos responsables de la préparer sur Athena.',
            default => 'Aucune couverture pour le moment. Vos responsables peuvent vous en attribuer une ; celle reçue en jeu apparaît ici si le téléphone ATAK est connecté à Athena.',
        };

        return [
            'steam_linked' => $steamLinked,
            'source' => $source,
            'updated_at' => $source === 'game' ? (string) ($gameCovers[0]['updated_at'] ?? '') : $prepared,
            'todo' => $todo,
        ];
    }

    /**
     * Sans nom : rien à garder. Tout est coupé à sa taille, le reste du corps est ignoré.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    private static function normalize(array $raw): ?array
    {
        $out = [];
        foreach (self::TEXT_FIELDS as $field => $max) {
            $out[$field] = self::text($raw[$field] ?? '', $max);
        }
        if ($out['name'] === '') {
            return null;
        }
        $out['contacts'] = [];
        foreach (array_slice(is_array($raw['contacts'] ?? null) ? array_values($raw['contacts']) : [], 0, self::MAX_CONTACTS) as $c) {
            if (is_array($c)) {
                $out['contacts'][] = [self::text($c[0] ?? '', 160), self::text($c[1] ?? '', 60), self::text($c[2] ?? '', 60)];
            }
        }
        $out['docs'] = [];
        foreach (array_slice(is_array($raw['docs'] ?? null) ? array_values($raw['docs']) : [], 0, self::MAX_DOCS) as $d) {
            if (is_array($d)) {
                $out['docs'][] = [self::text($d[0] ?? '', 60), self::text($d[1] ?? '', 80), self::text($d[2] ?? '', 40)];
            }
        }

        return $out;
    }

    private static function text(mixed $value, int $max): string
    {
        $s = is_scalar($value) ? mb_scrub((string) $value, 'UTF-8') : '';

        return mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]/', ' ', $s)), 0, $max);
    }
}
