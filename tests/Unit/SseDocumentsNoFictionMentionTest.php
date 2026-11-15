<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseWantedNoticePdf;
use App\Support\SseDocumentChromeCatalog;
use App\Support\SseFictionMentions;
use App\Support\SseIdWorkshopCatalog;
use App\Support\SseSheetCatalog;
use App\Support\SseWantedCatalog;
use App\Support\SseWantedServices;
use App\Support\TrainingCertificatePdfEngine;
use PHPUnit\Framework\TestCase;
use Tests\Support\PdfText;

/**
 * Documents et PDF du bureau SSE : plus aucune mention de jeu (« Document de fiction », « jeu de rôle », « usage RP »,
 * « preuve judiciaire », « (fictif) », filigrane « FICTION - RP »…), en français comme en anglais.
 *
 * Tout est rendu pour de vrai, sans doublure : la notice de recherche en PDF, le document de rédaction (gabarit papier) avec
 * chaque modèle de présentation livré, et les textes que les gabarits impriment (catalogue des services, textes par défaut
 * des fiches, mentions des papiers). Les fiches d'une personne (chaque modèle, deux langues) sont couvertes par SseSheetTest,
 * les rapports du TOC par AtakTocReportPdfTest, avec le même lecteur de PDF (Tests\Support\PdfText).
 *
 * Les garde-fous restent vérifiés ailleurs : aucune organisation réelle (SseWantedServicesCatalogTest), bandeaux de
 * classification (SseWantedNoticePdfContentTest), marquage des papiers des pays réels stylisés (SseIdRealCountryTest).
 */
final class SseDocumentsNoFictionMentionTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Source sans commentaires (PHP ou SQF) : ce qui peut finir à l'écran ou sur le papier. */
    private static function code(string $rel): string
    {
        $src = (string) file_get_contents(self::root() . '/' . $rel);
        $src = (string) preg_replace('~/\*.*?\*/~s', '', $src);

        return (string) preg_replace('~^\s*//.*$~m', '', $src);
    }

    /** @return array<string, mixed> */
    private static function notice(string $lang, string $faces): array
    {
        return [
            'lang' => $lang, 'banner' => ['NOTICE ROUGE', 'RED NOTICE'],
            'authority' => [(string) SseWantedCatalog::DEFAULTS['authority_fr'], (string) SseWantedCatalog::DEFAULTS['authority_en']],
            'ref' => 'A-0123/2026', 'issued_at' => '2026-11-14 10:00:00', 'expires_at' => '2027-01-01 00:00:00',
            'last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'sex' => 'F', 'birth_date' => '1984-03-12', 'birth_place' => 'Zargabad',
            'nationality' => 'Takistan (TAK)', 'height_cm' => 172, 'weight_kg' => 64, 'eyes' => 'Marron', 'hair' => 'Noirs', 'marks' => 'Cicatrice à l’arcade gauche',
            'aliases' => ['Abou Hamza'], 'languages' => 'pachto, anglais', 'type_label' => 'Recherche en vue d’arrestation', 'threat_level' => 'high',
            'threat_label' => 'Élevé', 'armed' => 'yes', 'reason' => 'Participation présumée à l’attaque du poste avancé.', 'instructions' => 'Signaler au service demandeur.',
            'zone' => 'Takistan', 'last_seen_place' => 'Poste nord', 'last_seen_at' => '2026-11-10 18:30:00',
            'contact_label' => (string) SseWantedCatalog::DEFAULTS['contact_label'], 'contact_detail' => 'FALCON NET · +1 555 0121',
            'photo' => null, 'extra_photos' => [], 'signature' => null, 'emblem_path' => null, 'faces' => $faces, 'generated_at' => '15/11/2026 10:00 UTC',
            'classification' => ['banner' => 'SECRET//NOFORN', 'rgb' => [200, 16, 46]],
        ];
    }

    /** @param array<string, mixed> $chrome */
    private static function paper(array $chrome, bool $live = false): string
    {
        return (static function (array $documentChrome, bool $livePreview): string {
            $document = [
                'id' => 12, 'title' => 'Note de synthèse', 'body' => "Paragraphe d’essai.\n\nSecond paragraphe.", 'reference_code' => 'DOC-2026-0012',
                'classification' => 'confidentiel', 'classification_label' => 'Secret (S)', 'status' => 'valide', 'status_label' => 'Validé',
                'document_type_label' => 'Note de renseignement', 'author_label' => 'Analyste Marek', 'case_reference' => 'DOS-0007',
                'created_at' => '2026-11-10 09:00:00', 'updated_at' => '2026-11-12 09:00:00', 'validated_at' => '2026-11-13 09:00:00',
            ];
            $unitLabel = 'Task Force Orion';
            $sectionLabel = 'Bureau SSE — Renseignement';
            ob_start();
            require dirname(__DIR__, 2) . '/views/atak/sse/partials/document_paper.php';

            return (string) ob_get_clean();
        })($chrome, $live);
    }

    public function testTheMentionListCatchesFrenchAndEnglishWordings(): void
    {
        foreach ([
            'Document de fiction — jeu de rôle Arma 3', 'Fictional document — Arma 3 role-play', 'USAGE JEU DE RÔLE UNIQUEMENT', 'ROLE-PLAY USE ONLY',
            'Ne constitue pas une preuve judiciaire — usage RP / renseignement uniquement.', 'Document de renseignement — usage RP uniquement.',
            'FICTION - RP', 'for RP use', 'RP only', 'Cellule de coordination (fictive)', 'Joint Cell (fictional)', 'SPÉCIMEN · SIMULATION', 'specimen',
            'Document de jeu – sans valeur', 'Game document – no value', 'roleplay', 'carte factice',
        ] as $text) {
            self::assertNotNull(SseFictionMentions::find($text), $text);
        }
        foreach (['SECRET//NOFORN', 'Cellule de coordination des recherches', 'Exemplaire 2/5', 'Page 1 sur 2', 'Registre', 'ARPEGE', 'Rapport de patrouille', 'CARPE DIEM'] as $text) {
            self::assertNull(SseFictionMentions::find($text), $text);
        }
    }

    public function testWantedNoticePdfHasNoFictionMentionInFrenchAndEnglish(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF et ses polices requis');
        }
        foreach (['fr', 'en'] as $lang) {
            foreach (['1', '2'] as $faces) {
                $pdf = (new SseWantedNoticePdf())->render(self::notice($lang, $faces));
                self::assertIsString($pdf, "$lang $faces");
                $text = PdfText::of($pdf);
                // Le lecteur lit bien les pages : référence, bandeau de classification et pagination y figurent toujours.
                self::assertStringContainsString('A-0123/2026', $text, "$lang $faces");
                self::assertStringContainsString('SECRET//NOFORN', $text, "$lang $faces : le bandeau de classification reste");
                self::assertStringContainsString('Page 1/' . $faces, $text, "$lang $faces : la pagination reste");
                self::assertNull(PdfText::fictionMention($pdf), "$lang $faces : mention de jeu sur la notice");
            }
        }
    }

    public function testWritingDocumentPaperHasNoFictionMentionWithEveryDeliveredPrefab(): void
    {
        $prefabs = SseDocumentChromeCatalog::builtInPrefabs();
        self::assertNotSame([], $prefabs);
        foreach ($prefabs as $prefab) {
            foreach ([false, true] as $live) {
                $html = self::paper($prefab, $live);
                $label = (string) $prefab['code'] . ($live ? ' (aperçu en direct)' : '');
                self::assertStringContainsString('DOC-2026-0012', $html, $label);
                self::assertNull(SseFictionMentions::find(strip_tags($html)), $label . ' : mention de jeu sur le document');
                // Pas de pied vide ni de filet orphelin : sans texte, l'élément du pied n'est pas rendu du tout.
                self::assertStringNotContainsString('sse-doc-paper__disclaimer', $html, $label);
                // Le chrome réel reste : bandeau de classification, pagination, numéro de contrôle.
                self::assertStringContainsString('sse-doc-paper__banner--bottom', $html, $label);
                self::assertMatchesRegularExpression('/Page 1 sur \d+/', $html, $label);
                self::assertStringContainsString('SECRET (S)', $html, $label);
            }
        }
        // Sans modèle (ancien appel) : même résultat.
        self::assertStringNotContainsString('sse-doc-paper__disclaimer', self::paper([]));
    }

    public function testAStoredLegacyFooterIsNoLongerShownButACommunityFooterIs(): void
    {
        foreach (SseDocumentChromeCatalog::LEGACY_FOOTERS as $legacy) {
            self::assertMatchesRegularExpression('/\bRP\b/', $legacy, 'ancien pied livré : ' . $legacy);
            $html = self::paper(['footer' => $legacy] + SseDocumentChromeCatalog::defaultChrome());
            self::assertStringNotContainsString('sse-doc-paper__disclaimer', $html, $legacy);
            self::assertNull(SseFictionMentions::find(strip_tags($html)), $legacy);
        }
        $custom = 'Exemplaire du bureau — ne pas reproduire.';
        $html = self::paper(['footer' => $custom] + SseDocumentChromeCatalog::defaultChrome());
        self::assertStringContainsString('<p class="sse-doc-paper__disclaimer">' . $custom . '</p>', $html, 'un pied écrit par la communauté reste affiché');
        // Un pied presque identique à l'ancien, mais retouché par la communauté, n'est pas considéré comme livré.
        $edited = SseDocumentChromeCatalog::LEGACY_FOOTERS[0] . ' Bureau Orion.';
        self::assertSame($edited, SseDocumentChromeCatalog::footerText($edited));
    }

    public function testDeliveredNamesAndPrintedTextsCarryNoFictionMention(): void
    {
        // Services demandeurs livrés : noms, autorités et coordonnées imprimés sur les avis et les fiches.
        $services = SseWantedServices::catalogue();
        self::assertGreaterThan(20, count($services));
        foreach ($services as $code => $service) {
            foreach (['fr', 'en', 'auth_fr', 'auth_en', 'ac', 'contact'] as $key) {
                if (isset($service[$key]) && is_string($service[$key])) {
                    self::assertNull(SseFictionMentions::find($service[$key]), $code . '.' . $key . ' : ' . $service[$key]);
                    self::assertDoesNotMatchRegularExpression('/\bRP\b/', $service[$key], $code . '.' . $key);
                }
            }
            foreach (['fr', 'en'] as $lang) {
                foreach (SseWantedServices::prefill($service, $lang) as $key => $value) {
                    if (is_string($value)) {
                        self::assertNull(SseFictionMentions::find($value), $code . ' prérempli ' . $key . ' (' . $lang . ')');
                    }
                }
            }
        }
        // Réglages par défaut de la notice (autorité, service) et textes par défaut des fiches, dans les deux langues.
        foreach (SseWantedCatalog::DEFAULTS as $key => $value) {
            if (is_string($value)) {
                self::assertNull(SseFictionMentions::find($value), 'notice.' . $key);
                self::assertDoesNotMatchRegularExpression('/\bRP\b/', $value, 'notice.' . $key);
            }
        }
        $sheetTexts = [SseSheetCatalog::MODELS, SseSheetCatalog::TEXTS, SseSheetCatalog::DEFAULTS];
        array_walk_recursive($sheetTexts, static function (mixed $value, mixed $key): void {
            if (is_string($value)) {
                self::assertNull(SseFictionMentions::find($value), 'fiche : ' . $value);
            }
        });
        // Mentions imprimées sur les papiers par les gabarits de l'atelier.
        $workshop = (string) file_get_contents(self::root() . '/app/Support/SseIdWorkshopCatalog.php');
        self::assertTrue(class_exists(SseIdWorkshopCatalog::class));
        preg_match_all("/'remarks' => '([^']*)'/", $workshop, $remarks);
        self::assertNotSame([], $remarks[1]);
        foreach ($remarks[1] as $remark) {
            self::assertNull(SseFictionMentions::find($remark), 'papier : ' . $remark);
        }
    }

    public function testStoredDeliveredNamesAreCleanedOnReadButCommunityNamesAreKept(): void
    {
        self::assertSame('Agence interservices du renseignement', SseFictionMentions::cleanName('Agence interservices du renseignement — RP'));
        self::assertSame('Interservice Intelligence Agency', SseFictionMentions::cleanName('Interservice Intelligence Agency — RP'));
        self::assertSame('Cellule de contre-terrorisme interarmées', SseFictionMentions::cleanName('Cellule de contre-terrorisme interarmées (fictive)'));
        self::assertSame('Joint Counter-Terrorism Cell', SseFictionMentions::cleanName('Joint Counter-Terrorism Cell (fictional)'));
        // Nom saisi par une communauté : jamais touché, même s'il porte le mot.
        foreach (['Cellule des opérations du Détroit (fictive)', 'Bureau Orion — RP', 'Régiment (fictif) de marche', 'Agence interservices du renseignement — RP 2'] as $custom) {
            self::assertSame($custom, SseFictionMentions::cleanName($custom), $custom);
        }
        self::assertNull(SseFictionMentions::cleanNullable(null));
        $snapshot = SseFictionMentions::cleanServiceSnapshot(['code' => 'SVC-CTC', 'fr' => 'Cellule de contre-terrorisme interarmées (fictive)', 'en' => 'Joint Counter-Terrorism Cell (fictional)', 'ac' => 'JCTC', 'color' => '#1F3A5F']);
        self::assertSame(['code' => 'SVC-CTC', 'fr' => 'Cellule de contre-terrorisme interarmées', 'en' => 'Joint Counter-Terrorism Cell', 'ac' => 'JCTC', 'color' => '#1F3A5F'], $snapshot);
        // Réglages enregistrés avec l'ancienne autorité par défaut : lus sans « — RP ».
        $settings = \App\Repositories\SseWantedSettingsRepository::normalize(['authority_fr' => 'Agence interservices du renseignement — RP', 'authority_en' => 'Interservice Intelligence Agency — RP']);
        self::assertSame('Agence interservices du renseignement', $settings['authority_fr']);
        self::assertSame('Interservice Intelligence Agency', $settings['authority_en']);
        self::assertArrayNotHasKey('watermark', $settings);
        self::assertArrayNotHasKey('footer', $settings);
    }

    public function testTemplatesAndInGameSheetsNoLongerPrintAFictionMention(): void
    {
        // Gabarits du site (code sans commentaires) : ce qu'ils impriment ne porte plus de mention.
        foreach ([
            'app/Services/Sse/SseSheetPdf.php', 'app/Services/Sse/SseWantedNoticePdf.php', 'app/Services/AtakToc/TocReportPdf.php',
            'views/atak/sse/partials/document_paper.php', 'views/atak/sse/files/subject_print.php', 'app/Support/SseDocumentChromeCatalog.php',
        ] as $file) {
            $code = self::code($file);
            if ($file === 'app/Support/SseDocumentChromeCatalog.php') {
                // La liste des anciens pieds sert justement à ne plus les afficher.
                $code = (string) preg_replace('/public const LEGACY_FOOTERS = \[.*?\];/s', '', $code);
            }
            self::assertNull(SseFictionMentions::find($code), $file . ' : ' . (string) SseFictionMentions::find($code));
        }
        // Mod SSE et app Fiches de l'ATAK : textes affichés en jeu.
        $chrome = self::code('mod/@COMSPEC_SSE/addons/ui/functions/fn_getDocumentChrome.sqf');
        self::assertNull(SseFictionMentions::find($chrome), 'modèles de présentation du mod');
        self::assertSame(6, substr_count($chrome, '["footer", ""]'), 'pied de page vide pour le défaut et les cinq modèles');
        $fill = self::code('mod/@COMSPEC_SSE/addons/ui/functions/fn_fillResultDialog.sqf');
        self::assertStringContainsString('if (_footer isNotEqualTo "") then {', $fill, 'sans pied de page, aucune ligne vide sous la qualité');
        self::assertStringContainsString('_chrome getOrDefault ["footer", ""]', $fill);
        foreach (SseDocumentChromeCatalog::LEGACY_FOOTERS as $legacy) {
            self::assertStringContainsString('"' . $legacy . '"', $fill, 'ancien pied ignoré en jeu : ' . $legacy);
        }
        $files = self::code('mod/@COMSPEC_SSE/addons/identity/functions/fn_filePage.sqf');
        self::assertStringNotContainsString('FICHIERS · SIMULATION', $files);
        self::assertStringNotContainsString('données fictives', $files);
        self::assertNull(SseFictionMentions::find(self::code('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/fiches/fn_pageFiches.sqf')), 'app Fiches');
        // Les nouveaux textes en jeu ont leur traduction anglaise.
        $dict = (string) file_get_contents(self::root() . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        self::assertStringContainsString('["FICHIERS", "RECORDS"],', $dict);
        self::assertStringContainsString('["Les fiches viennent du portail Athena et sont filtrées selon votre habilitation.", "Records come from the Athena portal and are filtered according to your clearance."],', $dict);
        self::assertStringNotContainsString('["FICHIERS · SIMULATION"', $dict);
    }

    public function testRealCountryPapersKeepTheirMarkingGuardrail(): void
    {
        // Garde-fou inchangé : un pays réel stylisé garde toujours une marque visible et le bandeau à l'export.
        self::assertSame('discreet', \App\Support\SseIdMarking::effective('none', true));
        self::assertSame('standard', \App\Support\SseIdMarking::effective('standard', true));
        $js = (string) file_get_contents(self::root() . '/public/assets/js/sse-id-workshop.js');
        self::assertStringContainsString("if (m === 'none' && real) return 'discreet';", $js);
        self::assertSame('DOCUMENT DE JEU – SANS VALEUR', \App\Support\SseIdRealCountryCatalog::EXPORT_BAND);
    }
}
