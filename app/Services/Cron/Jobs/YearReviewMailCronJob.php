<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Community\YearReviewMailer;
use App\Services\Cron\CronJobInterface;

/**
 * Annonce par e-mail de la rétrospective annuelle, les jours qui suivent sa publication (réglage dans
 * Administration du site > Rétrospective annuelle). Un seul e-mail par joueur et par année.
 */
final class YearReviewMailCronJob implements CronJobInterface
{
    public function __construct(private ?YearReviewMailer $mailer = null)
    {
    }

    public function key(): string
    {
        return 'year_review_mail';
    }

    public function label(): string
    {
        return 'Rétrospective annuelle : e-mail aux joueurs';
    }

    public function description(): string
    {
        return 'Après la publication de la rétrospective, prévient chaque joueur qui a joué dans l’année et ne l’a pas encore ouverte (un e-mail par an, coupable dans les préférences).';
    }

    public function run(): array
    {
        $r = ($this->mailer ??= new YearReviewMailer())->sendAnnouncements();
        if ($r['year'] === null) {
            return ['ok' => true, 'summary' => 'Aucune rétrospective à annoncer.', 'details' => $r];
        }

        return [
            'ok' => true,
            'summary' => sprintf(
                'Rétrospective %d : %d e-mail(s) envoyé(s), %d joueur(s) déjà prévenu(s) ou sans e-mail, %d échec(s), %d communauté(s)',
                $r['year'],
                $r['sent'],
                $r['skipped'],
                $r['failed'],
                $r['tenants']
            ),
            'details' => $r,
        ];
    }
}
