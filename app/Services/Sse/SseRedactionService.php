<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseCaseRepository;

/**
 * Caviardage et déclassification d'un dossier SSE.
 *
 * Deux mécanismes, volontairement séparés :
 *
 *   - **déclassification** — on demande une version du dossier diffusable à un
 *     niveau donné, et tout ce qui est au-dessus de ce niveau part au noir
 *     automatiquement. C'est une règle, elle ne s'oublie pas.
 *   - **caviardage manuel** — l'analyste noircit une zone précise sur une fiche
 *     précise, quel que soit le niveau. C'est un jugement, il est tracé et motivé.
 *
 * ## Le point qui compte
 *
 * Le texte caviardé n'est **jamais envoyé au navigateur**. Un trait noir obtenu en
 * CSS (`color: black; background: black`) laisse le texte dans la page : il ressort
 * au copier-coller, dans le code source, dans un lecteur d'écran et dans le cache du
 * navigateur. Autant ne rien caviarder. Ici la substitution est faite côté serveur,
 * la chaîne d'origine ne quitte pas la base.
 *
 * La longueur de la barre est **quantifiée**. Une barre exactement proportionnelle
 * révèle la longueur du nom : sur un dossier à trois personnes, cela suffit souvent
 * à savoir laquelle est laquelle.
 */
final class SseRedactionService
{
    /** Caractère de caviardage — bloc plein, rendu identique en monospace. */
    private const BLOCK = '█';

    /** Barres bornées : ni un trait de deux caractères, ni une ligne entière. */
    private const BAR_MIN = 4;
    private const BAR_MAX = 24;
    private const BAR_STEP = 4;

    /**
     * Échelle de diffusion, du plus large au plus restreint.
     *
     * @var array<string, int>
     */
    public const LEVELS = [
        SseCaseRepository::CLASS_INTERNAL => 0,
        SseCaseRepository::CLASS_COMMAND => 1,
        SseCaseRepository::CLASS_CONFIDENTIAL => 2,
        SseCaseRepository::CLASS_RESTRICTED => 3,
    ];

    /**
     * Catégories caviardables et niveau minimal pour les lire en clair.
     *
     * @var array<string, array{label: string, level: string, help: string}>
     */
    public const CATEGORIES = [
        'identite' => [
            'label' => 'Identité',
            'level' => SseCaseRepository::CLASS_CONFIDENTIAL,
            'help' => 'Nom, prénom, alias, date et lieu de naissance, nationalité déclarée, pièce d’identité.',
        ],
        'lieu' => [
            'label' => 'Lieu',
            'level' => SseCaseRepository::CLASS_COMMAND,
            'help' => 'Références de grille, désignation des sites, pièces où les objets ont été trouvés.',
        ],
        'biometrie' => [
            'label' => 'Biométrie',
            'level' => SseCaseRepository::CLASS_CONFIDENTIAL,
            'help' => 'Références de relevés, indices de qualité, références de dossier antérieur.',
        ],
        'source' => [
            'label' => 'Source',
            'level' => SseCaseRepository::CLASS_RESTRICTED,
            'help' => 'Indicatif de l’opérateur qui a recueilli, équipe, identifiant de terminal, signature.',
        ],
        'horodatage' => [
            'label' => 'Horodatage',
            'level' => SseCaseRepository::CLASS_COMMAND,
            'help' => 'Heures précises de recueil — un enchaînement d’horaires reconstitue un itinéraire.',
        ],
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function levelRank(string $level): int
    {
        return self::LEVELS[$level] ?? self::LEVELS[SseCaseRepository::CLASS_COMMAND];
    }

    public static function levelLabel(string $level): string
    {
        return SseCaseRepository::CLASSIFICATION_LABELS[$level]
            ?? SseCaseRepository::CLASSIFICATION_LABELS[SseCaseRepository::CLASS_COMMAND];
    }

    public static function categoryLabel(string $key): string
    {
        return self::CATEGORIES[$key]['label'] ?? 'Élément';
    }

    /** Une catégorie est-elle lisible en clair pour une diffusion à ce niveau ? */
    public static function visibleAt(string $category, string $releaseLevel): bool
    {
        $required = self::CATEGORIES[$category]['level'] ?? SseCaseRepository::CLASS_COMMAND;

        return self::levelRank($releaseLevel) >= self::levelRank($required);
    }

    /**
     * Barre de caviardage. La longueur est quantifiée pour ne pas trahir celle
     * du texte d'origine.
     */
    public static function bar(?string $original = null): string
    {
        $len = $original === null ? 0 : mb_strlen(trim($original));
        if ($len < 1) {
            $len = self::BAR_MIN;
        }
        $quantised = (int) (ceil($len / self::BAR_STEP) * self::BAR_STEP);
        $quantised = max(self::BAR_MIN, min(self::BAR_MAX, $quantised));

        return str_repeat(self::BLOCK, $quantised);
    }

    /**
     * Applique le caviardage à l'ensemble de données du compte rendu.
     *
     * Le seul point d'entrée : les deux comptes rendus et les écrans de
     * déclassification passent par ici, donc une catégorie oubliée est oubliée
     * partout — pas caviardée d'un côté et en clair de l'autre.
     *
     * @param array{case: array<string,mixed>, people: list<array<string,mixed>>, sites: list<array<string,mixed>>} $data
     * @param list<array<string,mixed>> $manual Caviardages posés à la main.
     * @return array{case: array<string,mixed>, people: list<array<string,mixed>>, sites: list<array<string,mixed>>}
     */
    public function apply(array $data, string $releaseLevel, array $manual = []): array
    {
        // Index des caviardages manuels : [type][id][champ] => motif
        $index = [];
        foreach ($manual as $m) {
            $type = (string) ($m['target_type'] ?? '');
            $id = (int) ($m['target_id'] ?? 0);
            $field = (string) ($m['field'] ?? '');
            if ($type === '' || $field === '') {
                continue;
            }
            $index[$type][$id][$field] = (string) ($m['reason'] ?? '');
        }

        $hide = function (string $category, string $type, int $id, string $field) use ($releaseLevel, $index): bool {
            if (isset($index[$type][$id][$field])) {
                return true;
            }

            return !self::visibleAt($category, $releaseLevel);
        };

        // --- Personnes ---
        foreach ($data['people'] as $i => $person) {
            $pid = (int) ($person['id'] ?? 0);

            foreach ([
                'last_name' => 'identite',
                'first_name' => 'identite',
                'alias' => 'identite',
                'display_name' => 'identite',
                'birth_date' => 'identite',
                'birth_place' => 'identite',
                'nationality' => 'identite',
                'language_spoken' => 'identite',
                'id_document_number' => 'identite',
                'grid_reference' => 'lieu',
                'submitter_callsign' => 'source',
            ] as $field => $category) {
                if (!array_key_exists($field, $person)) {
                    continue;
                }
                if ($hide($category, 'person', $pid, $field)) {
                    $data['people'][$i][$field] = self::bar((string) ($person[$field] ?? ''));
                    $data['people'][$i]['_redacted'][$field] = $category;
                }
            }

            // Relevés : la nature du relevé reste lisible (« empreintes »), la
            // référence de laboratoire non — c'est elle qui permet de recouper.
            if (is_array($person['biometric_samples'] ?? null)) {
                foreach ($person['biometric_samples'] as $j => $sample) {
                    if ($hide('biometrie', 'person', $pid, 'biometric_samples')) {
                        $data['people'][$i]['biometric_samples'][$j]['lab_reference']
                            = self::bar((string) ($sample['lab_reference'] ?? ''));
                        $data['people'][$i]['_redacted']['biometric_samples'] = 'biometrie';
                    }
                }
            }

            if (is_array($person['identity_query'] ?? null)
                && $hide('biometrie', 'person', $pid, 'identity_query')) {
                $data['people'][$i]['identity_query']['record_ref']
                    = self::bar((string) ($person['identity_query']['record_ref'] ?? ''));
                $data['people'][$i]['_redacted']['identity_query'] = 'biometrie';
            }

            if (is_array($person['signature'] ?? null)
                && $hide('source', 'person', $pid, 'signature')) {
                foreach (['callsign', 'terminal_id'] as $k) {
                    if (isset($data['people'][$i]['signature'][$k])) {
                        $data['people'][$i]['signature'][$k]
                            = self::bar((string) $person['signature'][$k]);
                    }
                }
                $data['people'][$i]['_redacted']['signature'] = 'source';
            }

            if ($hide('horodatage', 'person', $pid, 'created_at') && isset($person['created_at'])) {
                // La date reste, l'heure part : savoir « le 14 » n'a pas la même
                // valeur que savoir « le 14 à 03h12 ».
                $data['people'][$i]['created_at'] = substr((string) $person['created_at'], 0, 10) . ' ' . self::bar('00:00');
                $data['people'][$i]['_redacted']['created_at'] = 'horodatage';
            }
        }

        // --- Sites ---
        foreach ($data['sites'] as $i => $site) {
            $sid = (int) ($site['id'] ?? 0);

            foreach ([
                'name' => 'lieu',
                'grid_reference' => 'lieu',
                'submitter_callsign' => 'source',
                'team_label' => 'source',
            ] as $field => $category) {
                if (!array_key_exists($field, $site)) {
                    continue;
                }
                if ($hide($category, 'site', $sid, $field)) {
                    $data['sites'][$i][$field] = self::bar((string) ($site[$field] ?? ''));
                    $data['sites'][$i]['_redacted'][$field] = $category;
                }
            }

            if (is_array($site['rooms'] ?? null) && $hide('lieu', 'site', $sid, 'rooms')) {
                foreach ($site['rooms'] as $j => $room) {
                    $data['sites'][$i]['rooms'][$j]['label'] = self::bar((string) ($room['label'] ?? ''));
                }
                $data['sites'][$i]['_redacted']['rooms'] = 'lieu';
            }

            // Les saisies restent : c'est le matériel, il fait le renseignement.
            // Seule leur localisation fine suit le régime du lieu.
            if (is_array($site['seizures'] ?? null) && $hide('lieu', 'site', $sid, 'rooms')) {
                foreach ($site['seizures'] as $j => $seizure) {
                    if (!empty($seizure['notes'])) {
                        $data['sites'][$i]['seizures'][$j]['notes'] = self::bar((string) $seizure['notes']);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Caviardages posés à la main sur un dossier.
     *
     * @return list<array<string, mixed>>
     */
    public function listForCase(int $caseId, int $tenantId): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT * FROM sse_redactions WHERE tenant_id = :t AND case_id = :c ORDER BY id ASC',
                ['t' => $tenantId, 'c' => $caseId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function add(int $tenantId, int $caseId, array $data): bool
    {
        $type = (string) ($data['target_type'] ?? '');
        $field = trim((string) ($data['field'] ?? ''));
        $id = (int) ($data['target_id'] ?? 0);
        if (!in_array($type, ['person', 'site'], true) || $field === '' || $id < 1) {
            return false;
        }

        try {
            $this->db->execute(
                'INSERT INTO sse_redactions
                    (tenant_id, case_id, target_type, target_id, field, category, reason, author_label)
                 VALUES (:t, :c, :tt, :ti, :f, :cat, :r, :a)
                 ON DUPLICATE KEY UPDATE reason = VALUES(reason), author_label = VALUES(author_label)',
                [
                    't' => $tenantId,
                    'c' => $caseId,
                    'tt' => $type,
                    'ti' => $id,
                    'f' => $field,
                    'cat' => isset(self::CATEGORIES[(string) ($data['category'] ?? '')])
                        ? (string) $data['category']
                        : 'identite',
                    'r' => ($data['reason'] ?? null) ?: null,
                    'a' => ($data['author_label'] ?? null) ?: null,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function remove(int $id, int $tenantId): bool
    {
        try {
            return $this->db->execute(
                'DELETE FROM sse_redactions WHERE id = :id AND tenant_id = :t',
                ['id' => $id, 't' => $tenantId]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Ce que la déclassification à ce niveau va noircir, dit en clair avant de
     * produire le document. On ne découvre pas ce qu'on a diffusé après l'avoir
     * diffusé.
     *
     * @return array{visible: list<string>, hidden: list<string>}
     */
    public static function summarise(string $releaseLevel): array
    {
        $visible = [];
        $hidden = [];
        foreach (self::CATEGORIES as $key => $meta) {
            if (self::visibleAt($key, $releaseLevel)) {
                $visible[] = $meta['label'];
            } else {
                $hidden[] = $meta['label'];
            }
        }

        return ['visible' => $visible, 'hidden' => $hidden];
    }
}
