<?php

declare(strict_types=1);

namespace App\Services\Academy;

use App\Repositories\AcademyAutoValidationRepository;

/**
 * Vérification automatique par Athena des chapitres Academy restés en attente d'un formateur. Pour chaque communauté qui
 * l'a activée, un chapitre en attente depuis plus de N heures (72 par défaut) est vérifié sur des critères objectifs
 * seulement (AcademyCurriculum::autoValidationCheck) :
 * - critères remplis : validation automatique, tracée (journal d'audit sans auteur humain, mention dans le dossier,
 *   notification du stagiaire et des formateurs) ; le formateur peut l'annuler ;
 * - sinon, ou si rien d'objectif n'est vérifiable : aucune validation, les formateurs sont relancés (une fois par délai)
 *   et le dossier est signalé « à traiter » dans la file de l'encadrement.
 * Garde-fous : le contrôle est refait sous verrou au moment d'écrire (un formateur qui a validé ou refusé entre-temps
 * l'emporte, une seconde exécution ne fait rien), plafond par exécution, communauté par communauté.
 */
final class AcademyAutoValidation
{
    /** Chapitres examinés au plus par exécution, toutes communautés confondues. */
    public const CAP = 100;

    private AcademyAutoValidationRepository $store;

    public function __construct(?AcademyAutoValidationRepository $store = null)
    {
        $this->store = $store ?? new AcademyAutoValidationRepository();
    }

    /** @return array{tenants: int, checked: int, validated: int, unmet: int, skipped: int, capped: bool} */
    public function run(?int $now = null, int $cap = self::CAP): array
    {
        $now ??= time();
        $out = ['tenants' => 0, 'checked' => 0, 'validated' => 0, 'unmet' => 0, 'skipped' => 0, 'capped' => false];
        foreach ($this->store->enabledTenants() as $tenantId => $settings) {
            if ($tenantId < 1) {
                continue;
            }
            $room = $cap - $out['checked'];
            if ($room < 1) {
                $out['capped'] = true;
                break;
            }
            $out['tenants']++;
            $hours = (int) $settings['hours'];
            foreach ($this->store->candidates($tenantId, $hours, $now - $hours * 3600, $room) as $candidate) {
                $out['checked']++;
                $out[$this->process($tenantId, $candidate['user_id'], $candidate['course_key'], $settings, $now)]++;
            }
        }

        return $out;
    }

    /**
     * @param array{enabled: bool, hours: int, excluded: list<string>, final_allowed?: list<string>} $settings
     * @return 'validated'|'unmet'|'skipped'
     */
    public function process(int $tenantId, int $userId, string $key, array $settings, int $now): string
    {
        $hours = (int) $settings['hours'];
        $reasons = [];
        $chapter = -1;
        $title = $key;
        $validated = false;
        $final = false;
        try {
            $this->store->apply($tenantId, $userId, $key, function (array $state) use ($key, $settings, $hours, $now, &$reasons, &$chapter, &$title, &$validated, &$final): array {
                $course = is_array($state['snapshot'] ?? null) ? $state['snapshot'] : [];
                $course['key'] = $key;
                $chapter = is_int($state['chapter'] ?? null) ? $state['chapter'] : -1;
                $title = self::title($course['title'] ?? null, $key);
                // Le formateur a validé, refusé ou dispensé entre-temps, ou l'attente n'atteint pas le délai : on ne touche à rien.
                if (($state['stage'] ?? 0) !== 6 || !empty($state['completed']) || (int) ($state['updated'] ?? 0) > $now - $hours * 3600) {
                    throw new \DomainException('locked');
                }
                $verdict = AcademyCurriculum::autoValidationCheck($state, $course, $settings['excluded'], $settings['final_allowed'] ?? []);
                if (!$verdict['ok']) {
                    $reasons = $verdict['reasons'];
                    throw new \DomainException('unmet');
                }
                $validated = true;

                $final = AcademyCurriculum::nextUnvalidated($state['validations'] ?? [], count($course['chapters']), $chapter + 1) === null;

                return AcademyCurriculum::autoValidateChapter($state, $course, $settings['excluded'], $settings['final_allowed'] ?? [], $hours, $now);
            });
        } catch (\DomainException $e) {
            return $e->getMessage() === 'unmet' ? $this->unmet($tenantId, $userId, $key, $chapter, $title, $reasons, $hours, $now) : 'skipped';
        } catch (\Throwable $e) {
            error_log('[academy-auto] ' . $key . ' : ' . $e->getMessage());

            return 'skipped';
        }
        if (!$validated) {
            return 'skipped';
        }
        $awards = [];
        if ($final) {
            // Attestation, licence et qualifications : même logique que la validation manuelle, auteur système.
            try {
                $awards = $this->store->awardCredentials($tenantId, $userId, $key, $hours);
                if ($awards !== []) {
                    $this->store->apply($tenantId, $userId, $key, static function (array $state) use ($awards): array {
                        $state['auto_awards'] = $awards;

                        return $state;
                    });
                }
            } catch (\Throwable $e) {
                error_log('[academy-auto] attribution ' . $key . ' : ' . $e->getMessage());
            }
        }
        $this->store->audit('academy.learner.auto_validated', $tenantId, ['course' => $key, 'learner' => $userId, 'chapter' => $chapter, 'hours' => $hours, 'final' => $final, 'awards' => $awards, 'rule' => 'athena_auto_validation', 'actor' => 'system']);
        $payload = ['course' => $title, 'chapter' => $chapter + 1, 'hours' => $hours, 'final' => $final];
        $this->safely(fn () => $this->store->notify($tenantId, $userId, 'academy_auto_validated', $payload + ['for' => 'learner', 'href' => '/academy/classroom?course=' . rawurlencode($key)]));
        foreach ($this->store->instructorIds($tenantId) as $id) {
            if ($id !== $userId) {
                $this->safely(fn () => $this->store->notify($tenantId, $id, 'academy_auto_validated', $payload + ['for' => 'instructor', 'learner' => $userId, 'href' => '/academy/instructor#file']));
            }
        }

        return 'validated';
    }

    /** @param list<string> $reasons */
    private function unmet(int $tenantId, int $userId, string $key, int $chapter, string $title, array $reasons, int $hours, int $now): string
    {
        // L'écriture ne se fait que si le chapitre est toujours en attente ; sinon un formateur est passé avant.
        if ($chapter < 0 || !$this->store->markChecked($tenantId, $userId, $key, $chapter, ['at' => $now, 'chapter' => $chapter, 'hours' => $hours, 'reasons' => $reasons])) {
            return 'skipped';
        }
        $this->store->audit('academy.learner.auto_check_unmet', $tenantId, ['course' => $key, 'learner' => $userId, 'chapter' => $chapter, 'hours' => $hours, 'reasons' => $reasons, 'actor' => 'system']);
        foreach ($this->store->instructorIds($tenantId) as $id) {
            if ($id !== $userId) {
                $this->safely(fn () => $this->store->notify($tenantId, $id, 'academy_auto_pending', ['course' => $title, 'chapter' => $chapter + 1, 'hours' => $hours, 'learner' => $userId, 'reasons' => $reasons, 'href' => '/academy/instructor#file']));
            }
        }

        return 'unmet';
    }

    /** Annulation par un formateur d'une validation d'Athena (l'appelant a vérifié ses droits sur le cours). */
    public function revoke(int $tenantId, int $learnerId, string $key, int $chapter, int $instructorId, ?int $now = null): void
    {
        $now ??= time();
        if ($instructorId < 1 || $instructorId === $learnerId) {
            throw new \DomainException('locked');
        }
        $awards = [];
        $this->store->apply($tenantId, $learnerId, $key, static function (array $state) use ($chapter, $instructorId, $now, &$awards): array {
            $awards = array_values(array_filter(array_map('intval', (array) ($state['auto_awards'] ?? [])), static fn (int $id): bool => $id > 0));

            return AcademyCurriculum::revokeAutoValidation($state, $chapter, $instructorId, $now);
        });
        // Seules les attributions créées par Athena pour ce dernier chapitre sont révoquées.
        if ($awards !== []) {
            $this->store->revokeAwards($tenantId, $awards, $instructorId);
        }
        $this->store->audit('academy.learner.auto_validation_revoked', $tenantId, ['course' => $key, 'learner' => $learnerId, 'chapter' => $chapter, 'by' => $instructorId, 'revoked_awards' => $awards]);
        $this->safely(fn () => $this->store->notify($tenantId, $learnerId, 'academy_auto_revoked', ['chapter' => $chapter + 1, 'href' => '/academy/classroom?course=' . rawurlencode($key)]));
    }

    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            error_log('[academy-auto] notification : ' . $e->getMessage());
        }
    }

    private static function title(mixed $value, string $fallback): string
    {
        if (is_array($value)) {
            $value = $value['fr'] ?? $value['en'] ?? reset($value);
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }
}
