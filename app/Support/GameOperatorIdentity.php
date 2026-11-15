<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Grade, rôle et fonction d'un membre DANS UNE communauté, pour le profil envoyé au jeu (téléphone ATAK, dossier de
 * l'écran des rôles du mod COMSPEC Lobby).
 *
 * Toujours la communauté de la session de jeu : le grade et le rôle viennent de la ligne du membre telle que
 * UserRepository::findById($id, $tenantId) la rend (profil de communauté appliqué), pas de users.grade_id de la
 * communauté d'origine du compte ; la fonction vient des emplois de la fiche Effectifs de cette communauté.
 * La fonction est celle du membre : le jeu ne doit jamais la remplacer par le texte du poste choisi dans la mission.
 */
final class GameOperatorIdentity
{
    /**
     * @param array<string, mixed> $user ligne du membre pour la communauté (grade_id et role_id de cette communauté)
     * @return array{grade_short: string, grade_long: string, role: string, role_slug: string}
     */
    public static function gradeAndRole(PDO $pdo, array $user): array
    {
        $out = ['grade_short' => '', 'grade_long' => '', 'role' => '', 'role_slug' => ''];
        $gradeId = (int) ($user['grade_id'] ?? 0);
        if ($gradeId > 0) {
            try {
                $st = $pdo->prepare('SELECT label_short, label_long FROM grades WHERE id = ? LIMIT 1');
                $st->execute([$gradeId]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $out['grade_short'] = trim((string) ($row['label_short'] ?? ''));
                $out['grade_long'] = trim((string) ($row['label_long'] ?? ''));
            } catch (\Throwable) {
            }
        }
        $roleId = (int) ($user['role_id'] ?? 0);
        if ($roleId > 0) {
            try {
                $st = $pdo->prepare('SELECT name, slug FROM roles WHERE id = ? LIMIT 1');
                $st->execute([$roleId]);
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $out['role'] = trim((string) ($row['name'] ?? ''));
                $out['role_slug'] = trim((string) ($row['slug'] ?? ''));
            } catch (\Throwable) {
            }
        }

        return $out;
    }

    /**
     * Fonction du membre dans la communauté : son emploi principal de la fiche Effectifs (« Emploi — précision »).
     * Vide quand il n'en a pas : le jeu masque alors la ligne.
     */
    public static function jobFunction(PDO $pdo, int $tenantId, int $userId): string
    {
        if ($tenantId < 1 || $userId < 1) {
            return '';
        }
        try {
            $st = $pdo->prepare(
                'SELECT r.name AS role_name, pj.role_detail
                 FROM personnel_profile_job_roles pj
                 INNER JOIN personnel_job_roles r ON r.id = pj.personnel_job_role_id AND r.tenant_id = pj.tenant_id
                 WHERE pj.tenant_id = ? AND pj.user_id = ?
                 ORDER BY pj.is_primary DESC, pj.sort_order ASC, pj.id ASC
                 LIMIT 1'
            );
            $st->execute([$tenantId, $userId]);
            $job = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return '';
        }
        $name = trim((string) ($job['role_name'] ?? ''));
        $detail = trim((string) ($job['role_detail'] ?? ''));

        return $detail !== '' && $name !== '' ? $name . ' — ' . $detail : ($name !== '' ? $name : $detail);
    }
}
