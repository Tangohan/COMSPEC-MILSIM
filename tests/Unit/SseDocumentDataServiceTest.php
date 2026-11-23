<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseDocumentDataService;
use App\Support\OrgVisibilityCapabilities;
use App\Support\SseDocumentLocks;
use App\Support\SseRichText;
use PHPUnit\Framework\TestCase;

/**
 * Insertion de données de la communauté (ORBAT, fiche membre, effectif) : refus hors périmètre, niveaux de visibilité de l'ORBAT
 * respectés, personnel masqué ou anonymisé jamais nommé, aucune donnée d'état civil, classification minimale recalculée par le
 * serveur depuis data-src, bloc figé et verrouillé. Données fictives, aucune base.
 */
final class SseDocumentDataServiceTest extends TestCase
{
    private function caps(bool $restricted = false, bool $hidden = false, bool $all = false): OrgVisibilityCapabilities
    {
        $c = new OrgVisibilityCapabilities();
        $c->viewRestrictedUnits = $restricted;
        $c->viewHiddenUnits = $hidden;
        $c->viewRestrictedPersonnel = $restricted;
        $c->viewHiddenPersonnel = $hidden;
        $c->bypassAll = $all;

        return $c;
    }

    private function svc(OrgVisibilityCapabilities $caps): SseDocumentDataService
    {
        $units = [
            ['id' => 1, 'parent_id' => 0, 'name' => 'Compagnie Alpha', 'visibility_level' => 'normal'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Section Bravo', 'visibility_level' => 'normal'],
            ['id' => 3, 'parent_id' => 1, 'name' => 'Cellule Delta', 'visibility_level' => 'restricted'],
            ['id' => 4, 'parent_id' => 0, 'name' => 'Cellule Noire', 'visibility_level' => 'hidden'],
        ];
        $members = [
            1 => [['user_id' => 10, 'role_name' => 'Commandant', 'callsign' => 'ALPHA-6', 'display_name' => 'Capitaine Test']],
            2 => [['user_id' => 11, 'role_name' => 'Tireur', 'callsign' => 'BRAVO-2', 'display_name' => 'Soldat Fictif'],
                ['user_id' => 12, 'role_name' => 'Radio', 'callsign' => 'BRAVO-3', 'display_name' => 'Nom Secret']],
            3 => [['user_id' => 13, 'role_name' => 'Analyste', 'callsign' => 'DELTA-1', 'display_name' => 'Agent Delta']],
            4 => [['user_id' => 14, 'role_name' => 'Chef', 'callsign' => 'NOIR-1', 'display_name' => 'Agent Noir']],
        ];
        $vis = [12 => ['visibility_level' => 'hidden', 'assignment_visibility' => 'normal', 'anonymized_label' => ''],
            11 => ['visibility_level' => 'normal', 'assignment_visibility' => 'normal', 'anonymized_label' => '']];

        return new SseDocumentDataService(
            $caps,
            $units,
            $members,
            $vis,
            static fn (int $uid): string => $uid === 11 ? 'Sergent (Sgt)' : 'Capitaine (Cpt)',
            static fn (int $uid): array => $uid === 11 ? [['qualification_name' => 'Tireur d’élite'], ['qualification_name' => 'Qualification restreinte', 'visibility_redacted' => true]] : [],
            static fn (): string => '23/11/2026 à 10:00'
        );
    }

    public function testUnitOutsideThePerimeterIsRefusedAndNeverListed(): void
    {
        $s = $this->svc($this->caps());
        $ids = array_column($s->selectableUnits(), 'id');
        self::assertSame([1, 2], $ids);
        foreach ([3, 4, 99] as $bad) {
            self::assertFalse($s->orbatExtract($bad)['ok'], 'orbat ' . $bad);
            self::assertFalse($s->unitRosterTable($bad)['ok'], 'effectif ' . $bad);
        }
        self::assertFalse($s->memberCard(13)['ok'], 'membre d’une unité restreinte');
        self::assertFalse($s->memberCard(14)['ok'], 'membre d’une unité masquée');
        self::assertFalse($s->memberCard(99)['ok'], 'membre inconnu (autre communauté)');
    }

    public function testRestrictedAndHiddenUnitsOpenOnlyWithTheMatchingRights(): void
    {
        self::assertSame([1, 2, 3], array_column($this->svc($this->caps(true))->selectableUnits(), 'id'));
        self::assertTrue($this->svc($this->caps(true, true))->orbatExtract(4)['ok']);
        self::assertTrue($this->svc($this->caps(false, false, true))->orbatExtract(4)['ok']);
    }

    public function testHiddenPersonnelIsNeverNamed(): void
    {
        $s = $this->svc($this->caps());
        $html = $s->unitRosterTable(2)['html'];
        self::assertStringContainsString('Soldat Fictif', $html);
        self::assertStringNotContainsString('Nom Secret', $html);
        self::assertStringNotContainsString('BRAVO-3', $html);
        self::assertFalse($s->memberCard(12)['ok']);
        self::assertStringNotContainsString('Nom Secret', $s->orbatExtract(1)['html']);
    }

    public function testMemberCardHasOnlyTheAllowedFields(): void
    {
        $r = $this->svc($this->caps())->memberCard(11);
        self::assertTrue($r['ok']);
        foreach (['Soldat Fictif', 'Sergent (Sgt)', 'BRAVO-2', 'Section Bravo', 'Tireur', 'Tireur d’élite'] as $needle) {
            self::assertStringContainsString($needle, $r['html'], $needle);
        }
        self::assertStringNotContainsString('Qualification restreinte', $r['html'], 'qualification masquée au lecteur');
        self::assertDoesNotMatchRegularExpression('/naissance|adresse|téléphone|e-?mail|état civil|identité légale/i', $r['html']);
    }

    public function testBlockIsStaticTimestampedSourcedAndLocked(): void
    {
        $r = $this->svc($this->caps())->orbatExtract(1);
        self::assertStringContainsString('Extrait au 23/11/2026 à 10:00', $r['html']);
        self::assertStringContainsString('Source : ORBAT de la communauté', $r['html']);
        self::assertStringContainsString('data-lock="1"', $r['html']);
        self::assertStringContainsString('data-src="unit:1"', $r['html']);
        $clean = SseRichText::sanitize($r['html']);
        self::assertStringContainsString('data-src="unit:1"', $clean, 'la liste blanche garde la source');
        self::assertStringContainsString('data-lock="1"', $clean);
        // Un enregistrement qui modifie le bloc verrouillé est refusé.
        $edited = str_replace('Capitaine Test', 'Autre Nom', $clean);
        self::assertNotSame([], SseDocumentLocks::violations($clean, $edited));
        self::assertSame([], SseDocumentLocks::violations($clean, $clean . '<p>Suite libre</p>'));
    }

    public function testMinimumClassificationIsRaisedByTheDataLevels(): void
    {
        $normal = $this->svc($this->caps())->orbatExtract(2);
        self::assertSame('interne', $normal['classification']);
        $withRestricted = $this->svc($this->caps(true))->orbatExtract(1);
        self::assertSame('encadrement', $withRestricted['classification'], 'une sous-unité restreinte relève le bloc');
        self::assertSame('tres_restreint', $this->svc($this->caps(true, true))->orbatExtract(4)['classification']);
        self::assertSame('tres_restreint', $this->svc($this->caps(true, true))->memberCard(14)['classification']);
    }

    public function testServerRecomputesTheMinimumFromDataSrcNotFromTheClient(): void
    {
        $s = $this->svc($this->caps());
        $forged = '<div class="sse-data" data-src="unit:4" data-lock="1" data-class="interne"><p>x</p></div>';
        self::assertSame('tres_restreint', $s->minimumFor(SseRichText::sanitize($forged)));
        self::assertSame('interne', $s->minimumFor('<div class="sse-data" data-src="unit:1" data-lock="1"><p>x</p></div>'), 'ce que ce lecteur voit sous l’unité');
        self::assertSame('encadrement', $this->svc($this->caps(true))->minimumFor('<div class="sse-data" data-src="unit:1" data-lock="1"><p>x</p></div>'), 'sous-unité restreinte');
        self::assertSame('tres_restreint', $s->minimumFor('<div class="sse-data" data-src="member:14" data-lock="1"><p>x</p></div>'));
        self::assertSame('interne', $s->minimumFor('<p>Aucune donnée insérée</p>'));
        self::assertSame('confidentiel', SseDocumentDataService::maxClassification('interne', 'confidentiel'));
        self::assertSame('confidentiel', SseDocumentDataService::maxClassification('confidentiel', 'encadrement'));
    }

    public function testSanitizerKeepsOnlyAValidDataSource(): void
    {
        $out = SseRichText::sanitize('<div class="sse-data" data-src="javascript:1"><p>a</p></div><div class="sse-data" data-src="unit:7" onclick="x()"><p>b</p></div><div data-src="unit:8"><p>c</p></div>');
        self::assertSame(1, substr_count($out, 'data-src='));
        self::assertStringContainsString('data-src="unit:7"', $out);
        self::assertStringNotContainsString('onclick', $out);
    }

    public function testEverySourceIsScopedToTheWritersCommunity(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Sse/SseDocumentDataService.php');
        foreach (['allForTenant($tenantId)', 'listActiveMembersByUnitForTenant($tenantId)', 'personnelVisibilityByUserIdsForTenant($tenantId', 'listForUser($uid, $tenantId)', 'findById($uid, $tenantId)'] as $call) {
            self::assertStringContainsString($call, $src, $call);
        }
        $c = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/SsePortalController.php');
        self::assertStringContainsString('SseDocumentDataService::forSession($this->tenantId())', $c);
        $r = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        foreach (['documentDataIndex', 'documentDataInsert', 'documentMediaLogo'] as $m) {
            self::assertMatchesRegularExpression('~SsePortalController::class, \'' . $m . '\'\], \$mwSsePortal\)~', $r, $m);
        }
    }

    public function testSaveRaisesTheClassificationOnTheServer(): void
    {
        $c = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/SsePortalController.php');
        self::assertSame(2, substr_count($c, 'raisedClassification($body'), 'création et modification');
        self::assertStringContainsString('SseDocumentDataService::maxClassification($requested, $min)', $c);
    }
}
