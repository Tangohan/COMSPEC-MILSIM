<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Support\MemberName;
use App\Support\SseIdentityCatalog;

/**
 * Qui est le « titulaire » d'un papier du registre ? La colonne affiche le nom de l'identité qui porte le papier (sse_identities),
 * jamais celui d'un compte : une identité civile saisie une fois pour un membre est une copie de son état civil (user_profiles) qui
 * ne suit pas ses changements de prénom ou de nom, et une identité générée, d'emprunt ou de couverture n'a pas à lui ressembler.
 * Ce service dit de quelle nature est l'identité et, pour une identité civile rattachée à un compte, si son nom diffère encore
 * de l'état civil actuel du membre (indicateur « nom différent », avec le lien vers la fiche papiers du membre pour corriger).
 *
 * Lecture seule. Les natures cachées sans l'habilitation Confidentiel (couverture, falsifiée, alias) ne sont jamais nommées.
 */
final class SseIdentityHolderService
{
    public const MEMBER = 'member';
    public const SERVICE = 'service';
    public const SUBJECT = 'subject';

    public const NO_ISSUE = 'Ce titulaire est un sujet contrôlé ou remonté du terrain : on y consigne les papiers constatés, on n’en génère ni n’en émet. La génération est réservée aux identités d’un membre et aux couvertures ou faux préparés par le service.';

    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
    }

    /**
     * Qui est le titulaire d'une identité ? « member » : rattachée à un compte (user_id) ; « service » : couverture, alias ou faux
     * préparé par le service, ou toute identité fictive sans rattachement (ambiguë : on garde l'atelier, comme avant) ;
     * « subject » : sujet contrôlé ou remonté du terrain, soit une identité civile (sse_identities.kind = civil) rattachée à une
     * fiche personne (person_id) et à aucun compte. Une identité générée reste « service ».
     *
     * @param array<string, mixed> $identity user_id, person_id, kind
     */
    public static function holderType(array $identity): string
    {
        if ((int) ($identity['user_id'] ?? 0) > 0) {
            return self::MEMBER;
        }
        if ((string) ($identity['kind'] ?? '') === 'civil' && (int) ($identity['person_id'] ?? 0) > 0) {
            return self::SUBJECT;
        }

        return self::SERVICE;
    }

    /** Les papiers de ce titulaire se constatent seulement (aucune génération, validation, émission ni copie). */
    public static function isObservedOnly(array $identity): bool
    {
        return self::holderType($identity) === self::SUBJECT;
    }

    /** Authenticité constatée d'un papier (authentic | doubtful | fake) d'après son intégrité et son origine. */
    public static function authenticityOf(array $doc): string
    {
        if (($doc['st_origin'] ?? '') === 'forged' || ($doc['st_integrity'] ?? '') === 'counterfeit') {
            return 'fake';
        }

        return in_array($doc['st_integrity'] ?? '', ['altered', 'damaged'], true) ? 'doubtful' : 'authentic';
    }

    /**
     * Ajoute « holder » à chaque papier : kind_label (vide si caché), member_id, member_name, differs.
     *
     * @param list<array<string, mixed>> $documents lignes de SseIdDocumentRepository::list
     * @return list<array<string, mixed>>
     */
    public function annotate(int $tenantId, array $documents, bool $truth): array
    {
        $userIds = [];
        foreach ($documents as $d) {
            $uid = (int) ($d['identity']['user_id'] ?? 0);
            if ($uid > 0) {
                $userIds[$uid] = $uid;
            }
        }
        $members = $this->members($tenantId, array_values($userIds));
        foreach ($documents as $k => $d) {
            $identity = is_array($d['identity'] ?? null) ? $d['identity'] : [];
            $kind = (string) ($identity['kind'] ?? '');
            $secret = in_array($kind, MemberIdentityPapersService::SECRET, true);
            $uid = (int) ($identity['user_id'] ?? 0);
            $member = $uid > 0 ? ($members[$uid] ?? null) : null;
            $holder = [
                'kind_label' => $secret && !$truth ? '' : SseIdentityCatalog::label(SseIdentityCatalog::KINDS, $kind),
                'member_id' => null,
                'member_name' => '',
                'differs' => false,
            ];
            // Un membre rattaché à une identité cachée ne se révèle pas sans l'habilitation.
            if ($member !== null && (!$secret || $truth)) {
                $holder['member_id'] = $uid;
                $holder['member_name'] = $member['name'];
                $holder['differs'] = $kind === 'civil' && $member['name'] !== ''
                    && self::key((string) $identity['first_name'] . ' ' . (string) $identity['last_name']) !== self::key($member['name']);
            }
            $documents[$k]['holder'] = $holder;
        }

        return $documents;
    }

    /** Mots du nom, sans casse ni accents ni ordre : « GEORGIOU Yannis » et « Yannis Georgiou » sont le même nom. */
    public static function key(string $name): string
    {
        $words = preg_split("/[\s\-’']+/u", $name) ?: [];
        $words = array_values(array_filter(array_map(static fn (string $w): string => MemberName::letters($w), $words), static fn (string $w): bool => $w !== ''));
        sort($words);

        return implode(' ', $words);
    }

    /**
     * État civil actuel des membres (user_profiles), « Prénom NOM ».
     *
     * @param list<int> $userIds
     * @return array<int, array{name: string}>
     */
    private function members(int $tenantId, array $userIds): array
    {
        if ($tenantId < 1 || $userIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($userIds), '?'));
        try {
            $rows = $this->db->fetchAll(
                'SELECT u.id, p.first_name, p.last_name FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.tenant_id = ? AND u.id IN (' . $in . ')',
                array_merge([$tenantId], $userIds)
            );
        } catch (\Throwable $e) {
            error_log('Titulaire des papiers : état civil des membres indisponible : ' . $e->getMessage());

            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $first = trim((string) ($r['first_name'] ?? ''));
            $last = trim((string) ($r['last_name'] ?? ''));
            $out[(int) $r['id']] = ['name' => $first === '' && $last === '' ? '' : MemberName::full(['first_name' => $first, 'last_name' => $last])];
        }

        return $out;
    }
}
