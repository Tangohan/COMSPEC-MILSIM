<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Groupes et équipes partagent la même liste en cartes, la même fiche et le même formulaire :
 * les libellés et adresses suivent le type d’unité, sans casser l’écran des groupes.
 */
final class GroupTeamViewsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private bool $sessionWasStarted = false;

    protected function setUp(): void
    {
        // Le jeton anti-rejeu des formulaires ne doit pas ouvrir de vraie session en test.
        $started = new \ReflectionProperty(\App\Core\Session::class, 'started');
        $this->sessionWasStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $_SESSION['_csrf_token'] ??= 'test';
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(\App\Core\Session::class, 'started'))->setValue(null, $this->sessionWasStarted);
    }

    /** @param array<string, mixed> $vars */
    private function render(string $view, array $vars): string
    {
        return (static function (string $__file, array $__vars): string {
            extract($__vars);
            ob_start();
            require $__file;

            return (string) ob_get_clean();
        })(self::ROOT . '/views/admin/organization/' . $view . '.php', $vars);
    }

    /** @return list<array<string, mixed>> */
    private function units(): array
    {
        return [
            ['id' => 5, 'name' => 'Alpha <x>', 'code' => 'ALP', 'parent_id' => 1, 'commander_user_id' => 9, 'public_blurb' => 'Reco', 'show_on_public_page' => 1, 'public_capacity' => 4],
            ['id' => 6, 'name' => 'Bravo', 'code' => null, 'parent_id' => null, 'commander_user_id' => null, 'public_blurb' => null, 'show_on_public_page' => 0],
        ];
    }

    public function testTeamListUsesTeamWordingAndAddresses(): void
    {
        $html = $this->render('teams/index', [
            'teams' => $this->units(),
            'memberCounts' => [5 => 4],
            'commanders' => [9 => ['id' => 9, 'callsign' => 'Viper', 'display_name' => 'Jean']],
            'parentNames' => [1 => 'Compagnie'],
        ]);

        self::assertStringContainsString('Créer une équipe', $html);
        self::assertStringContainsString('back-office/teams/5', $html);
        self::assertStringNotContainsString('back-office/groups/5', $html);
        self::assertStringContainsString('Alpha &lt;x&gt;', $html);
        self::assertStringContainsString('rattaché à Compagnie', $html);
        self::assertStringContainsString('Viper', $html);
        self::assertStringContainsString('bo-grp-fill__bar is-full', $html);
        self::assertStringContainsString('Sans responsable (1)', $html);
        self::assertStringContainsString('Aucune équipe ne correspond.', $html);
    }

    public function testGroupListKeepsGroupWording(): void
    {
        $html = $this->render('groups/index', ['groups' => $this->units(), 'memberCounts' => [], 'commanders' => []]);

        self::assertStringContainsString('Créer un groupe', $html);
        self::assertStringContainsString('back-office/groups/6/edit', $html);
        self::assertStringContainsString('Aucun groupe ne correspond.', $html);

        $empty = $this->render('groups/index', ['groups' => []]);
        self::assertStringContainsString('Aucun groupe pour l’instant', $empty);
    }

    public function testTeamSheetAndFormRenderWithoutTypeChoice(): void
    {
        $team = $this->units()[0] + ['slug' => 'alpha', 'type' => 'team'];
        $sheet = $this->render('teams/show', ['team' => $team, 'members' => [], 'commander' => null, 'parent' => ['name' => 'Compagnie']]);
        self::assertStringContainsString('Toutes les équipes', $sheet);
        self::assertStringContainsString('back-office/teams/5/delete', $sheet);
        self::assertStringContainsString('Rattaché à Compagnie', $sheet);

        $form = $this->render('teams/edit', ['team' => $team, 'parents' => [], 'users' => [['id' => 9, 'callsign' => 'Viper', 'display_name' => 'Jean']]]);
        self::assertStringContainsString('back-office/teams/5/update', $form);
        self::assertStringContainsString('Chef d’équipe', $form);
        self::assertStringNotContainsString('name="struct_type"', $form);

        $groupForm = $this->render('groups/edit', ['group' => ['id' => 6, 'name' => 'Bravo', 'type' => 'group'], 'parents' => [], 'users' => [], 'unitTypes' => ['group' => ['label' => 'Groupe']]]);
        self::assertStringContainsString('name="struct_type"', $groupForm);
        self::assertStringContainsString('Chef de groupe', $groupForm);
    }
}
