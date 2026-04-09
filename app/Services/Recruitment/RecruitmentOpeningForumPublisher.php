<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Core\Database;
use App\Repositories\ForumPostRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RecruitmentOpeningRepository;
use PDO;

/**
 * Crée les sujets forum optionnels après publication d’une offre.
 */
final class RecruitmentOpeningForumPublisher
{
    private static ?bool $topicHasOfficialColumn = null;

    public function __construct(
        private RecruitmentOpeningRepository $openings = new RecruitmentOpeningRepository(),
        private ForumTopicRepository $topics = new ForumTopicRepository(),
        private ForumPostRepository $posts = new ForumPostRepository(),
        private RecruitmentForumCategoryEnsurer $categoryEnsurer = new RecruitmentForumCategoryEnsurer(),
        private PersonnelJobRoleRepository $jobRoles = new PersonnelJobRoleRepository()
    ) {}

    /**
     * @param array<string, mixed> $tenantRow
     *
     * @return list<string> Messages pour flash (succès ou avertissements).
     */
    public function publishForumThreads(
        int $tenantId,
        int $userId,
        int $openingId,
        array $tenantRow,
        bool $wantExterne,
        bool $wantInterne
    ): array {
        $messages = [];
        if ($userId < 1) {
            return ['Compte utilisateur invalide : annonce forum non créée.'];
        }
        if (!$wantExterne && !$wantInterne) {
            return [];
        }
        if (!$this->openings->recruitmentForumLinkColumnsExist()) {
            return ['Les annonces forum ne sont pas disponibles : exécutez les migrations sur la base de données.'];
        }
        $opening = $this->openings->findByIdForTenant($openingId, $tenantId);
        if (!$opening || (string) ($opening['status'] ?? '') !== 'published') {
            return ['L’offre n’est pas publiée : annonces forum ignorées.'];
        }

        $pdo = Database::getPdo();
        $jobRoleName = null;
        $pjr = (int) ($opening['personnel_job_role_id'] ?? 0);
        if ($pjr > 0) {
            $jr = $this->jobRoles->findRoleById($pjr, $tenantId);
            if ($jr) {
                $jobRoleName = trim((string) ($jr['name'] ?? ''));
                if ($jobRoleName === '') {
                    $jobRoleName = null;
                }
            }
        }

        $links = $this->buildPublicLinks($tenantRow, $opening);

        if ($wantExterne) {
            $existing = (int) ($opening['forum_topic_id_externe'] ?? 0);
            if ($existing > 0) {
                $messages[] = 'Une annonce sur le forum général existe déjà pour cette offre.';
            } else {
                $ensured = $this->categoryEnsurer->ensureExterneSubcategory($pdo, $tenantId);
                if (!$ensured['ok'] || empty($ensured['category_id'])) {
                    $messages[] = $ensured['error'] ?? 'Annonce forum général non créée.';
                } else {
                    $tid = $this->createThread(
                        $tenantId,
                        $userId,
                        $opening,
                        $tenantRow,
                        (int) $ensured['category_id'],
                        $links,
                        $jobRoleName,
                        'ext'
                    );
                    if ($tid === null) {
                        $messages[] = 'Échec de la création du sujet sur le forum général.';
                    } elseif ($this->openings->setForumTopicExterne($openingId, $tenantId, $tid)) {
                        $messages[] = 'Sujet créé dans le forum général (recrutement externe).';
                    } else {
                        $messages[] = 'Sujet forum créé, mais la liaison avec l’offre n’a pas été enregistrée. Contactez le support si besoin.';
                    }
                }
            }
        }

        if ($wantInterne) {
            $opening = $this->openings->findByIdForTenant($openingId, $tenantId) ?? $opening;
            $existing = (int) ($opening['forum_topic_id_interne'] ?? 0);
            if ($existing > 0) {
                $messages[] = 'Une annonce dans l’espace organisation existe déjà pour cette offre.';
            } else {
                $ensured = $this->categoryEnsurer->ensureInterneSubcategory($pdo, $tenantId);
                if (!$ensured['ok'] || empty($ensured['category_id'])) {
                    $messages[] = $ensured['error'] ?? 'Annonce forum organisation non créée.';
                } else {
                    $tid = $this->createThread(
                        $tenantId,
                        $userId,
                        $opening,
                        $tenantRow,
                        (int) $ensured['category_id'],
                        $links,
                        $jobRoleName,
                        'int'
                    );
                    if ($tid === null) {
                        $messages[] = 'Échec de la création du sujet dans l’espace organisation.';
                    } elseif ($this->openings->setForumTopicInterne($openingId, $tenantId, $tid)) {
                        $messages[] = 'Sujet créé dans l’espace réservé à l’organisation (recrutement interne).';
                    } else {
                        $messages[] = 'Sujet forum interne créé, mais la liaison avec l’offre n’a pas été enregistrée. Contactez le support si besoin.';
                    }
                }
            }
        }

        return $messages;
    }

    /**
     * @param array<string, mixed> $tenantRow
     * @param array<string, mixed> $opening
     *
     * @return array{href_fiche: string, href_candidater: string}
     */
    private function buildPublicLinks(array $tenantRow, array $opening): array
    {
        $slug = trim((string) ($tenantRow['slug'] ?? ''));
        $publicPage = trim((string) ($opening['public_page_slug'] ?? ''));
        $oid = (int) ($opening['id'] ?? 0);
        $hrefFiche = '';
        $hrefCand = '';
        if ($slug !== '') {
            if ($publicPage !== '') {
                $hrefFiche = url('c/' . rawurlencode($slug) . '/avis/' . rawurlencode($publicPage));
            }
            $hrefCand = url('c/' . rawurlencode($slug) . '/enlistment?ouverture=' . $oid);
        }

        return ['href_fiche' => $hrefFiche, 'href_candidater' => $hrefCand];
    }

    /**
     * @param array<string, mixed> $opening
     * @param array<string, mixed> $tenantRow
     * @param array{href_fiche: string, href_candidater: string} $links
     */
    private function createThread(
        int $tenantId,
        int $userId,
        array $opening,
        array $tenantRow,
        int $categoryId,
        array $links,
        ?string $jobRoleName,
        string $suffix
    ): ?int {
        try {
            $html = RecruitmentOpeningForumAnnouncementHtml::build($opening, $tenantRow, $links, $jobRoleName);
            $topicTitle = $this->buildTopicTitle($opening);
            $topicSlug = $this->buildTopicSlug($opening, $suffix);
            $topicId = $this->topics->create($tenantId, $categoryId, $userId, $topicTitle, $topicSlug);
            if ($topicId < 1) {
                return null;
            }
            if ($this->topicSupportsOfficialColumn()) {
                try {
                    $this->topics->update($topicId, $tenantId, ['is_official' => 1]);
                } catch (\Throwable) {
                }
            }
            $postId = $this->posts->create($tenantId, $topicId, $userId, $html, null, null, 'info');
            if ($postId > 0 && function_exists('forum_after_post_moderation')) {
                forum_after_post_moderation($tenantId, $userId, $postId, $html);
            }

            return $topicId;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $opening */
    private function buildTopicTitle(array $opening): string
    {
        $ref = trim((string) ($opening['reference_public'] ?? ''));
        $title = trim((string) ($opening['title'] ?? 'Offre'));
        $topicTitle = $ref !== '' ? '[' . $ref . '] ' . $title : $title;
        if (function_exists('mb_strlen') && mb_strlen($topicTitle) > 500) {
            return mb_substr($topicTitle, 0, 497) . '…';
        }
        if (strlen($topicTitle) > 500) {
            return substr($topicTitle, 0, 497) . '…';
        }

        return $topicTitle;
    }

    /** @param array<string, mixed> $opening */
    private function buildTopicSlug(array $opening, string $suffix): string
    {
        $id = (int) ($opening['id'] ?? 0);
        $publicSlug = trim((string) ($opening['public_page_slug'] ?? ''));
        $base = 'recrutement-' . $id . '-' . $suffix;
        if ($publicSlug !== '') {
            $clean = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $publicSlug));
            $clean = trim($clean, '-');
            if ($clean !== '') {
                $base .= '-' . $clean;
            }
        }
        if (function_exists('mb_strlen') && mb_strlen($base) > 250) {
            return mb_substr($base, 0, 250);
        }

        return strlen($base) > 250 ? substr($base, 0, 250) : $base;
    }

    private function topicSupportsOfficialColumn(): bool
    {
        if (self::$topicHasOfficialColumn !== null) {
            return self::$topicHasOfficialColumn;
        }
        try {
            $pdo = Database::getPdo();
            $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_topics' AND COLUMN_NAME = 'is_official' LIMIT 1");
            self::$topicHasOfficialColumn = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            self::$topicHasOfficialColumn = false;
        }

        return self::$topicHasOfficialColumn;
    }
}
