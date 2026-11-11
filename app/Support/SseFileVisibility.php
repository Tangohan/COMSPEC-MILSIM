<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fichiers SSE — ce qu'un opérateur peut lire d'une fiche : seules les données « connues des fichiers » et déjà révélées
 * (phase de révélation ≤ phase en cours de la session de lore). Le caché MJ, ce que la personne sait et les réponses de
 * l'IA ne sortent jamais vers un opérateur, ni dans une recherche, ni dans un recoupement.
 *
 * Fiche hors lore (pas de session) : tout ce qui est marqué connu est lisible, quelle que soit la phase indiquée.
 */
final class SseFileVisibility
{
    /** Phase utilisée hors session de lore : tout ce qui est connu est révélé. */
    public const NO_SESSION_PHASE = 99;

    /**
     * @param array<string, mixed>|null $profile profil hydraté (SseFileRecordRepository::hydrateProfile)
     * @param array<int, int> $phases phase en cours de chaque session
     */
    public static function phaseOf(?array $profile, array $phases): int
    {
        $sid = (int) ($profile['lore_session_id'] ?? 0);

        return $sid > 0 && isset($phases[$sid]) ? (int) $phases[$sid] : self::NO_SESSION_PHASE;
    }

    /** @param array<string, mixed>|null $profile */
    public static function field(?array $profile, string $field, int $phase): bool
    {
        $cfg = $profile['visibility'][$field] ?? null;
        if (!is_array($cfg)) {
            return true;
        }

        return ($cfg['v'] ?? 'known') !== 'hidden' && (int) ($cfg['phase'] ?? 0) <= $phase;
    }

    /** Alias, lien ou photo : connu et révélé. @param array<string, mixed> $row */
    public static function row(array $row, int $phase): bool
    {
        return ($row['visibility'] ?? 'known') !== 'hidden' && (int) ($row['reveal_phase'] ?? 0) <= $phase;
    }

    /** @param array<string, mixed>|null $profile */
    public static function inFiles(?array $profile): bool
    {
        return $profile === null || (!empty($profile['in_files']) && empty($profile['merged_into_id']));
    }

    /**
     * Profil vu par un opérateur : données cachées ou pas encore révélées retirées.
     *
     * @param array<string, mixed>|null $profile
     * @return array<string, mixed>|null
     */
    public static function operatorProfile(?array $profile, int $phase): ?array
    {
        if ($profile === null) {
            return null;
        }
        $p = $profile;
        if (!self::field($profile, 'physical', $phase)) {
            foreach (['height_cm', 'weight_kg', 'build', 'hair', 'eyes', 'complexion'] as $k) {
                $p[$k] = null;
            }
        }
        if (!self::field($profile, 'marks', $phase)) {
            $p['marks'] = null;
        }
        if (!self::field($profile, 'affiliation', $phase)) {
            $p['affiliation'] = null;
        }
        if (!self::field($profile, 'cover', $phase)) {
            $p['cover_note'] = null;
        }
        if (!self::field($profile, 'cautions', $phase)) {
            $p['caution_codes'] = [];
        }
        $p['knowledge'] = null;
        $p['ai_answers'] = [];
        $p['visibility'] = [];

        return $p;
    }

    /**
     * Identité vue par un opérateur : nom, naissance et profession masqués s'ils sont cachés.
     *
     * @param array<string, mixed> $identity
     * @param array<string, mixed>|null $profile
     * @return array<string, mixed>
     */
    public static function operatorIdentity(array $identity, ?array $profile, int $phase): array
    {
        $i = $identity;
        if (!self::field($profile, 'identity', $phase)) {
            $i['last_name'] = 'INCONNU';
            $i['first_name'] = '';
            $i['display_name'] = 'INCONNU';
            $i['birth_date'] = null;
            $i['sex'] = 'X';
        }
        if (!self::field($profile, 'birth', $phase)) {
            $i['birth_place'] = null;
            $i['nationality'] = null;
        }
        if (!self::field($profile, 'profession', $phase)) {
            $i['profession'] = null;
        }
        if (!self::field($profile, 'photo', $phase)) {
            $i['photo_path'] = null;
        }
        // Vérité de jeu jamais montrée : nature de l'identité, notes internes.
        unset($i['notes'], $i['seed']);

        return $i;
    }
}
