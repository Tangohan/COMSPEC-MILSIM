<?php

declare(strict_types=1);

namespace Tests\Unit\Pdf;

use App\Services\Sse\SseCasePdfService;
use App\Services\Sse\SseTransmissionPdfService;
use App\Support\TrainingCertificatePdfEngine;
use PHPUnit\Framework\TestCase;

/**
 * PDF SSE au gabarit Athena : dossier complet (expurgé), fiche de transmission et journal,
 * réellement produits en A4 portrait avec en-tête et pied sur chaque page.
 */
final class SsePdfTemplateTest extends TestCase
{
    use AthenaPdfAssertions;

    protected function setUp(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded()) {
            self::markTestSkipped('TCPDF indisponible');
        }
    }

    public function testCaseDossierPdf(): void
    {
        // Les dépendances (dépôts) ne servent qu'à export() : on construit sans constructeur.
        $svc = (new \ReflectionClass(SseCasePdfService::class))->newInstanceWithoutConstructor();

        $case = [
            'id' => 42,
            'reference_code' => 'SSE-2026-0042',
            'title' => 'Réseau logistique — vallée de Zargabad',
            'classification' => 'confidentiel',
            'classification_label' => 'Confidentiel',
            'status' => 'ouvert',
            'status_label' => 'En cours',
            'summary' => "Suivi d'un réseau d'approvisionnement en armes légères entre deux villages.\nTrois personnes identifiées, un site fouillé.",
            'has_unlock_code' => 1,
            'created_at' => '2026-08-20 10:00:00',
            'updated_at' => '2026-08-28 18:30:00',
        ];
        $people = [
            ['id' => 1, 'display_name' => 'Karim Haddad', 'alias' => 'Le Tailleur', 'status_label' => 'Détenu',
                'biometric_samples' => [['kind_label' => 'Empreintes'], ['kind_label' => 'Iris']],
                'watchlist' => [['score' => 87, 'reason' => 'empreintes', 'entry' => ['display_name' => 'HVT-12']]]],
            ['id' => 2, 'display_name' => 'Inconnu n° 2', 'status_label' => 'Relâché', 'watchlist' => []],
        ];
        $sites = [[
            'id' => 7, 'reference_code' => 'S-07', 'name' => 'Ferme isolée', 'site_type_label' => 'Habitation', 'status_label' => 'Exploité',
            'rooms' => [['label' => 'Cuisine', 'checked' => 1], ['label' => 'Cave', 'checked' => 0]],
            'seizures' => [['label' => 'Fusil AK-47', 'quantity' => 3, 'location_label' => 'Cave'], ['label' => 'Téléphone']],
        ]];
        $notes = [['classification_label' => 'Confidentiel', 'author_label' => 'SGT Martin', 'created_at' => '2026-08-21 09:12', 'body' => "Source locale fiable.\nÀ recouper."]];
        $evidence = [
            ['label' => 'Carnet de comptes', 'caption' => 'Saisi dans la cave', 'author_label' => 'CPL Durand', 'created_at' => '2026-08-22', 'image_path' => 'assets/atak-native/capture-map.jpg'],
            ['label' => 'Douille 7,62', 'caption' => '', 'author_label' => 'CPL Durand', 'created_at' => '2026-08-22'],
        ];
        $relations = [['from_type' => 'person', 'from_id' => 1, 'relation' => 'frequente', 'to_type' => 'site', 'to_id' => 7, 'reliability' => 'B', 'note' => 'Vu à trois reprises']];
        $flash = "FLASH OPÉRATIONNEL\nDossier : SSE-2026-0042\nNiveau : Confidentiel\n\nSITUATION\nTrois individus interpellés au point de contrôle nord.";
        $initial = "COMPTE RENDU INITIAL\nRédacteur : SGT Martin\n\nFAITS\nFouille de la ferme isolée, saisie de trois fusils.";

        $pdf = TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(
            static fn (): string => $svc->buildPdfBinary($case, $people, $sites, $notes, $evidence, $relations, $flash, $initial, 'interne')
        );

        self::assertAthenaTcpdf($pdf, 'sse-dossier');
    }

    public function testTransmissionEventPdf(): void
    {
        $svc = new SseTransmissionPdfService();
        $event = [
            'id' => 318,
            'event_type_label' => 'Fiche de renseignement',
            'summary' => 'Individu contrôlé au check-point nord',
            'event_time' => '2026-08-28 00:28:00',
            'created_at' => '2026-08-28 00:28:04',
            'confidence_code' => 'B2',
            'source_system_label' => 'Tablette',
            'author_label' => 'YA1 / Bravo',
            'unit_label' => '2e section',
            'client_label' => 'Athena SSE 1.4',
        ];
        $payload = [
            'source_system' => 'ARMA_SSE',
            'display_name' => 'Karim Haddad', 'last_name' => 'Haddad', 'first_name' => 'Karim', 'alias' => 'Le Tailleur',
            'nationality' => 'Takistanaise', 'age_estimated' => 34,
            'circumstances' => 'Contrôle de routine, comportement nerveux, documents incohérents avec la déclaration orale.',
            'consent_recorded' => true, 'biometrics_simulated' => false,
            'grid_reference' => '045 112', 'pos_x' => 4521.3, 'pos_y' => 11208.9, 'pos_z' => 12.0,
            'submitter_callsign' => 'YA1',
            'client' => ['mod_version' => '1.4.2', 'arma_version' => '2.18'],
        ];
        $html = $svc->eventHtml($event, $payload, ['name' => 'Karim Haddad', 'alias' => 'Le Tailleur', 'ref' => 'P-0012', 'prints' => 'OK', 'quality' => 82], '28/08/2026 à 02:00');
        $pdf = TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(
            static fn (): string => $svc->buildPdf($html, 'Fiche de renseignement', 'L', 'TX-318', [], ['kind' => 'Fiche de transmission terrain', 'community' => 'Task Force Athena'])
        );

        self::assertStringContainsString('Karim Haddad', $html);
        self::assertAthenaTcpdf($pdf, 'sse-transmission-fiche');
    }

    public function testTransmissionJournalPdf(): void
    {
        $svc = new SseTransmissionPdfService();
        $natures = ['Fiche de renseignement', 'Rapport reçu', 'Photo', 'Position'];
        $events = [];
        for ($i = 60; $i >= 1; $i--) {
            $events[] = [
                'id' => 300 + $i,
                'event_time' => sprintf('2026-08-28 %02d:%02d:00', intdiv($i, 4) % 24, ($i * 7) % 60),
                'event_type_label' => $natures[$i % 4],
                'source_system_label' => $i % 2 ? 'Tablette' : 'Arma 3',
                'summary' => 'Transmission terrain n° ' . $i . ' — observation d’un véhicule suspect au carrefour',
                'author_label' => 'YA' . ($i % 3 + 1) . ' / Bravo',
                'confidence_code' => ['A1', 'C3', 'E5', ''][$i % 4],
            ];
        }
        $html = $svc->journalHtml($events, '28/08/2026 à 02:00', 'Dernières 24 h');
        $pdf = TrainingCertificatePdfEngine::suppressTcpdfPhpDeprecationsWhile(
            static fn (): string => $svc->buildPdf($html, 'Journal des transmissions terrain', 'L', 'Journal des transmissions', [], ['kind' => 'Journal des transmissions', 'reference' => 'SSE-TX-20260828', 'community' => 'Task Force Athena'])
        );

        self::assertStringContainsString('Journal des transmissions terrain', $html);
        self::assertAthenaTcpdf($pdf, 'sse-transmission-journal');
    }
}
