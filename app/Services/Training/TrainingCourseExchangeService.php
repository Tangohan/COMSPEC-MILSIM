<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Core\Database;
use App\Repositories\DocumentRepository;
use App\Repositories\TrainingCourseRepository;
use App\Services\Platform\FeatureGateService;
use App\Repositories\TrainingLessonRepository;
use App\Repositories\TrainingModuleRepository;
use App\Repositories\TrainingQuizRepository;
use App\Repositories\TrainingResourceRepository;
use InvalidArgumentException;

/**
 * Export / import de formations au format JSON (et conversion HTML → brouillon JSON).
 */
class TrainingCourseExchangeService
{
    public const EXCHANGE_VERSION = 1;

    private const LESSON_TYPES = [
        'richtext', 'video', 'video_integrated', 'video_embed', 'pdf', 'audio',
        'scorm_like', 'checklist', 'external_link', 'canvas', 'quiz', 'modals', 'slideshow',
    ];

    private const LEVELS = ['initiation', 'intermediaire', 'avance', 'expert'];

    private const VISIBILITY = ['draft', 'private', 'published', 'archived'];

    private const RESOURCE_TYPES = ['pdf', 'image', 'video', 'audio', 'zip', 'attachment', 'link', 'library_document'];

    private const QUESTION_TYPES = ['single_choice', 'multiple_choice', 'true_false'];

    private const MAX_TEXT_FIELD = 600_000;

    private const MAX_JSON_BYTES = 14_000_000;

    public function __construct(
        private TrainingCourseRepository $courseRepository,
        private TrainingModuleRepository $moduleRepository,
        private TrainingLessonRepository $lessonRepository,
        private TrainingQuizRepository $quizRepository,
        private TrainingResourceRepository $resourceRepository,
        private TrainingService $trainingService,
        private DocumentRepository $documentRepository,
        private FeatureGateService $featureGateService,
    ) {}

    /** @return array<string, mixed> */
    public function buildExportDocument(int $courseId, int $tenantId): array
    {
        $course = $this->trainingService->getCourseWithStructure($courseId, $tenantId, true);
        if (!$course) {
            throw new InvalidArgumentException('Formation introuvable.');
        }

        $exportCourse = $this->stripCourseRowForExport($course);
        $modulesOut = [];
        foreach ($course['modules'] ?? [] as $mod) {
            $mid = (int) ($mod['id'] ?? 0);
            $lessonsOut = [];
            foreach ($mod['lessons'] ?? [] as $les) {
                $lid = (int) ($les['id'] ?? 0);
                $resources = $lid > 0 ? $this->resourceRepository->listByLessonId($lid) : [];
                $lessonsOut[] = $this->stripLessonForExport($les, $resources);
            }
            $quizzesOut = [];
            foreach ($mod['quizzes'] ?? [] as $qz) {
                $qid = (int) ($qz['id'] ?? 0);
                $quizzesOut[] = $this->stripQuizForExport($qz, $qid);
            }
            $modulesOut[] = $this->stripModuleForExport($mod, $lessonsOut, $quizzesOut);
        }

        return [
            'lms_exchange_version' => self::EXCHANGE_VERSION,
            'exported_at' => date('c'),
            'course' => $exportCourse,
            'modules' => $modulesOut,
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @return array{course_id: int, warnings: list<string>}
     */
    public function importDocument(
        array $document,
        int $tenantId,
        int $userId,
        bool $replaceExistingCourse,
        ?int $existingCourseId,
        bool $canPublishImported,
    ): array {
        $normalized = $this->normalizeIncomingDocument($document);
        $coursePayload = $normalized['course'];
        $modules = $normalized['modules'];

        $pdo = Database::getPdo();
        $pdo->beginTransaction();
        $warnings = [];
        try {
            if ($replaceExistingCourse) {
                if ($existingCourseId === null || $existingCourseId < 1) {
                    throw new InvalidArgumentException('Formation cible manquante pour le remplacement.');
                }
                $row = $this->courseRepository->findById($existingCourseId, $tenantId);
                if (!$row) {
                    throw new InvalidArgumentException('Formation cible introuvable.');
                }
                foreach ($this->moduleRepository->listByCourseId($existingCourseId) as $m) {
                    $this->moduleRepository->delete((int) $m['id']);
                }
                $courseId = $existingCourseId;
                $this->applyCoursePatch($courseId, $tenantId, $coursePayload, $userId, $canPublishImported);
            } else {
                if (!$this->featureGateService->canCreateTenantCatalogTrainingCourse($tenantId)) {
                    throw new InvalidArgumentException('Vous avez atteint le nombre maximal de parcours prévus pour votre formule. Passez à une offre supérieure ou archivez un parcours existant avant d’en importer un nouveau.');
                }
                $courseId = $this->createCourseFromPayload($tenantId, $userId, $coursePayload, $canPublishImported);
            }

            $this->insertModulesTree($courseId, $tenantId, $modules, $warnings);
            if (function_exists('lms_platform_version')) {
                $this->courseRepository->update($courseId, [
                    'lms_last_saved_with_version' => lms_platform_version(),
                    'updated_by' => $userId,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['course_id' => $courseId, 'warnings' => $warnings];
    }

    public function parseHtmlToDocument(string $html, string $fallbackTitle): array
    {
        $html = trim($html);
        if ($html === '') {
            throw new InvalidArgumentException('Contenu vide.');
        }
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            $html = $m[1];
        }

        if (!preg_match('/<h1\b/i', $html)) {
            $title = $fallbackTitle !== '' ? $fallbackTitle : 'Formation importée';
            $modules = [[
                'title' => 'Contenu',
                'description' => null,
                'subtitle' => null,
                'learning_objectives' => null,
                'estimated_minutes' => 0,
                'is_required' => 1,
                'lessons' => [[
                    'title' => 'Leçon 1',
                    'summary' => null,
                    'learning_objectives' => null,
                    'instructor_notes' => null,
                    'lesson_type' => 'richtext',
                    'content' => $this->clipText($html, self::MAX_TEXT_FIELD),
                    'external_url' => null,
                    'duration_minutes' => 0,
                    'difficulty' => null,
                    'is_required' => 1,
                    'resources' => [],
                ]],
                'quizzes' => [],
            ]];

            return [
                'lms_exchange_version' => self::EXCHANGE_VERSION,
                'course' => [
                    'title' => $title,
                    'slug' => '',
                    'short_description' => null,
                    'description' => null,
                    'visibility' => 'draft',
                    'level' => 'initiation',
                    'language_code' => 'fr',
                ],
                'modules' => $modules,
            ];
        }

        $parts = preg_split('/(?=<h1\b)/i', $html, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $modules = [];
        foreach ($parts as $chunk) {
            if (!preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $chunk, $hm)) {
                continue;
            }
            $modTitle = trim(html_entity_decode(strip_tags($hm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($modTitle === '') {
                $modTitle = 'Module';
            }
            $afterH1 = trim(preg_replace('/<h1\b[^>]*>.*?<\/h1>/is', '', $chunk, 1) ?? '');
            $lessons = $this->splitHtmlModuleIntoLessons($afterH1);
            $modules[] = [
                'title' => $modTitle,
                'description' => null,
                'subtitle' => null,
                'learning_objectives' => null,
                'estimated_minutes' => 0,
                'is_required' => 1,
                'lessons' => $lessons,
                'quizzes' => [],
            ];
        }
        if ($modules === []) {
            throw new InvalidArgumentException('Impossible de détecter la structure (titres de section).');
        }

        $title = $fallbackTitle !== '' ? $fallbackTitle : ($modules[0]['title'] ?? 'Formation importée');

        return [
            'lms_exchange_version' => self::EXCHANGE_VERSION,
            'course' => [
                'title' => $title,
                'slug' => '',
                'short_description' => null,
                'description' => null,
                'visibility' => 'draft',
                'level' => 'initiation',
                'language_code' => 'fr',
            ],
            'modules' => $modules,
        ];
    }

    public static function decodeJsonPayload(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new InvalidArgumentException('Fichier ou texte vide.');
        }
        if (strlen($raw) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('Le fichier est trop volumineux pour être importé ici.');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Le contenu n’est pas un document JSON valide.');
        }

        return $data;
    }

    /**
     * @param list<array<string, mixed>> $lessons
     * @param list<array<string, mixed>> $quizzes
     * @return array<string, mixed>
     */
    private function stripModuleForExport(array $mod, array $lessons, array $quizzes): array
    {
        return [
            'title' => (string) ($mod['title'] ?? ''),
            'description' => $this->nullIfEmptyString($mod['description'] ?? null),
            'subtitle' => $this->nullIfEmptyString($mod['subtitle'] ?? null),
            'learning_objectives' => $this->exportLearningObjectives($mod['learning_objectives'] ?? null),
            'estimated_minutes' => (int) ($mod['estimated_minutes'] ?? 0),
            'is_required' => (int) ($mod['is_required'] ?? 1) ? 1 : 0,
            'lessons' => $lessons,
            'quizzes' => $quizzes,
        ];
    }

    /** @param list<array<string, mixed>> $resources */
    private function stripLessonForExport(array $les, array $resources): array
    {
        $type = (string) ($les['lesson_type'] ?? 'richtext');
        if (!in_array($type, self::LESSON_TYPES, true)) {
            $type = 'richtext';
        }
        $resOut = [];
        foreach ($resources as $r) {
            $row = [
                'resource_type' => (string) ($r['resource_type'] ?? 'link'),
                'title' => (string) ($r['title'] ?? ''),
                'external_url' => $this->nullIfEmptyString($r['external_url'] ?? null),
                'file_path' => $this->nullIfEmptyString($r['file_path'] ?? null),
                'mime_type' => $this->nullIfEmptyString($r['mime_type'] ?? null),
                'file_size' => isset($r['file_size']) ? (int) $r['file_size'] : null,
            ];
            $libSlug = trim((string) ($r['document_slug'] ?? ''));
            if ($libSlug !== '' && !empty($r['document_id'])) {
                $row['library_document_slug'] = $libSlug;
            }
            $resOut[] = $row;
        }

        return [
            'title' => (string) ($les['title'] ?? ''),
            'summary' => $this->nullIfEmptyString($les['summary'] ?? null),
            'learning_objectives' => $this->exportLearningObjectives($les['learning_objectives'] ?? null),
            'instructor_notes' => $this->nullIfEmptyString($les['instructor_notes'] ?? null),
            'lesson_type' => $type,
            'content' => $les['content'] !== null && $les['content'] !== '' ? (string) $les['content'] : null,
            'external_url' => $this->nullIfEmptyString($les['external_url'] ?? null),
            'duration_minutes' => (int) ($les['duration_minutes'] ?? 0),
            'difficulty' => $this->normalizeDifficultyImport($les['difficulty'] ?? null),
            'is_required' => (int) ($les['is_required'] ?? 1) ? 1 : 0,
            'resources' => $resOut,
        ];
    }

    private function stripQuizForExport(array $qz, int $quizId): array
    {
        $questionsOut = [];
        foreach ($this->quizRepository->listQuestionsByQuizId($quizId, false) as $q) {
            $qid = (int) ($q['id'] ?? 0);
            $answersOut = [];
            foreach ($this->quizRepository->listAnswersByQuestionId($qid) as $a) {
                $answersOut[] = [
                    'answer_text' => (string) ($a['answer_text'] ?? ''),
                    'is_correct' => (int) ($a['is_correct'] ?? 0) ? 1 : 0,
                ];
            }
            $qt = (string) ($q['question_type'] ?? 'single_choice');
            if (!in_array($qt, self::QUESTION_TYPES, true)) {
                $qt = 'single_choice';
            }
            $questionsOut[] = [
                'question_type' => $qt,
                'question_text' => (string) ($q['question_text'] ?? ''),
                'explanation' => $this->nullIfEmptyString($q['explanation'] ?? null),
                'points' => (float) ($q['points'] ?? 1),
                'answers' => $answersOut,
            ];
        }

        return [
            'title' => (string) ($qz['title'] ?? ''),
            'description' => $this->nullIfEmptyString($qz['description'] ?? null),
            'passing_score' => (float) ($qz['passing_score'] ?? 80),
            'max_attempts' => (int) ($qz['max_attempts'] ?? 3),
            'time_limit_minutes' => isset($qz['time_limit_minutes']) && $qz['time_limit_minutes'] !== ''
                ? (int) $qz['time_limit_minutes'] : null,
            'randomize_questions' => (int) ($qz['randomize_questions'] ?? 0) ? 1 : 0,
            'is_final_exam' => (int) ($qz['is_final_exam'] ?? 0) ? 1 : 0,
            'questions' => $questionsOut,
        ];
    }

    /** @return array<string, mixed> */
    private function stripCourseRowForExport(array $course): array
    {
        $keys = [
            'title', 'slug', 'course_code', 'short_description', 'description', 'category', 'level',
            'language_code', 'estimated_minutes', 'passing_score', 'is_mandatory', 'is_certifying',
            'validity_days', 'visibility', 'lms_scope', 'thumbnail_path', 'banner_path',
            'instruction_audio_url', 'instruction_audio_instructor_optional', 'instruction_audio_notes',
            'showcase_cycle_date', 'showcase_location', 'showcase_badge', 'showcase_card_style', 'showcase_sort_order',
        ];
        $out = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $course)) {
                continue;
            }
            $v = $course[$k];
            if ($k === 'is_mandatory' || $k === 'is_certifying' || $k === 'instruction_audio_instructor_optional') {
                $out[$k] = (int) $v ? 1 : 0;
            } elseif ($k === 'passing_score') {
                $out[$k] = (float) $v;
            } elseif ($k === 'estimated_minutes' || $k === 'validity_days' || $k === 'showcase_sort_order') {
                $out[$k] = $v !== null && $v !== '' ? (int) $v : null;
            } else {
                $out[$k] = $v === null || $v === '' ? null : (is_scalar($v) ? (string) $v : $v);
            }
        }
        $out['learning_objectives'] = $this->exportLearningObjectives($course['learning_objectives'] ?? null);
        $out['theme_json'] = $this->exportJsonField($course['theme_json'] ?? null);
        $out['enrollment_policy_json'] = $this->exportJsonField($course['enrollment_policy_json'] ?? null);

        return $out;
    }

    private function exportLearningObjectives(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                $lines = [];
                foreach ($j as $x) {
                    if (is_string($x) && trim($x) !== '') {
                        $lines[] = trim($x);
                    }
                }

                return $lines === [] ? null : $lines;
            }
            $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

            return array_values(array_filter(array_map('trim', $lines))) ?: null;
        }

        return null;
    }

    private function exportJsonField(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $d = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $d;
            }

            return $raw;
        }

        return $raw;
    }

    /** @return array{course: array<string, mixed>, modules: list<array<string, mixed>>} */
    private function normalizeIncomingDocument(array $document): array
    {
        $ver = (int) ($document['lms_exchange_version'] ?? 1);
        if ($ver < 1 || $ver > self::EXCHANGE_VERSION) {
            throw new InvalidArgumentException('Version du document non prise en charge.');
        }
        $modules = $document['modules'] ?? null;
        $course = $document['course'] ?? null;
        if (!is_array($course)) {
            if (isset($document['title'])) {
                $course = ['title' => $document['title']];
                if ($modules === null) {
                    $modules = [];
                }
            } else {
                throw new InvalidArgumentException('Le document doit contenir une section « course » (fiche formation).');
            }
        }
        if (!is_array($modules)) {
            throw new InvalidArgumentException('Le document doit contenir une liste de modules.');
        }
        if (isset($course['modules'])) {
            unset($course['modules']);
        }

        return ['course' => $course, 'modules' => $modules];
    }

    private function createCourseFromPayload(int $tenantId, int $userId, array $coursePayload, bool $canPublish): int
    {
        $title = trim((string) ($coursePayload['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Le titre de la formation est obligatoire.');
        }
        $slug = trim((string) ($coursePayload['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->slugify($title);
        }
        $slug = $this->uniqueSlug($tenantId, $slug);
        $visibility = (string) ($coursePayload['visibility'] ?? 'draft');
        if (!in_array($visibility, self::VISIBILITY, true)) {
            $visibility = 'draft';
        }
        if ($visibility === 'published' && !$canPublish) {
            $visibility = 'draft';
        }
        $lmsVer = function_exists('lms_platform_version') ? lms_platform_version() : '1.0.0';
        $courseId = $this->courseRepository->create($tenantId, [
            'title' => $title,
            'slug' => $slug,
            'course_code' => $this->optionalString($coursePayload['course_code'] ?? null, 32),
            'short_description' => $this->optionalString($coursePayload['short_description'] ?? null, 65535),
            'description' => $this->optionalString($coursePayload['description'] ?? null, self::MAX_TEXT_FIELD),
            'learning_objectives' => $this->encodeLearningObjectives($coursePayload['learning_objectives'] ?? null),
            'theme_json' => $this->encodeJsonColumn($coursePayload['theme_json'] ?? null),
            'thumbnail_path' => $this->optionalString($coursePayload['thumbnail_path'] ?? null, 512),
            'banner_path' => $this->optionalString($coursePayload['banner_path'] ?? null, 512),
            'category' => $this->optionalString($coursePayload['category'] ?? null, 128),
            'level' => $this->normalizeLevel($coursePayload['level'] ?? null),
            'language_code' => $this->normalizeLanguage($coursePayload['language_code'] ?? null),
            'estimated_minutes' => max(0, (int) ($coursePayload['estimated_minutes'] ?? 0)),
            'passing_score' => min(100, max(0, (float) ($coursePayload['passing_score'] ?? 80))),
            'is_mandatory' => !empty($coursePayload['is_mandatory']) ? 1 : 0,
            'is_certifying' => !empty($coursePayload['is_certifying']) ? 1 : 0,
            'validity_days' => isset($coursePayload['validity_days']) && $coursePayload['validity_days'] !== ''
                ? max(0, (int) $coursePayload['validity_days']) : null,
            'visibility' => $visibility,
            'created_by' => $userId,
            'updated_by' => $userId,
            'lms_created_with_version' => $lmsVer,
            'lms_last_saved_with_version' => $lmsVer,
        ]);
        $this->courseRepository->update($courseId, array_filter([
            'enrollment_policy_json' => $this->encodeJsonColumn($coursePayload['enrollment_policy_json'] ?? null),
            'instruction_audio_url' => $this->optionalString($coursePayload['instruction_audio_url'] ?? null, 512),
            'instruction_audio_instructor_optional' => isset($coursePayload['instruction_audio_instructor_optional'])
                ? (!empty($coursePayload['instruction_audio_instructor_optional']) ? 1 : 0) : null,
            'instruction_audio_notes' => $this->optionalString($coursePayload['instruction_audio_notes'] ?? null, 500),
            'showcase_cycle_date' => $this->optionalString($coursePayload['showcase_cycle_date'] ?? null, 32),
            'showcase_location' => $this->optionalString($coursePayload['showcase_location'] ?? null, 255),
            'showcase_badge' => $this->optionalString($coursePayload['showcase_badge'] ?? null, 64),
            'showcase_card_style' => $this->optionalString($coursePayload['showcase_card_style'] ?? null, 64),
            'showcase_sort_order' => isset($coursePayload['showcase_sort_order']) && $coursePayload['showcase_sort_order'] !== ''
                ? (int) $coursePayload['showcase_sort_order'] : null,
        ], static fn ($v) => $v !== null));

        return $courseId;
    }

    /** @param array<string, mixed> $coursePayload */
    private function applyCoursePatch(int $courseId, int $tenantId, array $coursePayload, int $userId, bool $canPublish): void
    {
        $patch = [];
        if (isset($coursePayload['title'])) {
            $t = trim((string) $coursePayload['title']);
            if ($t === '') {
                throw new InvalidArgumentException('Le titre de la formation est obligatoire.');
            }
            $patch['title'] = $t;
        }
        if (array_key_exists('slug', $coursePayload)) {
            $s = trim((string) $coursePayload['slug']);
            if ($s !== '') {
                if ($this->courseRepository->slugExists($tenantId, $s, $courseId)) {
                    throw new InvalidArgumentException('Ce lien court (slug) est déjà utilisé par une autre formation.');
                }
                $patch['slug'] = $s;
            }
        }
        foreach ([
            'course_code' => 32,
            'short_description' => 65535,
            'thumbnail_path' => 512,
            'banner_path' => 512,
            'category' => 128,
            'instruction_audio_url' => 512,
            'instruction_audio_notes' => 500,
            'showcase_cycle_date' => 32,
            'showcase_location' => 255,
            'showcase_badge' => 64,
            'showcase_card_style' => 64,
        ] as $field => $maxLen) {
            if (array_key_exists($field, $coursePayload)) {
                $patch[$field] = $this->optionalString($coursePayload[$field] ?? null, $maxLen);
            }
        }
        if (array_key_exists('description', $coursePayload)) {
            $patch['description'] = $this->clipText(
                $coursePayload['description'] === null ? '' : (string) $coursePayload['description'],
                self::MAX_TEXT_FIELD
            );
            $patch['description'] = $patch['description'] === '' ? null : $patch['description'];
        }
        if (array_key_exists('learning_objectives', $coursePayload)) {
            $patch['learning_objectives'] = $this->encodeLearningObjectives($coursePayload['learning_objectives']);
        }
        if (array_key_exists('theme_json', $coursePayload)) {
            $patch['theme_json'] = $this->encodeJsonColumn($coursePayload['theme_json']);
        }
        if (array_key_exists('enrollment_policy_json', $coursePayload)) {
            $patch['enrollment_policy_json'] = $this->encodeJsonColumn($coursePayload['enrollment_policy_json']);
        }
        if (array_key_exists('level', $coursePayload)) {
            $patch['level'] = $this->normalizeLevel($coursePayload['level']);
        }
        if (array_key_exists('language_code', $coursePayload)) {
            $patch['language_code'] = $this->normalizeLanguage($coursePayload['language_code']);
        }
        if (array_key_exists('estimated_minutes', $coursePayload)) {
            $patch['estimated_minutes'] = max(0, (int) $coursePayload['estimated_minutes']);
        }
        if (array_key_exists('passing_score', $coursePayload)) {
            $patch['passing_score'] = min(100, max(0, (float) $coursePayload['passing_score']));
        }
        if (array_key_exists('is_mandatory', $coursePayload)) {
            $patch['is_mandatory'] = !empty($coursePayload['is_mandatory']) ? 1 : 0;
        }
        if (array_key_exists('is_certifying', $coursePayload)) {
            $patch['is_certifying'] = !empty($coursePayload['is_certifying']) ? 1 : 0;
        }
        if (array_key_exists('validity_days', $coursePayload)) {
            $v = $coursePayload['validity_days'];
            $patch['validity_days'] = $v === null || $v === '' ? null : max(0, (int) $v);
        }
        if (array_key_exists('instruction_audio_instructor_optional', $coursePayload)) {
            $patch['instruction_audio_instructor_optional'] = !empty($coursePayload['instruction_audio_instructor_optional']) ? 1 : 0;
        }
        if (array_key_exists('showcase_sort_order', $coursePayload)) {
            $so = $coursePayload['showcase_sort_order'];
            $patch['showcase_sort_order'] = $so === null || $so === '' ? null : (int) $so;
        }
        if (array_key_exists('visibility', $coursePayload)) {
            $vis = (string) $coursePayload['visibility'];
            if (in_array($vis, self::VISIBILITY, true)) {
                if ($vis === 'published' && !$canPublish) {
                    $vis = 'draft';
                }
                $patch['visibility'] = $vis;
            }
        }
        $patch['updated_by'] = $userId;
        $this->courseRepository->update($courseId, $patch);
    }

    /**
     * @param list<array<string, mixed>> $modules
     * @param list<string> $warnings
     */
    private function insertModulesTree(int $courseId, int $tenantId, array $modules, array &$warnings): void
    {
        $pos = 0;
        foreach ($modules as $mod) {
            $pos++;
            if (!is_array($mod)) {
                continue;
            }
            $mTitle = trim((string) ($mod['title'] ?? ''));
            if ($mTitle === '') {
                $warnings[] = 'Un module sans titre a été ignoré.';
                continue;
            }
            $mid = $this->moduleRepository->create($courseId, [
                'title' => $mTitle,
                'description' => $this->optionalString($mod['description'] ?? null, self::MAX_TEXT_FIELD),
                'subtitle' => $this->optionalString($mod['subtitle'] ?? null, 255),
                'learning_objectives' => $this->encodeLearningObjectives($mod['learning_objectives'] ?? null),
                'estimated_minutes' => max(0, (int) ($mod['estimated_minutes'] ?? 0)),
                'is_required' => !empty($mod['is_required']) ? 1 : 0,
                'position' => $pos,
            ]);
            $lp = 0;
            foreach ($mod['lessons'] ?? [] as $les) {
                if (!is_array($les)) {
                    continue;
                }
                $lp++;
                $lTitle = trim((string) ($les['title'] ?? ''));
                if ($lTitle === '') {
                    $warnings[] = 'Une leçon sans titre a été ignorée (module « ' . $mTitle . ' »).';
                    continue;
                }
                $type = (string) ($les['lesson_type'] ?? 'richtext');
                if (!in_array($type, self::LESSON_TYPES, true)) {
                    $type = 'richtext';
                }
                $content = $les['content'] ?? null;
                $contentStr = $content === null || $content === '' ? null : $this->clipText((string) $content, self::MAX_TEXT_FIELD);
                $lid = $this->lessonRepository->create($mid, [
                    'title' => $lTitle,
                    'summary' => $this->optionalString($les['summary'] ?? null, 500),
                    'learning_objectives' => $this->encodeLearningObjectives($les['learning_objectives'] ?? null),
                    'instructor_notes' => $this->optionalString($les['instructor_notes'] ?? null, self::MAX_TEXT_FIELD),
                    'lesson_type' => $type,
                    'content' => $contentStr,
                    'external_url' => $this->optionalString($les['external_url'] ?? null, 500),
                    'duration_minutes' => max(0, (int) ($les['duration_minutes'] ?? 0)),
                    'difficulty' => $this->normalizeDifficultyImport($les['difficulty'] ?? null),
                    'is_required' => !empty($les['is_required']) ? 1 : 0,
                    'position' => $lp,
                ]);
                foreach ($les['resources'] ?? [] as $res) {
                    if (!is_array($res)) {
                        continue;
                    }
                    $this->importOneResource($lid, $tenantId, $res, $warnings);
                }
            }
            foreach ($mod['quizzes'] ?? [] as $qz) {
                if (!is_array($qz)) {
                    continue;
                }
                $this->importOneQuiz($mid, $qz, $warnings);
            }
        }
    }

    /** @param array<string, mixed> $res */
    private function importOneResource(int $lessonId, int $tenantId, array $res, array &$warnings): void
    {
        $title = trim((string) ($res['title'] ?? ''));
        $libSlug = trim((string) ($res['library_document_slug'] ?? ''));
        if ($libSlug !== '') {
            $doc = $this->documentRepository->findBySlug($libSlug, $tenantId);
            if (!$doc) {
                $warnings[] = 'Ressource « ' . ($title !== '' ? $title : $libSlug) . ' » : document de bibliothèque « ' . $libSlug . ' » introuvable sur cette communauté — lien ignoré.';

                return;
            }
            if ($title === '') {
                $title = trim((string) ($doc['title'] ?? ''));
            }
            if ($title === '') {
                $title = 'Document';
            }
            $this->resourceRepository->create($lessonId, [
                'resource_type' => 'library_document',
                'title' => mb_substr($title, 0, 255),
                'external_url' => null,
                'file_path' => null,
                'mime_type' => null,
                'file_size' => null,
                'document_id' => (int) $doc['id'],
            ]);

            return;
        }
        if ($title === '') {
            return;
        }
        $type = (string) ($res['resource_type'] ?? 'link');
        if (!in_array($type, self::RESOURCE_TYPES, true)) {
            $type = 'link';
        }
        $url = $this->optionalString($res['external_url'] ?? null, 500);
        $path = $this->optionalString($res['file_path'] ?? null, 255);
        if ($url === null && $path === null) {
            $warnings[] = 'Ressource « ' . $title . ' » ignorée (aucune adresse exploitable).';

            return;
        }
        $this->resourceRepository->create($lessonId, [
            'resource_type' => $type,
            'title' => mb_substr($title, 0, 255),
            'external_url' => $url,
            'file_path' => $path,
            'mime_type' => $this->optionalString($res['mime_type'] ?? null, 128),
            'file_size' => isset($res['file_size']) ? (int) $res['file_size'] : null,
        ]);
    }

    /** @param array<string, mixed> $qz */
    private function importOneQuiz(int $moduleId, array $qz, array &$warnings): void
    {
        $qt = trim((string) ($qz['title'] ?? ''));
        if ($qt === '') {
            $warnings[] = 'Un questionnaire sans titre a été ignoré.';

            return;
        }
        $quizId = $this->quizRepository->createQuiz($moduleId, [
            'title' => $qt,
            'description' => $this->optionalString($qz['description'] ?? null, self::MAX_TEXT_FIELD),
            'passing_score' => min(100, max(0, (float) ($qz['passing_score'] ?? 80))),
            'max_attempts' => max(1, (int) ($qz['max_attempts'] ?? 3)),
            'time_limit_minutes' => isset($qz['time_limit_minutes']) && $qz['time_limit_minutes'] !== ''
                ? max(0, (int) $qz['time_limit_minutes']) : null,
            'randomize_questions' => !empty($qz['randomize_questions']) ? 1 : 0,
            'is_final_exam' => !empty($qz['is_final_exam']) ? 1 : 0,
        ]);
        $qpos = 0;
        foreach ($qz['questions'] ?? [] as $q) {
            if (!is_array($q)) {
                continue;
            }
            $qpos++;
            $qtext = trim((string) ($q['question_text'] ?? ''));
            if ($qtext === '') {
                continue;
            }
            $qtype = (string) ($q['question_type'] ?? 'single_choice');
            if (!in_array($qtype, self::QUESTION_TYPES, true)) {
                $qtype = 'single_choice';
            }
            $answers = $q['answers'] ?? [];
            if (!is_array($answers) || $answers === []) {
                $warnings[] = 'Question ignorée (réponses manquantes).';

                continue;
            }
            $qid = $this->quizRepository->createQuestion($quizId, [
                'question_type' => $qtype,
                'question_text' => $qtext,
                'explanation' => $this->optionalString($q['explanation'] ?? null, self::MAX_TEXT_FIELD),
                'points' => max(0, (float) ($q['points'] ?? 1)),
                'position' => $qpos,
            ]);
            $apos = 0;
            foreach ($answers as $a) {
                if (!is_array($a)) {
                    continue;
                }
                $apos++;
                $at = trim((string) ($a['answer_text'] ?? ''));
                if ($at === '') {
                    continue;
                }
                $this->quizRepository->createAnswer($qid, [
                    'answer_text' => $at,
                    'is_correct' => !empty($a['is_correct']) ? 1 : 0,
                    'position' => $apos,
                ]);
            }
        }
    }

    private function uniqueSlug(int $tenantId, string $slug): string
    {
        $base = $slug !== '' ? $slug : 'formation';
        $candidate = $base;
        $n = 2;
        while ($this->courseRepository->slugExists($tenantId, $candidate)) {
            $candidate = $base . '-import-' . $n;
            $n++;
            if ($n > 200) {
                $candidate = $base . '-' . bin2hex(random_bytes(3));

                break;
            }
        }

        return $candidate;
    }

    private function slugify(string $title): string
    {
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
        $s = strtolower((string) $s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';

        return trim($s, '-') ?: 'formation';
    }

    private function clipText(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max);
    }

    private function optionalString(mixed $v, int $maxLen): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : mb_substr($s, 0, $maxLen);
    }

    private function encodeLearningObjectives(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $t = trim($raw);
            if ($t === '') {
                return null;
            }
            $j = json_decode($t, true);
            if (is_array($j)) {
                $lines = [];
                foreach ($j as $x) {
                    if (is_string($x) && trim($x) !== '') {
                        $lines[] = trim($x);
                    }
                }

                return $lines === [] ? null : json_encode($lines, JSON_UNESCAPED_UNICODE);
            }
            $lines = preg_split('/\r\n|\r|\n/', $t) ?: [];

            return json_encode(array_values(array_filter(array_map('trim', $lines))), JSON_UNESCAPED_UNICODE);
        }
        if (is_array($raw)) {
            $lines = [];
            foreach ($raw as $x) {
                if (is_string($x) && trim($x) !== '') {
                    $lines[] = trim($x);
                }
            }

            return $lines === [] ? null : json_encode($lines, JSON_UNESCAPED_UNICODE);
        }

        return null;
    }

    private function encodeJsonColumn(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $t = trim($raw);
            if ($t === '') {
                return null;
            }
            json_decode($t);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $t;
            }

            return json_encode($t, JSON_UNESCAPED_UNICODE);
        }
        if (is_array($raw)) {
            return json_encode($raw, JSON_UNESCAPED_UNICODE);
        }

        return null;
    }

    private function normalizeLevel(mixed $v): string
    {
        $l = is_string($v) ? trim($v) : '';

        return in_array($l, self::LEVELS, true) ? $l : 'initiation';
    }

    private function normalizeLanguage(mixed $v): string
    {
        $c = is_string($v) ? trim($v) : '';

        return $c === '' ? 'fr' : mb_substr($c, 0, 10);
    }

    private function normalizeDifficultyImport(mixed $v): ?string
    {
        $d = is_string($v) ? trim($v) : '';

        return in_array($d, self::LEVELS, true) ? $d : null;
    }

    private function nullIfEmptyString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function splitHtmlModuleIntoLessons(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [[
                'title' => 'Introduction',
                'summary' => null,
                'learning_objectives' => null,
                'instructor_notes' => null,
                'lesson_type' => 'richtext',
                'content' => '<p></p>',
                'external_url' => null,
                'duration_minutes' => 0,
                'difficulty' => null,
                'is_required' => 1,
                'resources' => [],
            ]];
        }
        if (!preg_match('/<h2\b/i', $html)) {
            return [[
                'title' => 'Contenu',
                'summary' => null,
                'learning_objectives' => null,
                'instructor_notes' => null,
                'lesson_type' => 'richtext',
                'content' => $this->clipText($html, self::MAX_TEXT_FIELD),
                'external_url' => null,
                'duration_minutes' => 0,
                'difficulty' => null,
                'is_required' => 1,
                'resources' => [],
            ]];
        }
        $segments = preg_split('/(?=<h2\b)/i', $html, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lessons = [];
        $idx = 0;
        foreach ($segments as $seg) {
            $seg = trim($seg);
            if ($seg === '') {
                continue;
            }
            if (preg_match('/<h2\b[^>]*>(.*?)<\/h2>/is', $seg, $hm)) {
                $ltitle = trim(html_entity_decode(strip_tags($hm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($ltitle === '') {
                    $ltitle = 'Leçon ' . (++$idx);
                }
                $body = trim(preg_replace('/<h2\b[^>]*>.*?<\/h2>/is', '', $seg, 1) ?? '');
                $lessons[] = [
                    'title' => $ltitle,
                    'summary' => null,
                    'learning_objectives' => null,
                    'instructor_notes' => null,
                    'lesson_type' => 'richtext',
                    'content' => $this->clipText($body !== '' ? $body : '<p></p>', self::MAX_TEXT_FIELD),
                    'external_url' => null,
                    'duration_minutes' => 0,
                    'difficulty' => null,
                    'is_required' => 1,
                    'resources' => [],
                ];
            }
        }

        return $lessons !== [] ? $lessons : [[
            'title' => 'Contenu',
            'summary' => null,
            'learning_objectives' => null,
            'instructor_notes' => null,
            'lesson_type' => 'richtext',
            'content' => $this->clipText($html, self::MAX_TEXT_FIELD),
            'external_url' => null,
            'duration_minutes' => 0,
            'difficulty' => null,
            'is_required' => 1,
            'resources' => [],
        ]];
    }
}
