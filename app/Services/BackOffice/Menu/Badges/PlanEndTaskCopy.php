<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Menu\Badges;

use App\Services\BackOffice\Menu\PendingTaskDetail;
use App\Support\PlanAccessWindow;
use App\Support\PlanGift;

/**
 * Texte de la tâche « formule » du tableau de bord, selon l'état de la communauté :
 *   - elle se termine bientôt (essai fondateur, formule offerte ou période payée par PayPal) ;
 *   - elle est terminée et la période de grâce court encore ;
 *   - elle est terminée depuis quelques jours (sans grâce, ou grâce écoulée).
 *
 * Calcul pur à partir de la ligne tenants (PlanAccessWindow) : les noms de formule, la présence d'ATAK dans une
 * formule et le propriétaire sont fournis par l'appelant. Les textes sont composés dans la langue demandée (comme
 * PlanAccessWindow::copy), sans passer par la traduction de la page. La page du bouton est « Offres et formules », qui
 * propose le paiement PayPal ; l'activation après paiement est faite par l'équipe Athena.
 */
final class PlanEndTaskCopy
{
    /** Page « Offres et formules » : choix de la formule et paiement PayPal. */
    public const PAGE = 'platform/upgrade';

    /**
     * @param array<string, mixed> $tenant ligne tenants
     * @param callable(string): string $planName nom lisible d'une formule d'après son identifiant
     * @param callable(string): bool $hasAtak la formule comprend-elle ATAK (photos, vidéo drone, carte complète)
     * @param int $graceDays durée de la période de grâce réglée par la plateforme, 0 si elle est désactivée
     * @param string $owner nom du propriétaire de la communauté, '' s'il est inconnu
     */
    public static function compose(
        array $tenant,
        string $locale,
        callable $planName,
        callable $hasAtak,
        int $graceDays,
        string $owner = '',
        ?int $now = null,
    ): ?PendingTaskDetail {
        $now ??= time();
        $lang = PlanGift::locale($locale);
        $comm = trim((string) ($tenant['name'] ?? ''));
        $comm = $comm !== '' ? $comm : ($lang === 'en' ? 'Your community' : 'Votre communauté');
        $base = trim((string) ($tenant['plan_slug'] ?? 'free'));
        $base = $base !== '' ? $base : 'free';

        $soon = PlanAccessWindow::endingSoon($tenant, PlanEndBadge::WARNING_DAYS, $now);
        if ($soon !== null) {
            $atakLost = $hasAtak($soon['plan_slug']) && !$hasAtak($soon['kind'] === 'paid' ? 'free' : $base);

            return self::ending($lang, $comm, $planName($soon['plan_slug']), $soon['kind'], $soon['ends_at'], $soon['days_left'], $atakLost, $graceDays, $owner);
        }

        $lapsed = PlanAccessWindow::lapsed($tenant, $now);
        $endedTs = $lapsed !== null ? strtotime($lapsed['ended_at']) : false;
        if ($lapsed === null || $endedTs === false || $endedTs < $now - PlanEndBadge::LAPSED_DAYS * 86400) {
            return null;
        }
        $name = $planName($lapsed['plan_slug']);
        $atakLost = $hasAtak($lapsed['plan_slug']) && !$hasAtak($base);
        $since = max(0, intdiv($now - $endedTs, 86400));
        $grace = PlanAccessWindow::grace($tenant, $graceDays, $now);

        return self::lapsed($lang, $comm, $name, $lapsed['kind'], $lapsed['ended_at'], $since, $grace, $atakLost, $owner);
    }

    private static function ending(string $lang, string $comm, string $plan, string $kind, string $endsAt, int $daysLeft, bool $atakLost, int $graceDays, string $owner): PendingTaskDetail
    {
        $date = PlanGift::formatDate($endsAt, $lang);
        $stops = self::stops($lang, $atakLost);
        $endTs = strtotime($endsAt);
        if ($graceDays > 0 && $endTs !== false) {
            $graceEnd = PlanGift::formatDate(date('c', $endTs + $graceDays * 86400), $lang);
            $consequence = $lang === 'en'
                ? 'After ' . $date . ', everything keeps working until ' . $graceEnd . ' (grace period). After that, ' . $stops . '.'
                : 'Après le ' . $date . ', tout continue de fonctionner jusqu’au ' . $graceEnd . ' (période de grâce). Ensuite, ' . $stops . '.';
        } else {
            $consequence = ($lang === 'en' ? 'After ' : 'Après le ') . $date . ', ' . $stops . '.';
        }
        $paid = $kind === 'paid';

        if ($lang === 'en') {
            $in = ' (in ' . $daysLeft . ' day' . ($daysLeft > 1 ? 's' : '') . ')';
            $explain = match ($kind) {
                'paid' => 'The “' . $plan . '” plan paid for through PayPal for “' . $comm . '” ends on ' . $date . '. It does not renew by itself: unless you pay again, it stops that day.',
                'gift' => '“' . $comm . '” has a gifted plan, “' . $plan . '”, that ends on ' . $date . '. No payment has failed: the offer simply comes to an end. After that, the community goes back to its regular plan.',
                default => '“' . $comm . '” is on the founder trial (“' . $plan . '” plan), which ends on ' . $date . '. No payment has failed: the trial simply comes to an end. After that, the community goes back to its regular plan.',
            };
            $action = $paid
                ? 'Before that date, choose the plan you want to keep and pay for it with PayPal. The Athena team activates it as soon as the payment arrives.'
                : 'To carry on after that date, move to a paid plan: choose it before the end and pay for it with PayPal. The Athena team activates it as soon as the payment arrives. For any question, contact the platform team.';
            $title = match ($kind) {
                'paid' => 'Your “' . $plan . '” plan ends on ' . $date,
                'gift' => 'Your gifted “' . $plan . '” plan ends on ' . $date,
                default => 'Your founder trial (“' . $plan . '” plan) ends on ' . $date,
            };

            return self::detail($lang, $title . $in, $explain, $consequence, $action, $paid, $owner);
        }

        $dans = ' (dans ' . $daysLeft . ' jour' . ($daysLeft > 1 ? 's' : '') . ')';
        $explain = match ($kind) {
            'paid' => 'La formule « ' . $plan . ' » payée par PayPal pour « ' . $comm . ' » se termine le ' . $date . '. Elle ne se renouvelle pas toute seule : sans nouveau paiement, elle s’arrête ce jour-là.',
            'gift' => 'Votre communauté « ' . $comm . ' » bénéficie d’une formule offerte, « ' . $plan . ' », qui se termine le ' . $date . '. Ce n’est pas un paiement qui a échoué : l’offre arrive simplement à son terme. Ensuite, la communauté revient à sa formule habituelle.',
            default => 'Votre communauté « ' . $comm . ' » profite de l’essai fondateur (formule « ' . $plan . ' »), qui se termine le ' . $date . '. Ce n’est pas un paiement qui a échoué : l’essai arrive simplement à son terme. Ensuite, la communauté revient à sa formule habituelle.',
        };
        $action = $paid
            ? 'Avant cette date, choisissez la formule à garder et payez-la par PayPal. L’équipe Athena l’active dès réception du paiement.'
            : 'Pour continuer après cette date, passez à une formule payante : choisissez-la avant la fin et payez-la par PayPal. L’équipe Athena l’active dès réception du paiement. Pour toute question, contactez l’équipe de la plateforme.';
        $title = match ($kind) {
            'paid' => 'Votre formule « ' . $plan . ' » se termine le ' . $date,
            'gift' => 'Votre formule offerte « ' . $plan . ' » se termine le ' . $date,
            default => 'Votre essai fondateur (formule « ' . $plan . ' ») se termine le ' . $date,
        };

        return self::detail($lang, $title . $dans, $explain, $consequence, $action, $paid, $owner);
    }

    /**
     * @param array{grace_ends_at: string, days_left: int}|null $grace
     */
    private static function lapsed(string $lang, string $comm, string $plan, string $kind, string $endedAt, int $since, ?array $grace, bool $atakLost, string $owner): PendingTaskDetail
    {
        $ended = PlanGift::formatDate($endedAt, $lang);
        $paid = $kind === 'paid';
        $graceEnd = $grace !== null ? PlanGift::formatDate($grace['grace_ends_at'], $lang) : '';
        $left = $grace !== null ? $grace['days_left'] : 0;

        if ($lang === 'en') {
            $noun = match ($kind) {
                'paid' => 'Your “' . $plan . '” plan',
                'gift' => 'Your gifted “' . $plan . '” plan',
                default => 'Your founder trial (“' . $plan . '” plan)',
            };
            $what = match ($kind) {
                'paid' => 'The “' . $plan . '” plan paid for through PayPal for “' . $comm . '” ended on ' . $ended . '.',
                'gift' => 'The gifted “' . $plan . '” plan of “' . $comm . '” ended on ' . $ended . '. No payment failed: the offer simply came to an end.',
                default => 'The founder trial of “' . $comm . '” (“' . $plan . '” plan) ended on ' . $ended . '. No payment failed: the trial simply came to an end.',
            };
            $how = $since < 1 ? 'ended today' : 'ended ' . $since . ' day' . ($since > 1 ? 's' : '') . ' ago';
            $go = $paid
                ? 'choose a plan and pay for it with PayPal. The Athena team activates it as soon as the payment arrives.'
                : 'move to a paid plan and pay for it with PayPal. The Athena team activates it as soon as the payment arrives. For any question, contact the platform team.';
            if ($graceEnd !== '') {
                $title = $noun . ' ' . $how . ' (grace period until ' . $graceEnd . ')';
                $explain = $what . ' To give you time to react, everything keeps working during a grace period, until ' . $graceEnd . ' (' . $left . ' day' . ($left > 1 ? 's' : '') . ' left).';
                $consequence = 'After ' . $graceEnd . ', ' . self::stops($lang, $atakLost) . '.';
                $action = 'Before ' . $graceEnd . ', ' . $go;
            } else {
                $title = $noun . ' ' . $how;
                $explain = $what . ' The community is back on its regular plan.';
                $consequence = $atakLost
                    ? 'Photos, drone video and the full tactical map are stopped (positions, reports and briefing from Arma 3 keep working), along with the modules your regular plan does not include.'
                    : 'The modules your regular plan does not include are locked for all your members.';
                $action = ucfirst($go) . ' Everything comes back once it is active.';
            }

            return self::detail($lang, $title, $explain, $consequence, $action, $paid, $owner);
        }

        $fem = $kind !== 'trial';
        $noun = match ($kind) {
            'paid' => 'Votre formule « ' . $plan . ' »',
            'gift' => 'Votre formule offerte « ' . $plan . ' »',
            default => 'Votre essai fondateur (formule « ' . $plan . ' »)',
        };
        $what = match ($kind) {
            'paid' => 'La formule « ' . $plan . ' » payée par PayPal pour « ' . $comm . ' » a pris fin le ' . $ended . '.',
            'gift' => 'La formule offerte « ' . $plan . ' » de « ' . $comm . ' » a pris fin le ' . $ended . '. Ce n’est pas un paiement qui a échoué : l’offre est simplement arrivée à son terme.',
            default => 'L’essai fondateur de « ' . $comm . ' » (formule « ' . $plan . ' ») a pris fin le ' . $ended . '. Ce n’est pas un paiement qui a échoué : l’essai est simplement arrivé à son terme.',
        };
        $how = $since < 1
            ? ($fem ? 's’est terminée aujourd’hui' : 's’est terminé aujourd’hui')
            : ($fem ? 'est terminée' : 'est terminé') . ' depuis ' . $since . ' jour' . ($since > 1 ? 's' : '');
        $go = $paid
            ? 'choisissez une formule et payez-la par PayPal. L’équipe Athena l’active dès réception du paiement.'
            : 'passez à une formule payante et payez-la par PayPal. L’équipe Athena l’active dès réception du paiement. Pour toute question, contactez l’équipe de la plateforme.';
        if ($graceEnd !== '') {
            $title = $noun . ' ' . $how . ' (période de grâce jusqu’au ' . $graceEnd . ')';
            $explain = $what . ' Pour vous laisser le temps de réagir, tout continue de fonctionner pendant une période de grâce, jusqu’au ' . $graceEnd . ' (encore ' . $left . ' jour' . ($left > 1 ? 's' : '') . ').';
            $consequence = 'Après le ' . $graceEnd . ', ' . self::stops($lang, $atakLost) . '.';
            $action = 'Avant le ' . $graceEnd . ', ' . $go;
        } else {
            $title = $noun . ' ' . $how;
            $explain = $what . ' La communauté est revenue à sa formule habituelle.';
            $consequence = $atakLost
                ? 'Les photos, la vidéo drone et la carte tactique complète sont arrêtées (positions, comptes rendus et briefing envoyés depuis Arma 3 continuent), ainsi que les modules absents de votre formule habituelle.'
                : 'Les modules absents de votre formule habituelle sont verrouillés pour tous vos membres.';
            $action = ucfirst($go) . ' Tout redevient disponible dès qu’elle est active.';
        }

        return self::detail($lang, $title, $explain, $consequence, $action, $paid, $owner);
    }

    /** Ce qui s'arrête à la fin de la formule (sans majuscule ni point : la phrase est complétée par l'appelant). */
    private static function stops(string $lang, bool $atakLost): string
    {
        if ($lang === 'en') {
            return $atakLost
                ? 'photos, drone video and the full tactical map stop (positions, reports and briefing from Arma 3 keep working), along with the modules your regular plan does not include'
                : 'the modules your regular plan does not include are locked';
        }

        return $atakLost
            ? 'les photos, la vidéo drone et la carte tactique complète s’arrêtent (positions, comptes rendus et briefing envoyés depuis Arma 3 continuent), ainsi que les modules absents de votre formule habituelle'
            : 'les modules absents de votre formule habituelle sont verrouillés';
    }

    private static function detail(string $lang, string $title, string $explain, string $consequence, string $action, bool $paid, string $owner): PendingTaskDetail
    {
        if ($lang === 'en') {
            $button = $paid ? 'Renew my plan' : 'Choose a paid plan';
            $who = 'Community managers (the owner, the founders and the administrators) can renew or change the plan, with a PayPal payment. If paying is not your call, tell the community owner'
                . ($owner !== '' ? ' (' . $owner . ')' : '') . '.';
            $help = 'A plan is your community’s subscription to Athena: it decides which features are available (photos, drone video, the full tactical map, modules…). It can come from a founder trial, a gift from the Athena team, or a PayPal payment for one month or one year. Nothing renews automatically. When the platform has turned on a grace period, everything keeps working for a few days after the end date.';
        } else {
            $button = $paid ? 'Renouveler ma formule' : 'Choisir une formule payante';
            $who = 'Les responsables de la communauté (propriétaire, fondateurs et administrateurs) peuvent renouveler ou changer la formule, par un paiement PayPal. Si ce n’est pas à vous de payer, prévenez le propriétaire de la communauté'
                . ($owner !== '' ? ' (' . $owner . ')' : '') . '.';
            $help = 'Une formule est l’abonnement de votre communauté à Athena : elle décide des fonctions disponibles (photos, vidéo drone, carte tactique complète, modules…). Elle peut venir d’un essai fondateur, d’un cadeau de l’équipe Athena ou d’un paiement PayPal pour un mois ou un an. Rien ne se renouvelle automatiquement. Quand la plateforme a activé une période de grâce, tout continue de fonctionner quelques jours après la date de fin.';
        }

        return new PendingTaskDetail($title, $explain, $consequence, $action, $button, self::PAGE, $who, $help, true, $lang);
    }
}
