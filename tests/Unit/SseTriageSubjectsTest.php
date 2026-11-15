<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseTriageSubjects;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseTriageSqlite;

/**
 * Sujet visible de chaque ligne de « À trier » : dossier, personnes rattachées, identité ; et son masquage : jamais de nom sans autorisation
 * (dossier au-dessus de l'habilitation, identité d'un membre), échec fermé.
 */
final class SseTriageSubjectsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = SseTriageSqlite::open(true);
        SseTriageSqlite::addCase($this->pdo, 5, 'DOS-0005', 'Réseau du port');
        SseTriageSqlite::addPerson($this->pdo, 5, 1, 'Jean', 'Martin', 'civil');
        SseTriageSqlite::addPerson($this->pdo, 5, 2, 'Anna', 'Kovac', 'cover');
        SseTriageSqlite::addPerson($this->pdo, 5, 3, 'Marc', 'Vidal', 'civil', 42);
        SseTriageSqlite::addCase($this->pdo, 6, 'DOS-0006', 'Convoi nord', 1, 'tres_restreint');
        SseTriageSqlite::addPerson($this->pdo, 6, 4, 'Luc', 'Perrin');
        SseTriageSqlite::addCase($this->pdo, 7, 'DOS-0007', 'Autre communauté', 2);
    }

    protected function tearDown(): void
    {
        SseTriageSqlite::close();
    }

    /** @param (\Closure(int, int): bool)|null $member */
    private function subjects(?\Closure $member = null, ?\Closure $above = null, ?\Closure $wanted = null): SseTriageSubjects
    {
        return new SseTriageSubjects(null, $member ?? fn (int $t, int $u): bool => true, $above ?? fn (array $c): bool => false, $wanted ?? fn (int $t, array $ids): array => []);
    }

    /** @return array<string, mixed> */
    private function subjectOf(SseTriageSubjects $s, string $type, int $id, int $tenant = 1, array $extra = []): array
    {
        return $s->enrich($tenant, [['key' => 'x:1', 'subject_type' => $type, 'subject_id' => $id] + $extra])[0]['subject'] ?? [];
    }

    public function testACaseSubjectNamesTheDossierAndItsPeople(): void
    {
        $sub = $this->subjectOf($this->subjects(), 'case', 5);

        self::assertSame('DOS-0005 · Réseau du port', $sub['label']);
        self::assertSame(['Jean Martin', 'Anna Kovac', 'Marc Vidal'], $sub['people']);
        self::assertFalse($sub['masked']);
        self::assertSame('case:5', $sub['key']);
        self::assertSame('dossier', $sub['noun']);
        self::assertStringEndsWith('atak/sse/dossiers/5', $sub['href']);
        self::assertTrue($sub['cover'], 'une identité de couverture est rattachée');
    }

    public function testAMemberIdentityIsNotNamedWithoutAuthorisation(): void
    {
        $denied = $this->subjectOf($this->subjects(fn (int $t, int $u): bool => false), 'case', 5);

        self::assertSame(['Jean Martin', 'Anna Kovac', 'Identité de membre (accès restreint)'], $denied['people']);
        self::assertStringNotContainsString('Vidal', json_encode($denied, JSON_UNESCAPED_UNICODE));
        $allowed = $this->subjectOf($this->subjects(fn (int $t, int $u): bool => $u === 42), 'case', 5);
        self::assertContains('Marc Vidal', $allowed['people']);
    }

    public function testAMemberRightsCheckThatFailsHidesTheName(): void
    {
        $boom = $this->subjectOf($this->subjects(function (): bool {
            throw new \RuntimeException('base illisible');
        }), 'case', 5);

        self::assertStringNotContainsString('Vidal', json_encode($boom, JSON_UNESCAPED_UNICODE), 'échec fermé');
    }

    public function testACaseAboveTheClearanceIsNeitherTitledNorPeopled(): void
    {
        $sub = $this->subjectOf($this->subjects(null, fn (array $c): bool => $c['classification'] === 'tres_restreint'), 'case', 6);

        self::assertSame(SseTriageSubjects::MASKED_CASE, $sub['label']);
        self::assertTrue($sub['masked']);
        self::assertSame('', $sub['href'], 'pas de lien vers un dossier qu’on ne peut pas lire');
        self::assertSame([], $sub['people']);
        self::assertStringNotContainsString('Perrin', json_encode($sub, JSON_UNESCAPED_UNICODE));
        self::assertSame('DOS-0006 · Convoi nord', $this->subjectOf($this->subjects(), 'case', 6)['label'], 'avec l’habilitation, le titre est lisible');
    }

    public function testAClearanceCheckThatFailsMasksTheCase(): void
    {
        $sub = $this->subjectOf($this->subjects(null, function (): bool {
            throw new \RuntimeException('habilitation illisible');
        }), 'case', 5);

        self::assertTrue($sub['masked'] || $sub['label'] === SseTriageSubjects::MASKED_CASE);
        self::assertSame(SseTriageSubjects::MASKED_CASE, $sub['label']);
    }

    public function testAnIdentitySubjectOfAMemberIsMaskedAndACivilOneIsNamed(): void
    {
        $member = $this->subjectOf($this->subjects(fn (int $t, int $u): bool => false), 'identity', 1003, 1, ['subject_name' => 'Marc Vidal']);
        self::assertSame(SseTriageSubjects::MASKED_MEMBER, $member['label']);
        self::assertTrue($member['masked']);
        self::assertSame('', $member['href']);
        self::assertSame('tres_restreint', $member['classification']);

        $civil = $this->subjectOf($this->subjects(fn (): bool => false), 'identity', 1001);
        self::assertSame('Jean Martin', $civil['label'], 'une identité qui n’est pas celle d’un membre est nommée');
        self::assertSame('identité', $civil['noun']);
        self::assertTrue($this->subjectOf($this->subjects(), 'identity', 1002)['cover']);
    }

    public function testAWantedNoticeOnAnIdentityOfTheCaseIsFlagged(): void
    {
        $wanted = $this->subjectOf($this->subjects(null, null, fn (int $t, array $ids): array => in_array(1001, $ids, true) ? [1001 => true] : []), 'case', 5);
        self::assertTrue($wanted['wanted']);
        self::assertFalse($this->subjectOf($this->subjects(), 'case', 5)['wanted']);
    }

    public function testAnotherCommunitysCaseIsNeverResolved(): void
    {
        $sub = $this->subjectOf($this->subjects(), 'case', 7, 1);

        self::assertSame('Dossier #7', $sub['label'], 'introuvable dans cette communauté : aucun titre ne fuit');
        self::assertStringNotContainsString('Autre communauté', json_encode($sub, JSON_UNESCAPED_UNICODE));
    }

    public function testItemsWithoutASubjectAreLeftAlone(): void
    {
        $items = [['key' => 'note:1', 'title' => 'Fiche'], ['key' => 'x:2', 'subject_type' => '', 'subject_id' => 0]];

        self::assertSame($items, $this->subjects()->enrich(1, $items));
    }

    public function testFortyItemsOfTheSameCaseAreResolvedTogether(): void
    {
        $items = [];
        for ($i = 0; $i < 40; $i++) {
            $items[] = ['key' => 'signal:' . $i, 'subject_type' => 'case', 'subject_id' => 5];
        }
        $out = $this->subjects()->enrich(1, $items);

        self::assertCount(40, $out);
        self::assertSame('DOS-0005 · Réseau du port', $out[39]['subject']['label']);
        self::assertSame($out[0]['subject'], $out[39]['subject']);
    }
}
