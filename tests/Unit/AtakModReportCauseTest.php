<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Monitoring\AtakModReportCause;
use App\Services\Monitoring\AtakModReportDiscordTicketService;
use PHPUnit\Framework\TestCase;

final class AtakModReportCauseTest extends TestCase
{
    private const REFUSED = 'Session refusée par le poste — transmissions arrêtées';

    public function testCauseComesFromTheServerDiagnosis(): void
    {
        self::assertSame('Formule sans la carte', AtakModReportCause::label('Refus', 'error: feature_unavailable'));
        self::assertSame('Sans communauté', AtakModReportCause::label(self::REFUSED, 'tenant_context_required'));
        self::assertSame('Compte désactivé', AtakModReportCause::label(self::REFUSED, 'Diagnostic Athena : Compte Athena ou fiche membre désactivé pour ce Steam'));
        self::assertSame('Joueur à reconnecter', AtakModReportCause::label(self::REFUSED, 'Diagnostic Athena : Aucune session Athena ouverte depuis le jeu'));
        self::assertSame('Clé de communauté manquante', AtakModReportCause::label(self::REFUSED, 'Aucune session Athena ouverte. Aucune clé d’accès ATAK configurée pour la communauté.'));
        self::assertSame('Panne serveur', AtakModReportCause::label('ÉCHEC · HTTP POST — code 503', ''));
        self::assertSame('', AtakModReportCause::label(self::REFUSED, ''));
    }

    public function testThreadTitleShowsTheCauseFirst(): void
    {
        $title = AtakModReportDiscordTicketService::threadName([
            'id' => 835,
            'message' => self::REFUSED,
            'detail_text' => 'tenant_context_required',
        ]);

        self::assertStringStartsWith('#835 · Sans communauté · Session refusée', $title);
    }
}
