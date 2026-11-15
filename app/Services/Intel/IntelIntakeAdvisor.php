<?php

declare(strict_types=1);

namespace App\Services\Intel;

use App\Support\LazyDatabaseConnection;
use App\Support\SseFieldNoteCatalog;
use PDO;

/**
 * Propositions automatiques sur une remontée (« Proposé par Cerbère ») : type, thèmes, urgence, attribution,
 * doublons probables, destinataires d'une transmission.
 *
 * Règles simples, chacune donnée avec sa raison en une ligne. Rien n'est écrit ici : une proposition n'est appliquée
 * que par le clic d'un traitant, par les mêmes actions que la saisie à la main (type, données, attribution).
 */
class IntelIntakeAdvisor
{
    use LazyDatabaseConnection;

    /** Échelons et cellules proposés pour une transmission (les dossiers SSE ouverts s'y ajoutent). */
    public const ECHELONS = [
        'Commandement de l’opération',
        'Cellule renseignement (S2)',
        'Cellule opérations (S3)',
        'Cellule logistique (S4)',
        'Chef de section',
        'Bureau SSE',
        'Échelon supérieur',
    ];

    /** Mots qui orientent le type d'une fiche (sans accents, minuscules). */
    private const KIND_WORDS = [
        'FRT' => ['vehicule', 'pick-up', 'pickup', 'camion', 'arme', 'munition', 'lance-roquette', 'materiel', 'immatricul', 'plaque', 'marquage', 'antenne', 'engin', 'caisse', 'drone'],
        'FRC' => ['entretien', 'discute', 'discussion', 'rencontre', 'chef de village', 'informateur', 'interlocuteur', 'nous a dit', 'a declare', 'temoin'],
        'FRA' => ['population', 'ambiance', 'tension', 'hostile', 'accueil', 'habitants', 'rumeur', 'climat', 'manifestation'],
    ];

    /** Mots qui orientent les thèmes d'une fiche. */
    private const THEME_WORDS = [
        'TERROR' => ['attentat', 'engin explosif', 'ied', 'piege', 'kamikaze', 'otage'],
        'CBRNE' => ['chimique', 'radiolog', 'nrbc', 'gaz', 'biologique'],
        'ARMEMENT' => ['arme', 'munition', 'lance-roquette', 'fusil', 'mortier', 'explosif', 'grenade'],
        'PERSON' => ['individu', 'homme arme', 'hommes armes', 'chef', 'suspect', 'cible'],
        'LOGIST' => ['depot', 'caisse', 'carburant', 'ravitaillement', 'stock', 'entrepos', 'hangar', 'convoi'],
        'COMMS' => ['radio', 'antenne', 'frequence', 'telephone', 'relais'],
        'INFRA' => ['pont', 'route', 'batiment', 'centrale', 'barrage', 'piste'],
        'MOUV' => ['deplacement', 'convoi', 'colonne', 'se dirige', 'patrouille', 'franchi'],
        'SECUR' => ['garde', 'surveill', 'sentinelle', 'checkpoint', 'barrage routier', 'poste de controle'],
        'CIVIL' => ['population', 'habitants', 'civil', 'village', 'marche'],
    ];

    private const URGENT_WORDS = [
        'critical' => ['imminent', 'en cours', 'tir nourri', 'embuscade', 'otage', 'attentat'],
        'high' => ['engin explosif', 'ied', 'colis suspect', 'blesse', 'hommes armes', 'homme arme', 'arme', 'menace', 'explosif'],
    ];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /** Texte comparé sans accents ni casse. */
    public static function fold(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => "'",
        ]);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** @param array<string, mixed> $item remontée lue par IntelIntakeService::find */
    private static function haystack(array $item): string
    {
        $row = is_array($item['row'] ?? null) ? $item['row'] : [];
        $parts = [];
        foreach (['title', 'body', 'summary', 'details', 'remarks', 'place_label', 'location_description'] as $col) {
            $parts[] = (string) ($row[$col] ?? '');
        }

        return self::fold(implode(' ', $parts));
    }

    /**
     * @param list<string> $words
     * @return list<string> mots trouvés
     */
    private static function hits(string $haystack, array $words): array
    {
        return array_values(array_filter($words, static fn (string $w): bool => str_contains($haystack, $w)));
    }

    /**
     * Propositions qui s'écrivent dans la remontée : type, thèmes, urgence. Liste vide si tout est déjà cohérent.
     *
     * @param array<string, mixed> $item
     * @return list<array{key: string, label: string, value: string, value_label: string, reason: string, action: string, fields: array<string, string>}>
     */
    public function proposals(array $item): array
    {
        $out = [];
        $source = (string) ($item['source'] ?? '');
        $row = is_array($item['row'] ?? null) ? $item['row'] : [];
        $text = self::haystack($item);
        if ($source === 'fiche') {
            $best = '';
            $bestHits = [];
            foreach (self::KIND_WORDS as $kind => $words) {
                $found = self::hits($text, $words);
                if (count($found) > count($bestHits)) {
                    $best = $kind;
                    $bestHits = $found;
                }
            }
            if ($best !== '' && count($bestHits) >= 2 && $best !== (string) $item['type']) {
                $out[] = [
                    'key' => 'type', 'label' => 'Type de fiche', 'value' => $best,
                    'value_label' => $best . ' · ' . SseFieldNoteCatalog::kindLabel($best),
                    'reason' => 'Le texte parle de : ' . implode(', ', array_slice($bestHits, 0, 3)) . '.',
                    'action' => 'type', 'fields' => ['type' => $best],
                ];
            }
            $current = SseFieldNoteCatalog::normalizeThemes($row['themes'] ?? '');
            $themes = [];
            $why = [];
            foreach (self::THEME_WORDS as $code => $words) {
                $found = self::hits($text, $words);
                if ($found !== [] && !in_array($code, $current, true)) {
                    $themes[] = $code;
                    $why[] = $found[0];
                }
            }
            $room = SseFieldNoteCatalog::THEMES_MAX - count($current);
            $themes = array_slice($themes, 0, max(0, $room));
            if ($themes !== []) {
                $out[] = [
                    'key' => 'themes', 'label' => 'Thèmes', 'value' => implode(',', array_merge($current, $themes)),
                    'value_label' => implode(', ', array_map(static fn (string $c): string => SseFieldNoteCatalog::themeLabel($c), $themes)),
                    'reason' => 'Le texte parle de : ' . implode(', ', array_slice($why, 0, count($themes))) . '.',
                    'action' => 'themes', 'fields' => ['themes' => implode(',', array_merge($current, $themes))],
                ];
            }
        }
        $level = IntelIntakePresenter::urgencyLevel((string) ($item['priority'] ?? ''));
        $rank = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];
        foreach (self::URGENT_WORDS as $wanted => $words) {
            $found = self::hits($text, $words);
            if ($found !== [] && $rank[$wanted] > $rank[$level]) {
                $code = IntelIntakePresenter::URGENCY_LEVELS[$wanted][$source === 'cr' ? 1 : 0];
                $field = $source === 'cr' ? 'priority' : 'urgency';
                $out[] = [
                    'key' => 'urgence', 'label' => $source === 'cr' ? 'Priorité' : 'Urgence', 'value' => $code,
                    'value_label' => IntelIntakePresenter::urgencyLabel($source, $code),
                    'reason' => 'Le texte parle de : ' . implode(', ', array_slice($found, 0, 3)) . '.',
                    'action' => 'edit', 'fields' => [$field => $code],
                ];
                break;
            }
        }

        return $out;
    }

    /**
     * Qui proposer pour traiter cette remontée : le dernier traitant de ce joueur, sinon la personne qui a le moins
     * de remontées ouvertes parmi celles qui en traitent déjà.
     *
     * @param array<string, mixed> $item
     * @return array{user_id: int, label: string, reason: string}|null
     */
    public function assignee(int $tenantId, array $item): ?array
    {
        if (!empty($item['assignee'])) {
            return null;
        }
        $sender = is_array($item['sender'] ?? null) ? $item['sender'] : [];
        $callsign = trim((string) ($sender['callsign'] ?? ''));
        try {
            if ($callsign !== '') {
                $st = $this->pdo()->prepare(
                    "SELECT s.assignee_user_id AS uid FROM intel_intake_state s
                     LEFT JOIN sse_field_notes n ON s.source = 'fiche' AND n.id = s.source_id AND n.tenant_id = s.tenant_id
                     LEFT JOIN atak_tactical_reports r ON s.source = 'cr' AND r.id = s.source_id AND r.tenant_id = s.tenant_id
                     WHERE s.tenant_id = ? AND s.assignee_user_id IS NOT NULL AND NOT (s.source = ? AND s.source_id = ?)
                       AND (LOWER(n.author_label) = LOWER(?) OR LOWER(r.submitter_callsign) = LOWER(?))
                     ORDER BY s.assigned_at DESC, s.id DESC LIMIT 1"
                );
                $st->execute([$tenantId, (string) $item['source'], (int) $item['id'], $callsign, $callsign]);
                $uid = (int) $st->fetchColumn();
                if ($uid > 0 && ($label = $this->memberLabel($tenantId, $uid)) !== null) {
                    return ['user_id' => $uid, 'label' => $label, 'reason' => 'A traité la dernière remontée de ' . $callsign . '.'];
                }
            }
            $st = $this->pdo()->prepare(
                "SELECT e.actor_user_id AS uid,
                        (SELECT COUNT(*) FROM intel_intake_state s WHERE s.tenant_id = e.tenant_id AND s.assignee_user_id = e.actor_user_id
                            AND s.deleted_at IS NULL AND s.state IN ('a_traiter', 'prise_en_compte', 'en_cours')) AS open_load
                 FROM intel_intake_events e
                 WHERE e.tenant_id = ? AND e.actor_user_id IS NOT NULL AND e.kind IN ('state', 'ack', 'assign')
                 GROUP BY e.actor_user_id ORDER BY open_load ASC, MAX(e.created_at) DESC LIMIT 5"
            );
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $uid = (int) $r['uid'];
                if (($label = $this->memberLabel($tenantId, $uid)) !== null) {
                    $n = (int) $r['open_load'];

                    return ['user_id' => $uid, 'label' => $label, 'reason' => $n === 0 ? 'Traite les remontées et n’en a aucune ouverte.' : 'Charge la plus faible : ' . $n . ' remontée' . ($n > 1 ? 's' : '') . ' ouverte' . ($n > 1 ? 's' : '') . '.'];
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function memberLabel(int $tenantId, int $userId): ?string
    {
        $st = $this->pdo()->prepare("SELECT display_name, callsign FROM users WHERE id = ? AND tenant_id = ? AND (status IS NULL OR status NOT IN ('banned', 'disabled', 'deleted')) LIMIT 1");
        $st->execute([$userId, $tenantId]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return null;
        }

        return (string) (($u['callsign'] ?? '') ?: ($u['display_name'] ?? '') ?: 'Membre #' . $userId);
    }

    /**
     * Remontées proches : même émetteur, même lieu ou même carroyage, à moins de 24 h. Les plus proches d'abord.
     *
     * @param array<string, mixed> $item
     * @return list<array{source: string, id: int, ref: string, title: string, at: string, reason: string}>
     */
    public function duplicates(int $tenantId, array $item, int $limit = 5): array
    {
        $row = is_array($item['row'] ?? null) ? $item['row'] : [];
        $t = strtotime((string) ($item['at'] ?? '')) ?: time();
        $from = date('Y-m-d H:i:s', $t - 86400);
        $to = date('Y-m-d H:i:s', $t + 86400);
        $callsign = self::fold((string) ($item['author'] ?? ''));
        $grid = self::fold((string) ($row['grid_reference'] ?? ''));
        $place = self::fold((string) ($row['place_label'] ?? $row['location_description'] ?? ''));
        $rows = [];
        try {
            $st = $this->pdo()->prepare(
                "SELECT 'fiche' AS source, n.id, n.reference_code AS ref, COALESCE(NULLIF(n.title, ''), n.body) AS title, n.created_at AS at, n.author_label AS author, n.grid_reference AS grid, n.place_label AS place
                 FROM sse_field_notes n LEFT JOIN intel_intake_state s ON s.tenant_id = n.tenant_id AND s.source = 'fiche' AND s.source_id = n.id
                 WHERE n.tenant_id = ? AND n.status <> 'brouillon' AND s.deleted_at IS NULL AND n.created_at BETWEEN ? AND ? ORDER BY n.created_at DESC LIMIT 60"
            );
            $st->execute([$tenantId, $from, $to]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $st = $this->pdo()->prepare(
                "SELECT 'cr' AS source, r.id, COALESCE(NULLIF(r.report_number, ''), 'CR-' || r.id) AS ref, COALESCE(NULLIF(r.summary, ''), r.details) AS title, r.created_at AS at, r.submitter_callsign AS author, r.grid_reference AS grid, r.location_description AS place
                 FROM atak_tactical_reports r WHERE r.tenant_id = ? AND r.deleted_at IS NULL AND r.created_at BETWEEN ? AND ? ORDER BY r.created_at DESC LIMIT 60"
            );
            if ($this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
                $st = $this->pdo()->prepare(
                    "SELECT 'cr' AS source, r.id, COALESCE(NULLIF(r.report_number, ''), CONCAT('CR-', r.id)) AS ref, COALESCE(NULLIF(r.summary, ''), r.details) AS title, r.created_at AS at, r.submitter_callsign AS author, r.grid_reference AS grid, r.location_description AS place
                     FROM atak_tactical_reports r WHERE r.tenant_id = ? AND r.deleted_at IS NULL AND r.created_at BETWEEN ? AND ? ORDER BY r.created_at DESC LIMIT 60"
                );
            }
            $st->execute([$tenantId, $from, $to]);
            $rows = array_merge($rows, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (\Throwable) {
        }
        $out = [];
        foreach ($rows as $r) {
            if ((string) $r['source'] === (string) $item['source'] && (int) $r['id'] === (int) $item['id']) {
                continue;
            }
            $why = [];
            if ($callsign !== '' && self::fold((string) ($r['author'] ?? '')) === $callsign) {
                $why[] = 'même émetteur';
            }
            if ($grid !== '' && self::fold((string) ($r['grid'] ?? '')) === $grid) {
                $why[] = 'même carroyage';
            } elseif ($place !== '' && self::fold((string) ($r['place'] ?? '')) === $place) {
                $why[] = 'même lieu';
            }
            if ($why === []) {
                continue;
            }
            $gap = abs((strtotime((string) $r['at']) ?: $t) - $t);
            $why[] = $gap < 3600 ? 'à moins d’une heure' : 'le même jour';
            $out[] = [
                'source' => (string) $r['source'], 'id' => (int) $r['id'], 'ref' => (string) $r['ref'],
                'title' => mb_substr(trim((string) ($r['title'] ?? '')), 0, 110), 'at' => (string) $r['at'],
                'reason' => ucfirst(implode(', ', $why)) . '.', 'score' => count($why) * 100000 - $gap,
            ];
        }
        usort($out, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(static function (array $o): array {
            unset($o['score']);

            return $o;
        }, array_slice($out, 0, $limit));
    }

    /**
     * Destinataires possibles d'une transmission : échelons et cellules, puis dossiers SSE de la communauté.
     * Recherche sans accents ni casse. Sans recherche : les échelons et les dossiers les plus récents.
     *
     * @return list<array{value: string, label: string, kind: string}>
     */
    public function forwardTargets(int $tenantId, string $query = '', int $limit = 12): array
    {
        $q = self::fold($query);
        $out = [];
        foreach (self::ECHELONS as $e) {
            if ($q === '' || str_contains(self::fold($e), $q)) {
                $out[] = ['value' => $e, 'label' => $e, 'kind' => 'Échelon'];
            }
        }
        try {
            $st = $this->pdo()->prepare("SELECT reference_code, title FROM sse_cases WHERE tenant_id = ? AND (status IS NULL OR status NOT IN ('clos', 'archive')) ORDER BY id DESC LIMIT 200");
            $st->execute([$tenantId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $c) {
                $ref = (string) ($c['reference_code'] ?? '');
                $label = $ref . ' — ' . (string) ($c['title'] ?? '');
                if ($ref !== '' && ($q === '' || str_contains(self::fold($label), $q))) {
                    $out[] = ['value' => $ref, 'label' => $label, 'kind' => 'Dossier SSE'];
                }
            }
        } catch (\Throwable) {
        }

        return array_slice($out, 0, $limit);
    }

    /** Destinataire validé : un échelon de la liste ou la référence d'un dossier de la communauté. Null sinon. */
    public function validForward(int $tenantId, string $target): ?string
    {
        $t = self::fold($target);
        if ($t === '') {
            return null;
        }
        foreach (self::ECHELONS as $e) {
            if (self::fold($e) === $t) {
                return $e;
            }
        }
        // « SSE-2026-0007 — Titre » ou « Dossier SSE SSE-2026-0007 » : on garde la référence.
        $candidates = array_filter([trim($target), trim((string) preg_replace('/^dossier\s+sse\s+/iu', '', trim(explode(' — ', $target)[0])))]);
        try {
            foreach ($candidates as $ref) {
                $st = $this->pdo()->prepare('SELECT reference_code FROM sse_cases WHERE tenant_id = ? AND UPPER(reference_code) = ? LIMIT 1');
                $st->execute([$tenantId, mb_strtoupper($ref)]);
                $found = $st->fetchColumn();
                if (is_string($found) && $found !== '') {
                    return $found;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
