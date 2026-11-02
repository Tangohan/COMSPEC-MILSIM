<?php

declare(strict_types=1);

namespace App\Services\Academy;

use App\Services\Personnel\QualificationReferenceWriter;
use PDO;

/**
 * Qualifications Athena Academy délivrées par les cours intégrés (JTAC, cours inspirés des standards OTAN) :
 * chaque cours déclare sa qualification (clé « qualification » d’AcademyBuiltInCourses). À la validation du dernier
 * chapitre, la fiche est créée si besoin dans le référentiel de la communauté (code ACAD-…, catégorie « Academy »,
 * émetteur « Athena Academy »), réglée en mode « Formation Academy » (critère {type: academy, course_key}), puis la
 * règle automatique l’attribue (QualificationAwardRuleService::runAuto). Une fiche existante n’est jamais modifiée :
 * la communauté peut la renommer, changer son mode d’obtention ou désactiver la règle.
 */
final class AcademyBuiltInQualifications
{
    public const ISSUER_NAME = 'Athena Academy';
    public const ISSUER_SHORT = 'ATH-ACADEMY';
    public const CATEGORY_NAME = 'Academy';

    public function __construct(private QualificationReferenceWriter $writer)
    {
    }

    public static function forPdo(PDO $pdo): self
    {
        return new self(new QualificationReferenceWriter($pdo));
    }

    public function ready(): bool
    {
        return $this->writer->has('personnel_qualification_definitions') && $this->writer->has('qualification_award_rules')
            && $this->writer->has('academy_learners');
    }

    /** @return array<string, mixed> règle « réussir ce cours intégré » */
    public static function rule(string $courseKey): array
    {
        return ['op' => 'all', 'groups' => [['op' => 'all', 'items' => [['type' => 'academy', 'course_key' => $courseKey]]]]];
    }

    /** @return array<string, string> clé du cours => titre (langue du lecteur), cours qui délivrent une qualification */
    public static function courseTitles(string $lang): array
    {
        $lang = str_starts_with(strtolower($lang), 'en') ? 'en' : 'fr';
        $out = [];
        foreach (AcademyBuiltInCourses::qualifications() as $key => $q) {
            $course = AcademyBuiltInCourses::find($key);
            $out[$key] = (string) ($course['title'][$lang] ?? $key);
        }

        return $out;
    }

    /**
     * Cours intégrés terminés (dernier chapitre validé), par membre : membre => clé du cours => true.
     *
     * @return array<int, array<string, bool>>
     */
    public function completions(int $tenantId): array
    {
        $keys = array_keys(AcademyBuiltInCourses::qualifications());
        if ($keys === [] || !$this->writer->has('academy_learners')) {
            return [];
        }
        try {
            $st = $this->writer->pdo()->prepare(
                'SELECT user_id, course_key, state_json FROM academy_learners WHERE tenant_id = ? AND course_key IN ('
                . implode(',', array_fill(0, count($keys), '?')) . ") AND state_json LIKE '%\"completed\":true%'"
            );
            $st->execute(array_merge([$tenantId], $keys));
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $state = json_decode((string) $row['state_json'], true);
            if (is_array($state) && ($state['completed'] ?? false) === true) {
                $out[(int) $row['user_id']][(string) $row['course_key']] = true;
            }
        }

        return $out;
    }

    /**
     * Fiche du référentiel de la qualification d’un cours intégré : retrouvée par son code, sinon créée avec sa règle
     * « Formation Academy ». Renvoie 0 si le cours ne délivre rien ou si le référentiel n’est pas installé.
     */
    public function ensureDefinition(int $tenantId, string $courseKey, string $lang, ?int $actorId = null): int
    {
        $q = AcademyBuiltInCourses::qualifications()[$courseKey] ?? null;
        if ($tenantId < 1 || $q === null || !$this->ready()) {
            return 0;
        }
        $code = (string) $q['code'];
        $id = $this->writer->definitionId($tenantId, $code);
        if ($id > 0) {
            return $id;
        }
        $lang = str_starts_with(strtolower($lang), 'en') ? 'en' : 'fr';
        $course = AcademyBuiltInCourses::find($courseKey) ?? [];
        $courseTitle = (string) ($course['title'][$lang] ?? $courseKey);
        $description = trim((string) ($q['description'][$lang] ?? '')) . "\n\n"
            . ($lang === 'en' ? 'Awarded by the Academy course “' . $courseTitle . '”. ' : 'Délivrée par le cours Academy « ' . $courseTitle . ' ». ')
            . trim((string) ($q['reference'][$lang] ?? ''));
        $pdo = $this->writer->pdo();
        $pdo->beginTransaction();
        try {
            $id = $this->writer->createDefinition($tenantId, [
                'code' => $code,
                'name' => (string) ($q['name'][$lang] ?? $code),
                'short_name' => (string) ($q['short_name'] ?? ''),
                'description' => $description,
                'category_id' => $this->writer->ensureCategory($tenantId, self::CATEGORY_NAME),
                'issuer_id' => $this->writer->ensureIssuer(
                    $tenantId,
                    self::ISSUER_NAME,
                    self::ISSUER_SHORT,
                    'school',
                    $lang === 'en'
                        ? 'The site’s built-in Academy courses (Arma 3 in-game training): their qualifications are awarded when the instructor validates the last chapter. In-game qualifications with no real-world value.'
                        : 'Cours intégrés de l’Academy du site (formations de jeu Arma 3) : leurs qualifications sont délivrées à la validation du dernier chapitre par un instructeur. Qualifications de jeu, sans valeur réelle.',
                    $actorId
                ),
            ], $actorId);
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                "INSERT INTO qualification_award_rules (tenant_id, definition_id, allow_manual, auto_enabled, auto_mode, rule_json, show_on_profile, show_on_orbat, created_at, updated_at)
                 VALUES (?, ?, 1, 1, 'direct', ?, 1, 1, ?, ?)"
            )->execute([$tenantId, $id, json_encode(self::rule($courseKey), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now, $now]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $id;
    }

    /**
     * Toutes communautés : fiches des cours intégrés déjà terminés par au moins un membre (étape de migration ; la
     * règle automatique attribue ensuite les qualifications). Idempotent.
     *
     * @return list<string> journal
     */
    public function syncAll(string $lang = 'fr'): array
    {
        if (!$this->ready()) {
            return [];
        }
        $keys = array_keys(AcademyBuiltInCourses::qualifications());
        if ($keys === []) {
            return [];
        }
        $st = $this->writer->pdo()->prepare(
            'SELECT DISTINCT tenant_id, course_key FROM academy_learners WHERE course_key IN (' . implode(',', array_fill(0, count($keys), '?'))
            . ") AND state_json LIKE '%\"completed\":true%' ORDER BY tenant_id, course_key"
        );
        $st->execute($keys);
        $log = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $tenantId = (int) $row['tenant_id'];
            $key = (string) $row['course_key'];
            if ($tenantId < 1) {
                continue;
            }
            $code = (string) AcademyBuiltInCourses::qualifications()[$key]['code'];
            $before = $this->writer->definitionId($tenantId, $code);
            $id = $this->ensureDefinition($tenantId, $key, $lang);
            if ($id > 0 && $before === 0) {
                $log[] = 'Communauté #' . $tenantId . ' : qualification ' . $code . ' créée (cours ' . $key . ')';
            }
        }

        return $log;
    }
}
