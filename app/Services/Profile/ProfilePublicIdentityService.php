<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Repositories\ForumAuthorIdentityRepository;

/**
 * Résout le nom affiché public (forum, listes) et les champs modérateur (identité légale).
 */
final class ProfilePublicIdentityService
{
    public const MODE_DISPLAY_NAME = 'display_name';

    public const MODE_CALLSIGN = 'callsign';

    public const MODE_CHARACTER_NAME = 'character_name';

    public const MODE_FORUM_ALIAS = 'forum_alias';

    /**
     * @param array<string, mixed> $user users row fragment: id, email, display_name, callsign
     * @param array<string, mixed>|null $userProfile user_profiles row or null
     * @param array<string, mixed>|null $personnelProfile personnel_profiles row or null
     * @param array<string, mixed>|null $settings user_profile_display_settings or null
     */
    public function resolvePublicDisplayName(
        array $user,
        ?array $userProfile,
        ?array $personnelProfile,
        ?array $settings
    ): string {
        $email = trim((string) ($user['email'] ?? ''));
        $displayName = trim((string) ($user['display_name'] ?? ''));
        $callsign = trim((string) ($user['callsign'] ?? ''));
        $characterName = trim((string) ($personnelProfile['character_name'] ?? ''));
        $forumAlias = trim((string) ($settings['forum_alias'] ?? ''));
        if ($forumAlias !== '') {
            return $forumAlias;
        }
        $mode = (string) ($settings['forum_label_mode'] ?? self::MODE_DISPLAY_NAME);
        if (!in_array($mode, [self::MODE_DISPLAY_NAME, self::MODE_CALLSIGN, self::MODE_CHARACTER_NAME, self::MODE_FORUM_ALIAS], true)) {
            $mode = self::MODE_DISPLAY_NAME;
        }
        $chains = [
            self::MODE_DISPLAY_NAME => [$displayName, $callsign, $characterName, $this->emailLocalPart($email)],
            self::MODE_CALLSIGN => [$callsign, $displayName, $characterName, $this->emailLocalPart($email)],
            self::MODE_CHARACTER_NAME => [$characterName, $displayName, $callsign, $this->emailLocalPart($email)],
            self::MODE_FORUM_ALIAS => [$displayName, $callsign, $characterName, $this->emailLocalPart($email)],
        ];
        foreach ($chains[$mode] as $c) {
            if ($c !== '') {
                return $c;
            }
        }

        return 'Membre';
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed>|null $userProfile
     */
    public function resolveLegalFullName(array $user, ?array $userProfile): string
    {
        $fn = trim((string) ($userProfile['first_name'] ?? ''));
        $ln = trim((string) ($userProfile['last_name'] ?? ''));
        $full = trim($fn . ' ' . $ln);
        if ($full !== '') {
            return $full;
        }

        return trim((string) ($user['display_name'] ?? ''));
    }

    /**
     * @param array<string, mixed> $postRow ligne forum enrichie (author_*)
     * @return array{public_display_name: string, legal_full_name: string, author_user_id: int, author_email: string}
     */
    public function resolvePublicNameFromAuthorRow(array $authorRow): string
    {
        return $this->resolvePublicDisplayName(
            [
                'id' => (int) ($authorRow['author_user_id'] ?? 0),
                'email' => (string) ($authorRow['author_email'] ?? ''),
                'display_name' => (string) ($authorRow['author_name'] ?? ''),
                'callsign' => (string) ($authorRow['author_callsign'] ?? ''),
            ],
            [
                'first_name' => $authorRow['author_first_name'] ?? null,
                'last_name' => $authorRow['author_last_name'] ?? null,
            ],
            [
                'character_name' => $authorRow['author_character_name'] ?? null,
            ],
            [
                'forum_alias' => $authorRow['author_forum_alias'] ?? null,
                'forum_label_mode' => $authorRow['author_forum_label_mode'] ?? self::MODE_DISPLAY_NAME,
            ]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $topics
     * @return array<int, array<string, mixed>>
     */
    public function enrichTopicRowsWithPublicNames(array $topics, ForumAuthorIdentityRepository $identityRepo, int $tenantId): array
    {
        $ids = [];
        foreach ($topics as $t) {
            $tid = (int) ($t['topic_author_user_id'] ?? $t['user_id'] ?? 0);
            if ($tid > 0) {
                $ids[] = $tid;
            }
            if (!empty($t['last_post_user_id'])) {
                $ids[] = (int) $t['last_post_user_id'];
            }
        }
        $map = $identityRepo->fetchMapForTenantAndUserIds($tenantId, $ids);
        foreach ($topics as $i => $t) {
            $ta = (int) ($t['topic_author_user_id'] ?? $t['user_id'] ?? 0);
            $lp = (int) ($t['last_post_user_id'] ?? 0);
            $topics[$i]['topic_author_display'] = ($ta && isset($map[$ta]))
                ? $this->resolvePublicNameFromAuthorRow($map[$ta])
                : (string) ($t['author_name'] ?? '');
            $topics[$i]['last_post_author_name'] = ($lp && isset($map[$lp]))
                ? $this->resolvePublicNameFromAuthorRow($map[$lp])
                : (string) ($t['last_post_author_name_legacy'] ?? $t['author_name'] ?? '—');
        }

        return $topics;
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    public function enrichCategoryRowsWithLastAuthor(array $categories, ForumAuthorIdentityRepository $identityRepo, int $tenantId): array
    {
        $ids = array_filter(array_map('intval', array_column($categories, 'last_post_user_id')));
        $map = $identityRepo->fetchMapForTenantAndUserIds($tenantId, $ids);
        foreach ($categories as $i => $c) {
            $uid = (int) ($c['last_post_user_id'] ?? 0);
            $categories[$i]['last_post_author'] = ($uid && isset($map[$uid]))
                ? $this->resolvePublicNameFromAuthorRow($map[$uid])
                : '—';
        }

        return $categories;
    }

    /**
     * @param array<int, array<string, mixed>> $topics
     * @return array<int, array<string, mixed>>
     */
    public function enrichRecentTopicRows(array $topics, ForumAuthorIdentityRepository $identityRepo, int $tenantId): array
    {
        $ids = array_filter(array_map('intval', array_column($topics, 'last_post_user_id')));
        $map = $identityRepo->fetchMapForTenantAndUserIds($tenantId, $ids);
        foreach ($topics as $i => $t) {
            $uid = (int) ($t['last_post_user_id'] ?? 0);
            $topics[$i]['last_author_name'] = ($uid && isset($map[$uid]))
                ? $this->resolvePublicNameFromAuthorRow($map[$uid])
                : '—';
        }

        return $topics;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function enrichContributorRows(array $rows, ForumAuthorIdentityRepository $identityRepo, int $tenantId): array
    {
        $ids = array_column($rows, 'id');
        $ids = array_filter(array_map('intval', $ids), fn ($id) => $id > 0);
        $map = $identityRepo->fetchMapForTenantAndUserIds($tenantId, $ids);
        foreach ($rows as $i => $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id && isset($map[$id])) {
                $rows[$i]['display_name_resolved'] = $this->resolvePublicNameFromAuthorRow($map[$id]);
            } else {
                $rows[$i]['display_name_resolved'] = (string) ($r['display_name'] ?? '');
            }
        }

        return $rows;
    }

    public function enrichFromForumPostRow(array $postRow): array
    {
        $user = [
            'id' => (int) ($postRow['user_id'] ?? $postRow['author_user_id'] ?? 0),
            'email' => (string) ($postRow['author_email'] ?? ''),
            'display_name' => (string) ($postRow['author_name'] ?? ''),
            'callsign' => (string) ($postRow['author_callsign'] ?? ''),
        ];
        $userProfile = [
            'first_name' => $postRow['author_first_name'] ?? null,
            'last_name' => $postRow['author_last_name'] ?? null,
        ];
        $personnelProfile = [
            'character_name' => $postRow['author_character_name'] ?? null,
        ];
        $settings = [
            'forum_alias' => $postRow['author_forum_alias'] ?? null,
            'forum_label_mode' => $postRow['author_forum_label_mode'] ?? self::MODE_DISPLAY_NAME,
        ];
        $public = $this->resolvePublicDisplayName($user, $userProfile, $personnelProfile, $settings);
        $legal = $this->resolveLegalFullName($user, $userProfile);

        return [
            'public_display_name' => $public,
            'legal_full_name' => $legal,
            'author_user_id' => (int) ($postRow['user_id'] ?? $postRow['author_user_id'] ?? 0),
            'author_email' => (string) ($user['email'] ?? ''),
        ];
    }

    /**
     * Applique les préférences d’affichage forum de l’auteur (masque matricule, grade, etc. pour les non-modérateurs).
     *
     * @param array<string, mixed> $postRow
     * @return array<string, mixed>
     */
    public function filterAuthorFieldsForForumViewer(array $postRow, bool $viewerIsModerator): array
    {
        if ($viewerIsModerator) {
            return $postRow;
        }
        $showMat = !isset($postRow['author_show_matricule_forum']) || (int) $postRow['author_show_matricule_forum'] === 1;
        $showGr = !isset($postRow['author_show_grade_forum']) || (int) $postRow['author_show_grade_forum'] === 1;
        $showUn = !isset($postRow['author_show_unit_forum']) || (int) $postRow['author_show_unit_forum'] === 1;
        $showBio = !isset($postRow['author_show_bio_forum']) || (int) $postRow['author_show_bio_forum'] === 1;
        if (!$showMat) {
            $postRow['author_matricule'] = null;
        }
        if (!$showGr) {
            $postRow['author_grade_name'] = null;
            $postRow['author_grade_short'] = null;
            $postRow['author_grade_nato'] = null;
        }
        if (!$showUn) {
            $postRow['author_unit_name'] = null;
            $postRow['author_unit_code'] = null;
            $postRow['author_primary_role'] = null;
        }
        if (!$showBio) {
            $postRow['author_bio'] = null;
        }

        return $postRow;
    }

    /**
     * Forum « plateforme » (catégorie scope=platform) : carte auteur allégée — rôle communauté uniquement, sans ORBAT / dossier militaire.
     *
     * @param array<string, mixed> $postRow
     * @return array<string, mixed>
     */
    public function filterAuthorCardForPlatformForum(array $postRow): array
    {
        $postRow['author_matricule'] = null;
        $postRow['author_grade_name'] = null;
        $postRow['author_grade_short'] = null;
        $postRow['author_grade_nato'] = null;
        $postRow['author_primary_role'] = null;
        $postRow['author_unit_name'] = null;
        $postRow['author_unit_code'] = null;
        $postRow['author_unit_depth'] = null;
        $postRow['author_awards'] = null;

        return $postRow;
    }

    private function emailLocalPart(string $email): string
    {
        if ($email === '') {
            return '';
        }
        $at = strpos($email, '@');

        return $at !== false ? substr($email, 0, $at) : $email;
    }
}
