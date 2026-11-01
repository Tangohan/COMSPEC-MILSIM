<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Communautés restées à un ou deux membres : qui reçoit un rappel, à quel palier, et quand la communauté entre
 * dans la liste « Organisations à supprimer » de l'administration de la plateforme. Règles pures (sans base),
 * textes des rappels en français et en anglais.
 *
 *   - palier m3 : communauté créée depuis 90 jours ou plus, 1 ou 2 membres actifs ;
 *   - palier m4 : relance à 120 jours, au moins 21 jours après le premier rappel ;
 *   - liste à supprimer : relance m4 envoyée depuis 14 jours ou plus et toujours 1 ou 2 membres.
 * Jamais : plateforme (id 1), démonstration, communauté archivée, communauté gardée par l'administration.
 * Aucune suppression automatique : l'administration passe par le retrait existant (archiver puis supprimer).
 */
final class LowMemberOrgPolicy
{
    public const MIN_MEMBERS = 1;
    public const MAX_MEMBERS = 2;

    public const STAGE_FIRST = 'm3';
    public const STAGE_SECOND = 'm4';

    public const FIRST_AFTER_DAYS = 90;
    public const SECOND_AFTER_DAYS = 120;
    /** Écart minimal entre les deux rappels (communauté déjà ancienne à la mise en service). */
    public const MIN_GAP_DAYS = 21;
    /** Délai laissé après la relance avant que la communauté entre dans la liste à supprimer. */
    public const DELETION_GRACE_DAYS = 14;

    /** Adresses courtes jamais visées (démonstration, plateforme). */
    private const PROTECTED_SLUGS = ['demo-comspec', 'comspec', 'platform', 'plateforme', 'athena'];

    /** @param array<string, mixed> $tenant ligne annuaire (id, slug, archive…) */
    public static function isProtected(array $tenant): bool
    {
        if ((int) ($tenant['id'] ?? 0) <= 1) {
            return true;
        }
        $slug = strtolower(trim((string) ($tenant['slug'] ?? '')));
        if (in_array($slug, self::PROTECTED_SLUGS, true) || str_starts_with($slug, 'demo-') || $slug === 'demo') {
            return true;
        }

        return !empty($tenant['archive']);
    }

    public static function isLowMemberCount(int $members): bool
    {
        return $members >= self::MIN_MEMBERS && $members <= self::MAX_MEMBERS;
    }

    /** Âge en jours entiers (null si date de création inconnue). */
    public static function ageDays(mixed $createdAt, int $now): ?int
    {
        $ts = UtcDateTime::timestamp($createdAt);
        if ($ts === null) {
            return null;
        }

        return intdiv(max(0, $now - $ts), 86400);
    }

    /**
     * Communauté concernée : ancienne de 3 mois, 1 ou 2 membres actifs, ni protégée ni gardée.
     *
     * @param array<string, mixed> $tenant
     */
    public static function matches(array $tenant, int $members, bool $kept, int $now): bool
    {
        if ($kept || self::isProtected($tenant) || !self::isLowMemberCount($members)) {
            return false;
        }
        $age = self::ageDays($tenant['created_at'] ?? null, $now);

        return $age !== null && $age >= self::FIRST_AFTER_DAYS;
    }

    /**
     * Palier de rappel à envoyer maintenant, ou null.
     *
     * @param array<string, mixed> $tenant
     * @param array<string, string> $sent palier => date d'envoi UTC
     */
    public static function dueStage(array $tenant, int $members, array $sent, bool $kept, int $now): ?string
    {
        if (!self::matches($tenant, $members, $kept, $now)) {
            return null;
        }
        if (!isset($sent[self::STAGE_FIRST])) {
            return self::STAGE_FIRST;
        }
        if (isset($sent[self::STAGE_SECOND])) {
            return null;
        }
        $age = (int) self::ageDays($tenant['created_at'] ?? null, $now);
        $first = UtcDateTime::timestamp($sent[self::STAGE_FIRST]) ?? $now;
        if ($age >= self::SECOND_AFTER_DAYS && $now - $first >= self::MIN_GAP_DAYS * 86400) {
            return self::STAGE_SECOND;
        }

        return null;
    }

    /**
     * Prête à supprimer : relance envoyée depuis DELETION_GRACE_DAYS et rien n'a changé.
     *
     * @param array<string, string> $sent
     */
    public static function readyForDeletion(array $sent, int $now): bool
    {
        $second = UtcDateTime::timestamp($sent[self::STAGE_SECOND] ?? null);

        return $second !== null && $now - $second >= self::DELETION_GRACE_DAYS * 86400;
    }

    /** Date à partir de laquelle la suppression peut être envisagée après la relance. */
    public static function deletionFrom(int $secondReminderAt): int
    {
        return $secondReminderAt + self::DELETION_GRACE_DAYS * 86400;
    }

    public static function locale(string $lang): string
    {
        return str_starts_with(strtolower(trim($lang)), 'en') ? 'en' : 'fr';
    }

    /**
     * Textes du rappel aux responsables (e-mail et notification du site).
     *
     * @return array{subject: string, kicker: string, title: string, hello: string, lead: string, tips_title: string,
     *               tips: list<array{label: string, key: string}>, warning: string, cta: string, notice_title: string, notice_detail: string}
     */
    public static function copy(string $lang, string $stage, string $tenantName, int $members, int $deletionFrom): array
    {
        $en = self::locale($lang) === 'en';
        $second = $stage === self::STAGE_SECOND;
        $name = $tenantName !== '' ? $tenantName : ($en ? 'Your community' : 'Votre communauté');
        $date = $en ? gmdate('F j, Y', $deletionFrom) : gmdate('d/m/Y', $deletionFrom);
        if ($en) {
            $count = $members . ' active member' . ($members > 1 ? 's' : '');
            $tips = [
                ['key' => 'invitations', 'label' => 'Send email invitations from the back office (Invitations).'],
                ['key' => 'new_member', 'label' => 'Add a member yourself from Personnel > Add a member.'],
                ['key' => 'invite_codes', 'label' => 'Create an invitation code to share on your Discord (Recruitment > Invitation codes).'],
                ['key' => 'public_page', 'label' => 'Share your community’s public page.'],
            ];

            return [
                'subject' => ($second ? 'Last reminder: ' : '') . $name . ' still has only ' . $count,
                'kicker' => 'Your community',
                'title' => $second ? 'Last reminder for ' . $name : 'Grow ' . $name,
                'hello' => 'Hello',
                'lead' => $second
                    ? $name . ' still has only ' . $count . ' four months after it was created.'
                    : $name . ' was created more than three months ago and still has only ' . $count . '.',
                'tips_title' => 'To bring your players in:',
                'tips' => $tips,
                'warning' => $second
                    ? 'Without new members, the Athena team may delete the community and its data from ' . $date . '. If you want to keep it, invite your members now.'
                    : 'Communities that stay at one or two members are deleted to keep the directory up to date. Without new members, yours may be deleted in a little over a month; you will receive one last reminder before that.',
                'cta' => 'Invite members',
                'notice_title' => $second ? 'Last reminder: your community may be deleted' : 'Your community still has few members',
                'notice_detail' => $name . ' — ' . $count . '. '
                    . ($second ? 'Invite members before ' . $date . ' to keep it.' : 'Invite your players so it is not deleted.'),
            ];
        }
        $count = $members . ' membre' . ($members > 1 ? 's' : '') . ' actif' . ($members > 1 ? 's' : '');
        $tips = [
            ['key' => 'invitations', 'label' => 'Envoyez des invitations par e-mail depuis le back-office (Invitations).'],
            ['key' => 'new_member', 'label' => 'Ajoutez vous-même un membre depuis Effectifs > Ajouter un membre.'],
            ['key' => 'invite_codes', 'label' => 'Créez un code d’invitation à partager sur votre Discord (Recrutement > Codes d’invitation).'],
            ['key' => 'public_page', 'label' => 'Partagez la page publique de votre communauté.'],
        ];

        return [
            'subject' => ($second ? 'Dernier rappel : ' : '') . $name . ' ne compte que ' . $count,
            'kicker' => 'Votre communauté',
            'title' => $second ? 'Dernier rappel pour ' . $name : 'Faites grandir ' . $name,
            'hello' => 'Bonjour',
            'lead' => $second
                ? $name . ' ne compte toujours que ' . $count . ' quatre mois après sa création.'
                : $name . ' a été créée il y a plus de trois mois et ne compte toujours que ' . $count . '.',
            'tips_title' => 'Pour faire venir vos joueurs :',
            'tips' => $tips,
            'warning' => $second
                ? 'Sans nouveaux membres, l’équipe Athena pourra supprimer la communauté et ses données à partir du ' . $date . '. Pour la garder, invitez vos membres dès maintenant.'
                : 'Les communautés qui restent à un ou deux membres sont supprimées pour garder l’annuaire à jour. Sans nouveaux membres, la vôtre pourra l’être dans un peu plus d’un mois ; vous recevrez un dernier rappel avant.',
            'cta' => 'Inviter des membres',
            'notice_title' => $second ? 'Dernier rappel : votre communauté pourra être supprimée' : 'Votre communauté compte encore peu de membres',
            'notice_detail' => $name . ' — ' . $count . '. '
                . ($second ? 'Invitez des membres avant le ' . $date . ' pour la garder.' : 'Invitez vos joueurs pour qu’elle ne soit pas supprimée.'),
        ];
    }
}
