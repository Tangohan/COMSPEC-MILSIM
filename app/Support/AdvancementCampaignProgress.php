<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Où en est une campagne d’avancement, en cinq étapes lisibles par un gestionnaire :
 * préparer → éligibles → propositions → arbitrage → publication.
 */
final class AdvancementCampaignProgress
{
    public const STEPS = [
        1 => ['key' => 'preparer', 'label' => 'Préparer', 'text' => 'Le grade visé, la période de candidatures, le quota et la commission sont fixés.'],
        2 => ['key' => 'eligibles', 'label' => 'Éligibles', 'text' => 'Le système repère les membres du grade précédent qui remplissent les règles du grade visé.'],
        3 => ['key' => 'propositions', 'label' => 'Propositions', 'text' => 'Chaque membre détecté reçoit un score expliqué ; vous retenez ou écartez chaque proposition, les membres peuvent aussi se porter candidats.'],
        4 => ['key' => 'arbitrage', 'label' => 'Arbitrage', 'text' => 'La commission classe les candidats, donne son avis et décide qui est inscrit au tableau, dans la limite du quota.'],
        5 => ['key' => 'publication', 'label' => 'Publication', 'text' => 'Le tableau est publié : les inscrits reçoivent leur nouveau grade et un message. On ne revient pas en arrière.'],
    ];

    /**
     * @param array<string, mixed> $campaign
     * @param list<array<string, mixed>> $candidacies lignes décorées (live_eligible, is_forced)
     * @return array{step:int, done:bool, candidates:int, eligible:int, opinions:int, listed:int, ranked:int, quota:?int, days_left:?int, window:string, next:string}
     */
    public static function summarize(array $campaign, array $candidacies, ?\DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $status = (string) ($campaign['status'] ?? '');
        $candidates = count($candidacies);
        $eligible = 0;
        $opinions = 0;
        $listed = 0;
        $ranked = 0;
        foreach ($candidacies as $row) {
            if (!empty($row['live_eligible']) || !empty($row['is_eligible']) || !empty($row['is_forced'])) {
                $eligible++;
            }
            if (trim((string) ($row['commission_opinion'] ?? '')) !== '') {
                $opinions++;
            }
            if ((string) ($row['decision'] ?? '') === 'inscrit') {
                $listed++;
            }
            if (($row['preference_rank'] ?? null) !== null && $row['preference_rank'] !== '') {
                $ranked++;
            }
        }
        $quotaRaw = $campaign['quota_slots'] ?? null;
        $quota = $quotaRaw === null || $quotaRaw === '' ? null : (int) $quotaRaw;

        $opens = substr((string) ($campaign['opens_at'] ?? ''), 0, 10);
        $closes = substr((string) ($campaign['closes_at'] ?? ''), 0, 10);
        $day = $today->format('Y-m-d');
        $window = 'open';
        if ($opens !== '' && strcmp($day, $opens) < 0) {
            $window = 'before';
        } elseif ($closes !== '' && strcmp($day, $closes) > 0) {
            $window = 'after';
        }
        $daysLeft = null;
        if ($closes !== '' && $window !== 'after') {
            try {
                $daysLeft = (int) $today->diff(new \DateTimeImmutable($closes))->format('%a');
            } catch (\Throwable) {
                $daysLeft = null;
            }
        }

        $done = false;
        switch ($status) {
            case 'publiee':
            case AdvancementCodes::CAMPAIGN_PUBLISHED:
            case 'archivee':
            case AdvancementCodes::CAMPAIGN_ARCHIVED:
                $step = 5;
                $done = true;
                $next = 'Tableau publié : les grades sont attribués et les inscrits ont été prévenus.';
                break;
            case 'en_commission':
            case AdvancementCodes::CAMPAIGN_IN_COMMISSION:
                $step = 4;
                if ($candidates === 0) {
                    $next = 'Aucun candidat : la commission n’a rien à examiner. Publiez un tableau vide ou laissez la campagne en l’état.';
                } elseif ($opinions < $candidates || $listed === 0) {
                    $next = 'Ouvrez la commission : classez les candidats, donnez un avis et choisissez les inscrits (' . $opinions . ' avis sur ' . $candidates . ').';
                } else {
                    $next = 'Les décisions sont prises (' . $listed . ' inscrit(s)) : vérifiez le tableau puis publiez-le.';
                }
                break;
            case 'cloturee':
            case AdvancementCodes::CAMPAIGN_CLOSED:
                $step = 4;
                $next = 'Les candidatures sont closes : passez la campagne en commission pour arbitrer.';
                break;
            default:
                if ($window === 'before') {
                    $step = 2;
                    $next = 'Les candidatures ouvrent le ' . self::date($opens) . '. Vous pouvez déjà consulter les membres détectés.';
                } elseif ($window === 'after') {
                    $step = 3;
                    $next = 'La période de candidatures est terminée : clôturez-la puis passez en commission.';
                } elseif ($candidates === 0) {
                    $step = 2;
                    $next = 'Examinez les propositions du système et retenez les membres à présenter, ou ajoutez une candidature.';
                } else {
                    $step = 3;
                    $next = $candidates . ' candidat(s) retenu(s). À la fin de la période, passez en commission.';
                }
        }

        return [
            'step' => $step,
            'done' => $done,
            'candidates' => $candidates,
            'eligible' => $eligible,
            'opinions' => $opinions,
            'listed' => $listed,
            'ranked' => $ranked,
            'quota' => $quota,
            'days_left' => $daysLeft,
            'window' => $window,
            'next' => $next,
        ];
    }

    /** « 12 mars 2027 » dans la langue du lecteur. */
    public static function date(string $iso): string
    {
        $ts = strtotime(substr($iso, 0, 10));
        if ($ts === false || $iso === '') {
            return $iso;
        }

        return (int) date('j', $ts) . ' ' . LocalizedCalendar::month((int) date('n', $ts)) . ' ' . date('Y', $ts);
    }
}
