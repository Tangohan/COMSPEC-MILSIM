<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SsePersonRepository;

/**
 * Contrôle N3 en ligne : interrogation des fichiers pour un papier présenté
 * (COMSPEC Link QueryIdDocument → GET /api/sse/identity/documents/lookup).
 *
 * Répond comme le ferait le fichier de l'État émetteur : connu ou non, valide, annulé, volé, expiré, titulaire sur une
 * liste. Ne révèle jamais la vérité de jeu (couverture, faux, vraie identité) : un faux n'existe pas dans les fichiers,
 * une couverture y est en règle. Seule exception : nom présenté différent du titulaire inscrit, et titulaire inscrit sur
 * une liste — son nom est alors donné, comme un vrai fichier le ferait.
 */
final class SseIdDocumentLookupService
{
    /** Ordre de gravité : le premier verdict trouvé l'emporte. */
    private const PRECEDENCE = ['hvt', 'recherche', 'vole', 'revoque', 'expire', 'signale', 'ras'];

    public function __construct(
        private ?SseIdDocumentRepository $documents = null,
        private ?SseCrossMatchService $cross = null,
        private ?SsePersonRepository $persons = null,
    ) {
        $this->documents ??= new SseIdDocumentRepository();
        $this->cross ??= new SseCrossMatchService();
        $this->persons ??= new SsePersonRepository();
    }

    /**
     * @param array{country?: string, type?: string, number?: string, last?: string, first?: string, dob?: string} $q
     * @return array{found: bool, verdict: string, ref: string, level: string, flags: list<string>, updated_at: ?string, holder?: array{last_name: string, first_name: string, level: string}}
     */
    public function lookup(int $tenantId, array $q): array
    {
        $country = strtoupper(trim((string) ($q['country'] ?? '')));
        $type = trim((string) ($q['type'] ?? ''));
        $number = SseIdDocumentRepository::normalizeNumber((string) ($q['number'] ?? ''));
        $last = trim((string) ($q['last'] ?? ''));
        $first = trim((string) ($q['first'] ?? ''));
        $dob = trim((string) ($q['dob'] ?? ''));

        $doc = ($country !== '' && $number !== '')
            ? $this->documents->findOfficialByNumber($tenantId, $country, $type, $number)
            : null;

        // Nom présenté par le sujet, confronté aux listes (c'est ce qu'il déclare, pas une vérité).
        $presentedHit = ($last !== '' || $first !== '') ? $this->topWatch($tenantId, $last, $first) : null;

        if ($doc === null) {
            $flags = ['unknown_document'];
            $verdict = 'inconnu';
            $level = '';
            $ref = '';
            if ($presentedHit !== null) {
                $flags[] = 'watchlist';
                $level = $presentedHit['level'];
                $verdict = $level === 'prioritaire' ? 'recherche' : 'signale';
                $ref = $presentedHit['ref'];
            }

            return ['found' => false, 'verdict' => $verdict, 'ref' => $ref, 'level' => $level, 'flags' => $flags, 'updated_at' => null];
        }

        $flags = [];
        $verdicts = [];
        $holderName = $doc['identity'];
        $mismatch = ($last !== '' && self::norm($last) !== self::norm((string) $holderName['last_name']))
            || ($first !== '' && self::norm($first) !== self::norm((string) $holderName['first_name']));
        if ($mismatch) {
            $flags[] = 'holder_mismatch';
            $verdicts[] = 'signale';
        }
        if ($dob !== '' && $holderName['birth_date'] !== null && self::normDate($dob) !== null
            && self::normDate($dob) !== $holderName['birth_date']) {
            $flags[] = 'dob_mismatch';
            $verdicts[] = 'signale';
        }

        if ($doc['workflow'] === 'revoked' || $doc['st_validity'] === 'revoked') {
            $flags[] = 'revoked';
            $verdicts[] = 'revoque';
        } elseif ($doc['st_validity'] === 'suspended') {
            $flags[] = 'suspended';
            $verdicts[] = 'revoque';
        }
        if ($doc['expired'] || $doc['st_validity'] === 'expired') {
            $flags[] = 'expired';
            $verdicts[] = 'expire';
        }
        if (in_array($doc['st_report'], ['stolen', 'lost'], true)) {
            $flags[] = $doc['st_report'];
            $verdicts[] = 'vole';
        }

        $level = '';
        // Titulaire inscrit : listes de surveillance sur le nom imprimé, report porté sur le papier, fiche personne.
        $holderHit = $this->topWatch($tenantId, (string) $holderName['last_name'], (string) $holderName['first_name']);
        $holderLevel = $holderHit['level'] ?? '';
        if ($doc['st_report'] === 'wanted') {
            $holderLevel = self::maxLevel($holderLevel, 'prioritaire');
        } elseif ($doc['st_report'] === 'watch') {
            $holderLevel = self::maxLevel($holderLevel, 'surveillance');
        }
        // Une couverture ou un faux n'est pas relié à la fiche de la personne réelle dans les fichiers de l'État.
        if (!in_array($doc['identity']['kind'], ['cover', 'forged'], true) && $doc['st_origin'] !== 'cover') {
            $personId = (int) ($doc['identity']['person_id'] ?? 0);
            if ($personId > 0 && $this->personIsPriority($personId, $tenantId)) {
                $flags[] = 'person_priority';
                $holderLevel = 'hvt';
            }
        }
        if ($holderLevel !== '') {
            $level = $holderLevel;
            if (!in_array('person_priority', $flags, true)) {
                $flags[] = 'watchlist';
            }
        }
        if ($presentedHit !== null && $mismatch) {
            $level = self::maxLevel($level, $presentedHit['level']);
            if (!in_array('watchlist', $flags, true)) {
                $flags[] = 'watchlist';
            }
        }
        if ($level !== '') {
            $verdicts[] = match ($level) {
                'hvt' => 'hvt',
                'prioritaire' => 'recherche',
                default => 'signale',
            };
        }

        $verdict = 'ras';
        foreach (self::PRECEDENCE as $v) {
            if (in_array($v, $verdicts, true)) {
                $verdict = $v;
                break;
            }
        }

        $out = [
            'found' => true,
            'verdict' => $verdict,
            'ref' => (string) $doc['ref'],
            'level' => $level,
            'flags' => array_values(array_unique($flags)),
            'updated_at' => self::iso($doc['updated_at'] ?? $doc['issued_at'] ?? $doc['created_at'] ?? null),
        ];
        if ($mismatch && $holderLevel !== '') {
            $out['holder'] = [
                'last_name' => (string) $holderName['last_name'],
                'first_name' => (string) $holderName['first_name'],
                'level' => $holderLevel,
            ];
        }

        return $out;
    }

    /**
     * @return array{level: string, ref: string}|null
     */
    private function topWatch(int $tenantId, string $last, string $first): ?array
    {
        if (trim($last . $first) === '') {
            return null;
        }
        $hits = $this->cross->matchOne([
            'last_name' => $last,
            'first_name' => $first,
            'display_name' => trim($first . ' ' . $last),
        ], $tenantId);
        $top = $hits[0] ?? null;
        if (!is_array($top) || !is_array($top['entry'] ?? null)) {
            return null;
        }
        $entry = $top['entry'];

        return [
            'level' => ($entry['threat_level'] ?? '') === 'prioritaire' ? 'prioritaire' : 'surveillance',
            'ref' => 'WL-' . (int) ($entry['id'] ?? 0),
        ];
    }

    private function personIsPriority(int $personId, int $tenantId): bool
    {
        $p = $this->persons->findById($personId, $tenantId);

        return is_array($p) && SsePersonRepository::normalizeStatus((string) ($p['status'] ?? '')) === SsePersonRepository::STATUS_PRIORITAIRE;
    }

    private static function maxLevel(string $a, string $b): string
    {
        $rank = ['' => 0, 'surveillance' => 1, 'prioritaire' => 2, 'hvt' => 3];

        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }

    /** Comparaison de noms comme une bande MRZ : sans accents, casse, espaces ni tirets. */
    private static function norm(string $s): string
    {
        return str_replace('<', '', SseMrzService::sanitize($s));
    }

    /** Date présentée (AAAA-MM-JJ ou JJ/MM/AAAA) → AAAA-MM-JJ ; autre forme : non comparée. */
    private static function normDate(string $s): ?string
    {
        $s = trim($s);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) === 1) {
            return $m[0];
        }
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m) === 1) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return null;
    }

    private static function iso(mixed $dt): ?string
    {
        $s = trim((string) $dt);
        if ($s === '') {
            return null;
        }

        return str_replace(' ', 'T', substr($s, 0, 19)) . 'Z';
    }
}
