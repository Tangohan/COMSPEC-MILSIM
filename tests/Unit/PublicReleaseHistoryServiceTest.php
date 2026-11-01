<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Platform\PublicReleaseHistoryService;
use PHPUnit\Framework\TestCase;

/** Historique public des versions : fusion journal ATAK + changelogs Steam + bulletins UPDATE + version du portail. */
final class PublicReleaseHistoryServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/comspec-releases-' . bin2hex(random_bytes(4));
        @mkdir($this->root . '/docs/dev', 0775, true);
        @mkdir($this->root . '/storage', 0775, true);
        file_put_contents($this->root . '/CHANGELOG-ATAK.md', <<<'MD'
# CHANGELOG - Features ATAK

## Règle : chaque version a un « Résumé joueur »

Texte d’introduction.

## Pack 2.9.19 : file d’envoi élargie (ATAK natif 2.9.19.AG - Denver, COMSPEC Link 2.0.110)

### Résumé joueur
Type : performance
FR : Liaison plus fluide sous charge
- La logistique n’ouvre plus d’envois parallèles.
- Relancez Arma complètement.
EN : Smoother link under load
- Logistics no longer open parallel sends.

### Détail technique
- OPT-11 : logistics → BACKGROUND ; DLL `extAwait`

## Montre : carte et lecteur (ATAK natif 2.9.0.AB - Denver, COMSPEC Link 2.0.99)

### Résumé joueur
Type : nouveauté
FR : La montre affiche la carte
- Mon point est au centre de la carte.

## Photo : flou des visages (partie du regroupement 2.9.0)

### Résumé joueur
Type : correctif
FR : Le flou des visages aussi dans le téléphone

## Overwatch 1.6.3 — 2026-09-20

### Correction — Découpage d’étage sur la silhouette

Quand un bâtiment est désigné, une croix marque le plafond de l’étage. Relancez Arma complètement (Overwatch 1.6.3).

## Overwatch 1.6.2 / SSE 0.7.22 — 2026-09-19

### Correction — Profil d’identité SSE

Non testé en jeu. Demande de TTRD : à revoir. - Le profil suit la fiche.

## [1.5.30] / Overwatch 1.4.63 / extension 2.0.12 — 2026-08-24

### Ajouté — Tables `atak_poi` et endpoint API

- **Repository** : `AtakPoiRepository`

## Versionning
MD);
        file_put_contents($this->root . '/docs/dev/STEAM-CHANGELOG-2026-10-28-atak-2.9.19.md', <<<'BB'
[h1]COMSPEC ATAK — Mise à jour 2.9.19.AG - Denver[/h1]
[b]Publication : 28/10/2026[/b]

[h2]Amélioration — Liaison encore plus stable[/h2]
Les ordres du poste ne figent plus l’image pendant l’envoi.
[list]
[*] logistique : envoi ordonné ;
[/list]
BB);
        file_put_contents($this->root . '/docs/dev/STEAM-CHANGELOG-2026-10-27-soar-fn-atak-2.9.5.md', '[h1]Pack FN[/h1]');
        file_put_contents($this->root . '/storage/app_version.json', json_encode([
            'version' => '1.7.0', 'codename' => 'Denver', 'updated_at' => '2026-10-18T15:00:00+02:00',
        ]));
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->root);
    }

    public function testMergesSourcesIntoPlayerFacingReleases(): void
    {
        $history = $this->service()->history(true);
        $byKey = [];
        foreach ($history['releases'] as $r) {
            $byKey[$r['tags'][0]['product'] . ' ' . $r['tags'][0]['version']] = $r;
        }

        // Pack 2.9.19 : titre du résumé joueur, date du changelog Steam, numéro de bulletin, Link en mention.
        $atak = $byKey['atak 2.9.19'];
        self::assertSame('2026-10-28', $atak['date']);
        self::assertSame('day', $atak['date_precision']);
        self::assertSame('Liaison plus fluide sous charge', $atak['title']);
        self::assertSame('Smoother link under load', $atak['title_en']);
        self::assertSame('performance', $atak['type']);
        self::assertSame('Denver', $atak['codename']);
        self::assertSame(1203, $atak['bulletin']);
        self::assertSame(['atak', 'link'], $atak['filters']);
        self::assertSame('2.0.110', $atak['tags'][1]['version']);
        self::assertSame(['La logistique n’ouvre plus d’envois parallèles'], $atak['points']);
        self::assertStringContainsString('ne figent plus l’image', $atak['summary']);

        // Version sans date dans le journal : rangée au mois de l’entrée datée qui la suit, jamais au jour.
        $watch = $byKey['atak 2.9.0'];
        self::assertSame('month', $watch['date_precision']);
        self::assertSame('2026-09-20', $watch['date']);
        // La sous-entrée « partie du regroupement 2.9.0 » devient une ligne de cette version.
        self::assertContains('Le flou des visages aussi dans le téléphone', $watch['points']);

        // Ancienne entrée : titre tiré du sous-titre, consigne d’installation retirée du résumé.
        $ow = $byKey['overwatch 1.6.3'];
        self::assertSame('Découpage d’étage sur la silhouette', $ow['title']);
        self::assertSame('fix', $ow['type']);
        self::assertStringNotContainsString('Relancez', $ow['summary']);

        // Notes d’atelier et noms de testeurs jamais affichés.
        $ow2 = $byKey['overwatch 1.6.2'];
        self::assertSame('', $ow2['summary']);
        self::assertSame(['overwatch', 'sse'], $ow2['filters']);

        // Numéro entre crochets = version du portail ; titre technique remplacé par un libellé neutre.
        $portalOld = $byKey['portal 1.5.30'];
        self::assertSame('Portail 1.5.30', $portalOld['title']);
        self::assertSame([], $portalOld['points']);

        // Version actuelle du portail lue dans app_version.json, avec le titre du bulletin.
        $portal = $byKey['portal 1.7.0'];
        self::assertSame('2026-10-18', $portal['date']);
        self::assertSame('Denver', $portal['codename']);
        self::assertSame('Portail Athena 1.7.0 « Denver » : chaque version porte un nom de ville', $portal['title']);

        // Packs de communauté (FN) ignorés : pas de doublon.
        self::assertArrayNotHasKey('atak 2.9.5', $byKey);

        // Plus récentes d’abord.
        $dates = array_column($history['releases'], 'date');
        $sorted = $dates;
        rsort($sorted);
        self::assertSame($sorted, $dates);

        // Versions en service = la plus récente de chaque produit.
        $current = array_column($history['current'], 'version', 'product');
        self::assertSame(['atak' => '2.9.19', 'link' => '2.0.110', 'overwatch' => '1.6.3', 'sse' => '0.7.22', 'portal' => '1.7.0'], $current);
        self::assertSame(2, $history['counts']['atak']);
        self::assertSame(2, $history['bulletins']);
        self::assertSame('2026-08-24', $history['first_date']);

        $json = (string) json_encode($history, JSON_UNESCAPED_UNICODE);
        foreach (['TTRD', 'extAwait', 'endpoint', 'Repository', 'Non testé', 'OPT-11'] as $jargon) {
            self::assertStringNotContainsString($jargon, $json);
        }
    }

    public function testCacheIsReusedUntilASourceChanges(): void
    {
        $calls = 0;
        $service = new PublicReleaseHistoryService($this->root, $this->root . '/cache', function () use (&$calls): array {
            $calls++;

            return [];
        });
        $first = $service->history();
        self::assertFileExists($this->root . '/cache/public_history_releases.json');
        $second = $service->history();
        self::assertSame($first, $second);

        // Une nouvelle entrée du journal invalide le cache.
        file_put_contents(
            $this->root . '/CHANGELOG-ATAK.md',
            "## Overwatch 1.6.4 — 2026-09-21\n\n### Nouveau — Reco dans le téléphone\n\nLa reco s’ouvre dans le téléphone.\n",
            FILE_APPEND
        );
        $third = $service->history();
        self::assertSame('1.6.4', array_column($third['current'], 'version', 'product')['overwatch']);
    }

    public function testEmptySourcesGiveEmptyHistory(): void
    {
        $empty = sys_get_temp_dir() . '/comspec-releases-empty-' . bin2hex(random_bytes(4));
        @mkdir($empty, 0775, true);
        $history = (new PublicReleaseHistoryService($empty, $empty, static fn (): array => []))->history(true);
        self::assertSame([], $history['releases']);
        self::assertSame([], $history['current']);
        self::assertNull($history['first_date']);
        @unlink($empty . '/public_history_releases.json');
        @rmdir($empty);
    }

    public function testTokensReadProductVersionsInOrder(): void
    {
        self::assertSame(
            [['product' => 'atak', 'version' => '2.9.19'], ['product' => 'link', 'version' => '2.0.110']],
            PublicReleaseHistoryService::tokens('Pack téléphone 2.9.19 et liaison 2.0.110 à republier', false)
        );
        self::assertSame(
            [['product' => 'overwatch', 'version' => '1.5.68'], ['product' => 'athena', 'version' => '1.0.115'], ['product' => 'link', 'version' => '2.0.34']],
            PublicReleaseHistoryService::tokens('Portail · Overwatch 1.5.68 · Athena 1.0.115 · Extension 2.0.34', false)
        );
        self::assertSame([['product' => 'portal', 'version' => '1.6.03']], PublicReleaseHistoryService::tokens('Portail 1.6.03', false));
        self::assertSame('1.6.3', PublicReleaseHistoryService::versionKey('1.6.03'));
        self::assertSame([], PublicReleaseHistoryService::tokens('Vague 2026.08c', false));
    }

    private function service(): PublicReleaseHistoryService
    {
        return new PublicReleaseHistoryService($this->root, $this->root . '/cache', static fn (): array => [
            [
                'kind' => 'update', 'number' => 1203, 'date' => '2026-10-28',
                'title' => 'Liaison : moins d’envois parallèles résiduels',
                'activity' => 'Avec le pack 2.9.19, la logistique rejoint la file d’envoi.',
                'size' => 'Pack téléphone 2.9.19 et liaison 2.0.110 à republier',
                'sections' => [['title' => 'CHANGELOG', 'items' => [['verb' => 'Added', 'text' => 'Logistique dans la file d’envoi']]]],
            ],
            [
                'kind' => 'update', 'number' => 758, 'date' => '2026-10-18',
                'title' => 'Portail Athena 1.7.0 « Denver » : chaque version porte un nom de ville',
                'activity' => 'Le portail passe en version 1.7.0 et s’appelle Denver.',
                'size' => 'Portail Athena',
                'sections' => [],
            ],
        ]);
    }
}
