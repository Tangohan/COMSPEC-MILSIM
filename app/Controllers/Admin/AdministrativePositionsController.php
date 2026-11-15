<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\AdministrativePositionCatalog as Catalog;
use App\Support\EffectifsLmsAccess;
use App\Support\SqlText;
use PDO;
use Throwable;

/**
 * Effectifs › Positions administratives : les quatre positions du site (lecture) et celles que la communauté crée,
 * modifie ou archive (table tenant_administrative_positions). Une position créée se choisit ensuite sur la fiche d'un
 * membre comme les autres ; elle n'est jamais changée automatiquement (fin d'accueil, rattrapage).
 *
 * Lecture : pilotage des effectifs (page servie par EffectifsWorkspaceController::positions). Écriture : gestion des accès.
 */
final class AdministrativePositionsController
{
    public const LABEL_MAX = 60;
    public const SUMMARY_MAX = 255;
    public const DETAIL_MAX = 1000;
    public const SHORT_MAX = 60;
    public const MAX_CUSTOM = 30;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public static function url(): string
    {
        return effectifs_workspace_url('positions');
    }

    /** @return array<string, mixed> */
    public function pageData(int $tenantId): array
    {
        Catalog::forget($tenantId);
        $custom = Catalog::customFor($tenantId);

        return [
            'posBuiltIn' => Catalog::builtIn(),
            'posCustom' => $custom,
            'posHolders' => $this->holderCounts($tenantId, array_column(Catalog::all($tenantId), 'slug')),
            'posReady' => $this->ready(),
            'posCanManage' => EffectifsLmsAccess::canManageRoles(Gate::getInstance()),
            'posEditing' => null,
            'csrfToken' => Csrf::token(),
        ];
    }

    /**
     * Valeurs du formulaire nettoyées, ou message d'erreur.
     *
     * @param array<string, mixed> $in
     * @param list<string> $takenLabels libellés déjà pris (minuscules), hors la position modifiée
     * @return array{0: array<string, string>|null, 1: string}
     */
    public static function clean(array $in, array $takenLabels): array
    {
        $line = static fn (mixed $v, int $max): string => mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $v))), 0, $max);
        $label = $line($in['label'] ?? '', self::LABEL_MAX);
        if (mb_strlen($label) < 2) {
            return [null, 'Donnez un nom à la position (2 caractères au moins).'];
        }
        if (in_array(mb_strtolower($label), $takenLabels, true)) {
            return [null, 'Une position porte déjà ce nom.'];
        }
        $tone = (string) ($in['tone'] ?? 'muted');

        return [[
            'label' => $label,
            'summary' => $line($in['summary'] ?? '', self::SUMMARY_MAX),
            'detail' => mb_substr(trim(strip_tags((string) ($in['detail'] ?? ''))), 0, self::DETAIL_MAX),
            'mission' => $line($in['mission'] ?? '', self::SHORT_MAX),
            'service' => $line($in['service'] ?? '', self::SHORT_MAX),
            'strength' => $line($in['strength'] ?? '', self::SHORT_MAX),
            'tone' => in_array($tone, Catalog::TONES, true) ? $tone : 'muted',
        ], ''];
    }

    /** POST effectifs/positions : créer ou modifier une position de la communauté. */
    public function save(Request $request, array $params = []): Response
    {
        [$tenantId, $userId, $denied] = $this->guard($request);
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) $request->input('id', 0);
        $custom = Catalog::customFor($tenantId);
        $editing = null;
        foreach ($custom as $p) {
            if ($p['id'] === $id) {
                $editing = $p;
            }
        }
        if ($id > 0 && $editing === null) {
            Session::flash('error', 'Position introuvable.');

            return Response::redirect(self::url());
        }
        if ($editing === null && count($custom) >= self::MAX_CUSTOM) {
            Session::flash('error', self::MAX_CUSTOM . ' positions au plus. Archivez-en une avant d’en créer une autre.');

            return Response::redirect(self::url());
        }
        $taken = [];
        foreach (Catalog::all($tenantId) as $p) {
            if ($editing === null || $p['slug'] !== $editing['slug']) {
                $taken[] = mb_strtolower((string) $p['label']);
            }
        }
        [$values, $error] = self::clean([
            'label' => $request->input('label', ''),
            'summary' => $request->input('summary', ''),
            'detail' => $request->input('detail', ''),
            'mission' => $request->input('mission', ''),
            'service' => $request->input('service', ''),
            'strength' => $request->input('strength', ''),
            'tone' => $request->input('tone', 'muted'),
        ], $taken);
        if ($values === null) {
            Session::flash('error', $error);

            return Response::redirect(self::url() . ($editing !== null ? '?modifier=' . $id : '') . '#position-form');
        }
        try {
            if ($editing === null) {
                $slug = Catalog::CUSTOM_PREFIX . bin2hex(random_bytes(5));
                $this->db()->prepare(
                    'INSERT INTO tenant_administrative_positions (tenant_id, slug, label, summary, detail, mission, service, strength, tone, display_order, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute([$tenantId, $slug, $values['label'], $values['summary'], $values['detail'], $values['mission'], $values['service'], $values['strength'], $values['tone'], count($custom) + 1, $userId]);
                Session::flash('success', 'Position « ' . $values['label'] . ' » créée. Elle se choisit sur la fiche d’un membre.');
            } else {
                $this->db()->prepare(
                    'UPDATE tenant_administrative_positions SET label = ?, summary = ?, detail = ?, mission = ?, service = ?, strength = ?, tone = ?, updated_at = NOW()
                     WHERE id = ? AND tenant_id = ?'
                )->execute([$values['label'], $values['summary'], $values['detail'], $values['mission'], $values['service'], $values['strength'], $values['tone'], $id, $tenantId]);
                // Le rôle d'affichage porté par les membres suit le nouveau nom.
                $this->db()->prepare('UPDATE roles SET name = ?, description = ? WHERE tenant_id = ? AND ' . SqlText::equals($this->db(), 'slug'))
                    ->execute([$values['label'], $values['summary'], $tenantId, $editing['slug']]);
                Session::flash('success', 'Position « ' . $values['label'] . ' » enregistrée.');
            }
            Catalog::forget($tenantId);
            $this->audit($tenantId, $userId, $editing === null ? 'administrative_position.created' : 'administrative_position.updated', $values['label']);
        } catch (Throwable $e) {
            error_log('[positions] enregistrement : ' . $e->getMessage());
            Session::flash('error', 'La position n’a pas pu être enregistrée. Réessayez dans un instant.');
        }

        return Response::redirect(self::url());
    }

    /** POST effectifs/positions/archiver : retire une position des choix ; refusé tant qu'un membre la porte. */
    public function archive(Request $request, array $params = []): Response
    {
        [$tenantId, $userId, $denied] = $this->guard($request);
        if ($denied !== null) {
            return $denied;
        }
        $id = (int) $request->input('id', 0);
        $target = null;
        foreach (Catalog::customFor($tenantId) as $p) {
            if ($p['id'] === $id) {
                $target = $p;
            }
        }
        if ($target === null) {
            Session::flash('error', 'Position introuvable.');

            return Response::redirect(self::url());
        }
        $holders = $this->holderCounts($tenantId, [$target['slug']])[$target['slug']] ?? 0;
        if ($holders > 0) {
            Session::flash('error', $holders . ' membre(s) portent « ' . $target['label'] . ' ». Changez d’abord leur position sur leur fiche.');

            return Response::redirect(self::url());
        }
        try {
            $this->db()->prepare('UPDATE tenant_administrative_positions SET archived_at = NOW(), updated_at = NOW() WHERE id = ? AND tenant_id = ?')
                ->execute([$id, $tenantId]);
            Catalog::forget($tenantId);
            $this->audit($tenantId, $userId, 'administrative_position.archived', $target['label']);
            Session::flash('success', 'Position « ' . $target['label'] . ' » archivée : elle n’est plus proposée.');
        } catch (Throwable $e) {
            error_log('[positions] archivage : ' . $e->getMessage());
            Session::flash('error', 'La position n’a pas pu être archivée. Réessayez dans un instant.');
        }

        return Response::redirect(self::url());
    }

    public function ready(): bool
    {
        try {
            return $this->db()->query("SHOW TABLES LIKE 'tenant_administrative_positions'")->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Membres actifs qui portent chaque position.
     *
     * @param list<string> $slugs
     * @return array<string, int>
     */
    private function holderCounts(int $tenantId, array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }
        $out = [];
        try {
            $in = implode(',', array_fill(0, count($slugs), '?'));
            $st = $this->db()->prepare(
                "SELECT r.slug, COUNT(DISTINCT x.user_id) AS n FROM roles r
                 INNER JOIN (SELECT user_id, role_id FROM user_roles UNION SELECT user_id, role_id FROM tenant_user_roles WHERE tenant_id = ?) x ON x.role_id = r.id
                 INNER JOIN users u ON u.id = x.user_id AND u.status = 'active'
                 WHERE r.tenant_id = ? AND r.slug IN ($in) GROUP BY r.slug"
            );
            $st->execute(array_merge([$tenantId, $tenantId], $slugs));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(string) $row['slug']] = (int) $row['n'];
            }
        } catch (Throwable $e) {
            error_log('[positions] titulaires : ' . $e->getMessage());
        }

        return $out;
    }

    /** @return array{0: int, 1: int, 2: Response|null} */
    private function guard(Request $request): array
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return [0, 0, Response::redirect(url('login'))];
        }
        if (!Csrf::verify($request)) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return [$tenantId, $userId, Response::redirect(self::url())];
        }
        if (!EffectifsLmsAccess::canManageRoles(Gate::getInstance())) {
            Session::flash('error', 'Seuls les responsables des accès gèrent les positions administratives.');

            return [$tenantId, $userId, Response::redirect(self::url())];
        }
        if (!$this->ready()) {
            Session::flash('error', 'Les positions de la communauté arrivent avec la prochaine mise à jour du site (étape tenant_administrative_positions).');

            return [$tenantId, $userId, Response::redirect(self::url())];
        }

        return [$tenantId, $userId, null];
    }

    private function audit(int $tenantId, int $userId, string $action, string $label): void
    {
        try {
            (new \App\Services\Audit\AuditService())->log($action, $tenantId, $userId ?: null, 'administrative_position', null, null, $label);
        } catch (Throwable $e) {
            error_log('[positions] audit : ' . $e->getMessage());
        }
    }
}
