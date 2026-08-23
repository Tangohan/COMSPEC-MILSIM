<?php

declare(strict_types=1);

namespace Tests\Contract\Api;

use PHPUnit\Framework\TestCase;

/**
 * Le rédacteur ATAK appelle ces adresses depuis une DLL native : leur
 * disparition ne se verrait qu'en jeu, une fois le mod déjà distribué.
 */
final class SseFieldNoteApiContractTest extends TestCase
{
    private string $routes;

    protected function setUp(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../../routes/web.php');
        self::assertIsString($routes);
        $this->routes = $routes;
    }

    public function testFieldNoteEndpointsAreRegistered(): void
    {
        foreach ([
            "'/api/sse/notes/catalogue'",
            "'/api/sse/notes'",
            "'/api/sse/notes/{id}'",
            "'/api/sse/notes/{id}/pieces'",
        ] as $path) {
            self::assertStringContainsString($path, $this->routes, $path);
        }
    }

    /**
     * « catalogue » est un segment fixe : déclaré après /{id}, le routeur le
     * lirait comme un identifiant et renverrait « fiche introuvable ».
     */
    public function testCatalogRouteIsDeclaredBeforeTheIdentifierRoute(): void
    {
        $catalogue = strpos($this->routes, "'/api/sse/notes/catalogue'");
        $show = strpos($this->routes, "'/api/sse/notes/{id}'");

        self::assertIsInt($catalogue);
        self::assertIsInt($show);
        self::assertLessThan($show, $catalogue);
    }

    public function testPortalRoutesExposeComposerAndTriage(): void
    {
        foreach ([
            "'/atak/sse/fiches'",
            "'/atak/sse/fiches/nouvelle'",
            "'/atak/sse/fiches/{id}'",
            "'/atak/sse/fiches/{id}/pieces'",
            "'/atak/sse/fiches/{id}/suivi'",
            "'/atak/sse/fiches/{id}/rattachement'",
        ] as $path) {
            self::assertStringContainsString($path, $this->routes, $path);
        }
    }

    /**
     * Même piège que pour l'API : « nouvelle » doit précéder /{id}.
     */
    public function testComposerRouteIsDeclaredBeforeTheIdentifierRoute(): void
    {
        $composer = strpos($this->routes, "'/atak/sse/fiches/nouvelle'");
        $show = strpos($this->routes, "'/atak/sse/fiches/{id}'");

        self::assertIsInt($composer);
        self::assertIsInt($show);
        self::assertLessThan($show, $composer);
    }

    /**
     * Le pont natif nomme ses commandes en dur : elles doivent exister dans la
     * DLL et être déclarées dans les fonctions du mod.
     */
    public function testGameBridgeDeclaresBothFieldNoteCommands(): void
    {
        $extension = dirname(__DIR__, 3) . '/mod/UptoDate/COMSPECExtension/Extension.cs';
        if (!is_file($extension)) {
            self::markTestSkipped('Sources du pont natif absentes de cette copie de travail.');
        }
        $contents = (string) file_get_contents($extension);

        self::assertStringContainsString('"SubmitSseFieldNote"', $contents);
        self::assertStringContainsString('"UploadSseNoteAttachment"', $contents);
        self::assertStringContainsString('"/api/sse/notes"', $contents);
        self::assertStringContainsString('/api/sse/notes/"', $contents);
    }

    public function testConnectAddonDeclaresComposerFunctions(): void
    {
        $config = dirname(__DIR__, 3)
            . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp';
        if (!is_file($config)) {
            self::markTestSkipped('Sources du mod absentes de cette copie de travail.');
        }
        $contents = (string) file_get_contents($config);

        foreach ([
            'intelNoteShow',
            'intelNoteOnLoad',
            'intelNotePane',
            'intelNoteRefresh',
            'intelNoteToggleTheme',
            'intelNoteAddPiece',
            'intelNoteDropPiece',
            'intelNoteSubmit',
            'intelNoteClose',
        ] as $function) {
            self::assertStringContainsString('class ' . $function . ' {};', $contents, $function);
            self::assertFileExists(
                dirname(__DIR__, 3)
                    . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_'
                    . $function . '.sqf'
            );
        }

        self::assertStringContainsString('#include "display_intel_note.hpp"', $contents);
    }

    public function testAtakDrawerExposesDedicatedNoteMenu(): void
    {
        $config = dirname(__DIR__, 3)
            . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp';
        if (!is_file($config)) {
            self::markTestSkipped('Sources du mod absentes de cette copie de travail.');
        }
        $contents = (string) file_get_contents($config);

        // BCE lit ATAK_APPs, la coque RscTitles en miroir : une seule des deux
        // déclarations et le menu n'apparaît pas dans le tiroir.
        self::assertSame(
            2,
            substr_count($contents, 'PAGE_CTRL = "COMSPEC_ATAK_Note"'),
            'le menu RENS doit être déclaré dans ATAK_APPs et dans RscTitles'
        );
        self::assertSame(
            2,
            substr_count($contents, 'comspec_overwatch_atak_athena_fnc_athena_noteOnOpened'),
            'les deux déclarations doivent pointer la même fonction d’ouverture'
        );
        self::assertStringContainsString('#include "ui\\note_page.hpp"', $contents);
    }
}
