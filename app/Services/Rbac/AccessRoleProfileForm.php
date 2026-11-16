<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use InvalidArgumentException;

/**
 * Saisie de la fenêtre « Fiche du niveau » (Effectifs > Accès) : lit les champs envoyés, les valide, enregistre la fiche puis
 * le logo. Partagée par la création d'un niveau (EffectifsWorkspaceController::createAccessRole) et sa modification
 * (AccessRoleProfileController::save). La communauté et le niveau viennent toujours de l'appelant, jamais de la saisie.
 */
final class AccessRoleProfileForm
{
    /** @var \Closure(string): bool */
    private \Closure $isUploaded;

    public function __construct(
        private ?AccessRoleProfileService $profiles = null,
        private ?AccessRoleLogoStore $logos = null,
        ?callable $isUploaded = null,
    ) {
        $this->profiles ??= new AccessRoleProfileService();
        $this->logos ??= new AccessRoleLogoStore();
        $this->isUploaded = \Closure::fromCallable($isUploaded ?? static fn (string $tmp): bool => is_uploaded_file($tmp));
    }

    public function profiles(): AccessRoleProfileService
    {
        return $this->profiles;
    }

    /**
     * Champs de la fiche dans la saisie (le formulaire envoie profile_form=1 quand la fiche est présente).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function fields(array $input): array
    {
        return [
            'abbreviation' => $input['abbreviation'] ?? null,
            'details' => $input['details'] ?? '',
            'icon' => $input['icon'] ?? '',
            'tags' => $input['tags'] ?? null,
            'qualifications' => is_array($input['qualifications'] ?? null) ? $input['qualifications'] : [],
            'jobs' => is_array($input['jobs'] ?? null) ? $input['jobs'] : [],
            'grade_min' => $input['grade_min'] ?? '',
            'grade_max' => $input['grade_max'] ?? '',
            'sort_order' => $input['sort_order'] ?? '',
        ];
    }

    /**
     * Valide la fiche sans rien écrire (roleId = 0 à la création).
     *
     * @param array<string, mixed> $input
     * @return array{data: ?array<string, mixed>, error: ?string}
     */
    public function check(int $tenantId, int $roleId, array $input): array
    {
        if (($input['profile_form'] ?? '') !== '1' || !$this->profiles->ready()) {
            return ['data' => null, 'error' => null];
        }
        try {
            return ['data' => $this->profiles->validate($tenantId, $roleId, self::fields($input)), 'error' => null];
        } catch (InvalidArgumentException $e) {
            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enregistre une fiche déjà validée par check(), puis le logo (envoi ou retrait).
     *
     * @param array<string, mixed>|null $data
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file entrée de $_FILES['logo_file']
     * @return array{ok: bool, error: ?string, changed: list<string>}
     */
    public function apply(int $tenantId, int $roleId, ?array $data, array $input, ?array $file, int $userId): array
    {
        $changed = [];
        if ($data !== null) {
            $before = $this->profiles->profiles($tenantId)[$roleId] ?? AccessRoleProfileService::emptyProfile();
            if (!$this->profiles->save($tenantId, $roleId, $data, $userId)) {
                return ['ok' => false, 'error' => 'La fiche n’a pas pu être enregistrée.', 'changed' => []];
            }
            $changed = self::diff($before, $data);
        }
        if (!$this->profiles->ready() || ($input['profile_form'] ?? '') !== '1') {
            return ['ok' => true, 'error' => null, 'changed' => $changed];
        }
        // Un fichier envoyé l'emporte ; sans fichier, « Icône de la bibliothèque » choisie retire le logo éventuel.
        $iconChosen = ($input['visual'] ?? '') === 'icon';
        $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_NO_FILE) {
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                return ['ok' => false, 'error' => 'Logo refusé : 1 Mo au plus.', 'changed' => $changed];
            }
            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($error !== UPLOAD_ERR_OK || $tmp === '' || !($this->isUploaded)($tmp)) {
                return ['ok' => false, 'error' => 'Le logo n’a pas été reçu : choisissez de nouveau le fichier.', 'changed' => $changed];
            }
            if (($input['logo_confirm'] ?? '') !== '1') {
                return ['ok' => false, 'error' => 'Cochez la confirmation : le logo appartient à votre communauté ou est une création originale.', 'changed' => $changed];
            }
            $stored = $this->logos->store($tenantId, $tmp);
            if (!$stored['ok']) {
                return ['ok' => false, 'error' => $stored['error'], 'changed' => $changed];
            }
            $set = $this->profiles->setLogo($tenantId, $roleId, $stored['file'], $stored['mime'], $userId);
            if (!$set['ok']) {
                $this->logos->delete($stored['file']);

                return ['ok' => false, 'error' => 'Le logo n’a pas pu être enregistré.', 'changed' => $changed];
            }
            $this->logos->delete($set['previous']);
            $changed[] = 'logo';
        } elseif ((($input['logo_remove'] ?? '') === '1' || $iconChosen) && $this->profiles->logoOf($tenantId, $roleId) !== null) {
            $set = $this->profiles->setLogo($tenantId, $roleId, null, null, $userId);
            $this->logos->delete($set['previous']);
            $changed[] = 'logo';
        }

        return ['ok' => true, 'error' => null, 'changed' => $changed];
    }

    /** Fiche supprimée avec son niveau (lignes et fichier de logo). */
    public function forget(int $tenantId, int $roleId): void
    {
        try {
            $this->logos->delete($this->profiles->forget($tenantId, $roleId));
        } catch (\Throwable $e) {
            error_log('[acces] fiche du niveau non effacée : ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private static function diff(array $before, array $data): array
    {
        $quals = [];
        foreach ((array) $before['qualifications'] as $kind => $ids) {
            foreach ((array) $ids as $qid) {
                $quals[(int) $qid] = (string) $kind;
            }
        }
        ksort($quals);
        $newQuals = $data['qualifications'];
        ksort($newQuals);
        $tags = array_map(static fn (array $t): string => mb_strtolower((string) $t['name'], 'UTF-8'), (array) $before['tags']);
        $newTags = array_map(static fn (string $t): string => mb_strtolower($t, 'UTF-8'), $data['tags']);
        sort($tags);
        sort($newTags);
        $jobs = array_map('intval', (array) $before['jobs']);
        $newJobs = $data['jobs'];
        sort($jobs);
        sort($newJobs);
        $changed = [];
        foreach ([
            'abbreviation' => [$before['abbreviation'], $data['abbreviation']],
            'details' => [(string) $before['details'], $data['details']],
            'icon' => [$before['icon'], $data['icon']],
            'tags' => [$tags, $newTags],
            'qualifications' => [$quals, $newQuals],
            'jobs' => [$jobs, $newJobs],
            'grades' => [[$before['grade_min'], $before['grade_max']], [$data['grade_min'], $data['grade_max']]],
            'sort_order' => [(int) $before['sort_order'], $data['sort_order']],
        ] as $field => [$a, $b]) {
            if ($a !== $b) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
