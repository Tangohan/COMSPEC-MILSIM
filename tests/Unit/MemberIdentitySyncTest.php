<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\MemberIdentitySyncService;
use App\Services\Sse\SseIdentityPackService;
use App\Services\Sse\SseIdGameCoverService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Identité Athena ↔ jeu : cause du décalage (aucun kit → identité tirée par le mod), kit automatique dans le paquet,
 * détection Prêt / Incomplet / Erroné / Bloqué, différence avec le jeu champ par champ sans écrasement, historique
 * restaurable, droits (vérité de couverture) et cloison des communautés, journal d'audit.
 */
final class MemberIdentitySyncTest extends TestCase
{
    private PDO $pdo;

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Joueur'), (11, 1, NULL, 'Sans Steam'), (12, 1, '76561198000000012', 'Kit composé'), (20, 2, '76561198000000020', 'Autre')");
        $this->audit = [];
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
    }

    private function papers(): MemberIdentityPapersService
    {
        return new MemberIdentityPapersService(null, null, null, null, null, function (array $e): void {
            $this->audit[] = $e;
        });
    }

    private function sync(): MemberIdentitySyncService
    {
        return new MemberIdentitySyncService(null, null, null, null, null, function (array $e): void {
            $this->audit[] = $e;
        });
    }

    /** @return array<string, mixed> */
    private function pack(int $tenant, ?string $op = null): array
    {
        $watch = $this->createMock(SseWatchlistRepository::class);
        $watch->method('listActive')->willReturn([]);
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('listForContext')->willReturn([]);

        return (new SseIdentityPackService(new SseIdDocumentRepository(), new SseIdentityRepository(), new SseIdCountryRepository(), $watch, $persons, new SseIdKitRepository()))
            ->build($tenant, 1, $op);
    }

    /** @return array{0: int, 1: int} identité principale « Jake GYLENHALL », carte d'identité émise */
    private function jake(int $user = 10, bool $issue = true): array
    {
        $s = $this->papers();
        $id = $s->savePrimary(1, $user, ['last_name' => 'Gylenhall', 'first_name' => 'Jake', 'sex' => 'M', 'birth_date' => '1988-07-16', 'birth_place' => 'Colombus', 'nationality' => 'ALT'], 1)['id'];
        $doc = $s->saveDocument(1, $user, $s->ownedIdentity(1, $user, $id, true), null, ['doc_type' => 'id_card', 'country_code' => 'ALT', 'issued_on' => '2025-01-10', 'expires_on' => '2035-01-10'], true, 1)['id'];
        if ($issue) {
            self::assertTrue($s->action(1, $user, $s->ownedDocument(1, $user, $doc, true), 'issue', '', 1)['ok']);
        }

        return [$id, $doc];
    }

    /** @param array<string, mixed> $cover */
    private function report(int $user, array $cover): int
    {
        $res = (new SseIdGameCoverService())->store(1, $user, ['map' => '1', 'op' => '', 'cover' => $cover]);
        self::assertArrayHasKey('id', $res);

        return (int) $res['id'];
    }

    /** @return array<string, mixed> */
    private function diag(int $user, string $steam, bool $truth = true): array
    {
        $section = $this->papers()->section(1, $user, $steam, $truth);

        return MemberIdentitySyncService::diagnose($section, (new SseIdCountryRepository())->listForTenant(1, false), '2026-11-09');
    }

    /** @param list<array<string, mixed>> $issues @return list<string> */
    private static function codes(array $issues): array
    {
        return array_column($issues, 'code');
    }

    public function testRootCauseWithoutKitTheMemberNowGetsHisPrimaryIdentityPapersAutomatically(): void
    {
        [$id, $doc] = $this->jake();
        // Avant : aucun kit composé = rien dans le paquet = identité tirée par le mod en jeu. Désormais : kit « auto ».
        $kits = $this->pack(1)['kits'];
        self::assertSame([['uid' => '76561198000000010', 'user_id' => 10, 'scope' => 'auto', 'documents' => [$doc]]], $kits);
        self::assertContains($doc, array_column($this->pack(1)['documents'], 'id'), 'le papier du kit est dans le paquet');
        $identity = array_values(array_filter($this->pack(1)['identities'], static fn (array $i): bool => $i['id'] === $id))[0];
        self::assertSame(['Gylenhall', 'Jake', '1988-07-16', 'Colombus'], [$identity['last_name'], $identity['first_name'], $identity['birth_date'], $identity['birth_place']]);

        // Révoqué, volé : plus de kit automatique ; sans Steam : rien.
        $s = $this->papers();
        $s->action(1, 10, $s->ownedDocument(1, 10, $doc, true), 'stolen', '', 1);
        self::assertSame([], $this->pack(1)['kits']);
        $s->action(1, 10, $s->ownedDocument(1, 10, $doc, true), 'clear_report', '', 1);
        self::assertCount(1, $this->pack(1)['kits']);
        $this->jake(11);
        self::assertSame([10], array_column($this->pack(1)['kits'], 'user_id'), 'membre sans Steam : pas de kit');
    }

    public function testAComposedKitWinsAndTheAutomaticKitCarriesRealAndCover(): void
    {
        [, $doc] = $this->jake();
        [, $doc12] = $this->jake(12);
        $s = $this->papers();
        // Membre 12 : kit composé pour une opération seulement → plus de kit automatique, même hors de l'opération.
        $s->saveKit(1, 12, null, 'OP-X', [$doc12], true, 1);
        self::assertSame([10], array_column($this->pack(1)['kits'], 'user_id'));
        self::assertSame('operation', array_values(array_filter($this->pack(1, 'OP-X')['kits'], static fn (array $k): bool => $k['user_id'] === 12))[0]['scope']);

        // Infiltré : fiche de couverture → la couverture part à la place de l'identité principale.
        $cover = $s->createAlias(1, 10, ['kind' => 'cover', 'last_name' => 'Oscar', 'first_name' => 'De-Aligeara', 'nationality' => 'ALT'], true, 1)['id'];
        $coverDoc = $s->saveDocument(1, 10, $s->ownedIdentity(1, 10, $cover, true), null, ['doc_type' => 'passport', 'country_code' => 'ALT'], true, 1)['id'];
        $s->action(1, 10, $s->ownedDocument(1, 10, $coverDoc, true), 'issue', '', 1);
        self::assertEqualsCanonicalizing([$doc, $coverDoc], $this->pack(1)['kits'][0]['documents'], 'réglage par défaut : identité réelle et couverture');
        $this->pdo->exec("INSERT INTO sse_id_briefs (tenant_id, user_id, identity_id, title, created_at) VALUES (1, 10, {$cover}, 'Infiltration', '2026-11-01 10:00:00')");
        // Réglage par défaut « identité réelle avec la couverture » : les deux partent (MemberIdentityDualPhotoTest).
        self::assertEqualsCanonicalizing([$doc, $coverDoc], $this->pack(1)['kits'][0]['documents']);
        self::assertSame([], $this->pack(2)['kits'], 'autre communauté : rien');
    }

    public function testDetectionExplainsWhatBlocksTheTransmission(): void
    {
        // Membre sans Steam, identité incomplète, papier en brouillon : bloqué, avec la raison et le geste.
        $s = $this->papers();
        $id = $s->savePrimary(1, 11, ['last_name' => 'Gylenhall', 'first_name' => 'J4ke', 'sex' => 'X', 'nationality' => ''], 1)['id'];
        $doc = $s->saveDocument(1, 11, $s->ownedIdentity(1, 11, $id, true), null, ['doc_type' => 'id_card', 'country_code' => 'ALT'], true, 1)['id'];
        $d = $this->diag(11, '');
        self::assertSame('blocked', $d['state']);
        self::assertSame(['steam'], self::codes($d['account']));
        self::assertSame('Compte Athena non lié au joueur Steam', $d['account'][0]['title']);
        $i = $d['identities'][$id];
        self::assertSame('blocked', $i['state']);
        foreach (['first_name_format', 'dob_missing', 'pob_missing', 'nat_missing', 'sex_missing', 'photo_missing', 'no_issued'] as $code) {
            self::assertContains($code, self::codes($i['issues']), $code);
        }
        foreach ($i['issues'] as $issue) {
            self::assertNotSame('', $issue['why']);
            self::assertNotSame('', $issue['example']);
            self::assertArrayHasKey('label', $issue['fix']);
        }
        $dd = $d['documents'][$doc];
        self::assertSame('blocked', $dd['state']);
        self::assertSame(['not_issued', 'no_expiry'], self::codes($dd['issues']), 'brouillon sans date de fin de validité');
        self::assertSame(['label' => 'Émettre', 'do' => 'issue', 'doc' => $doc], $dd['issues'][0]['fix']);

        // Révoqué : « Papier révoqué : non transmis » ; papier expiré : erroné.
        $s->action(1, 11, $s->ownedDocument(1, 11, $doc, true), 'issue', '', 1);
        $s->action(1, 11, $s->ownedDocument(1, 11, $doc, true), 'revoke', 'grillée', 1);
        self::assertContains('Papier révoqué : non transmis', array_column($this->diag(11, '')['documents'][$doc]['issues'], 'title'));
        $id2 = $s->createAlias(1, 11, ['kind' => 'cover', 'last_name' => 'Oscar', 'first_name' => 'Luc', 'nationality' => ''], true, 1)['id'];
        self::assertContains('cover_no_country', self::codes($this->diag(11, '')['identities'][$id2]['issues']), 'Identité de couverture sans pays');
    }

    public function testACompleteIdentityWithItsIssuedPaperIsReadyAndSent(): void
    {
        [$id, $doc] = $this->jake();
        $d = $this->diag(10, '76561198000000010');
        self::assertTrue($d['auto_kit']);
        self::assertSame($id, $d['sent_identity_id']);
        self::assertSame('ready', $d['documents'][$doc]['state']);
        // Seule la photo manque : incomplet (part quand même).
        self::assertSame(['photo_missing'], self::codes($d['identities'][$id]['issues']));
        self::assertSame('incomplete', $d['state']);
        // Un kit composé sans ce papier : hors du kit, bloqué.
        $s = $this->papers();
        $other = $s->saveDocument(1, 10, $s->ownedIdentity(1, 10, $id, true), null, ['doc_type' => 'driving_license', 'country_code' => 'ALT'], true, 1)['id'];
        $s->action(1, 10, $s->ownedDocument(1, 10, $other, true), 'issue', '', 1);
        $s->saveKit(1, 10, null, null, [$other], true, 1);
        self::assertSame(['not_in_kit'], self::codes($this->diag(10, '76561198000000010')['documents'][$doc]['issues']));
        self::assertTrue(MemberIdentitySyncService::validName('De-Aligeara'));
        self::assertFalse(MemberIdentitySyncService::validName('<OSCAR>'));
    }

    public function testTheGameIdentityIsComparedFieldByFieldWithoutOverwriting(): void
    {
        [$id] = $this->jake();
        // Ce que le téléphone a remonté : l'identité tirée par le mod (cas constaté).
        $gc = $this->report(10, ['name' => 'PAPADOPOULOS Panagiotis', 'last' => 'PAPADOPOULOS', 'first' => 'Panagiotis', 'dob' => '1967-07-15', 'pob' => 'Telos', 'country' => 'ALT', 'sex' => 'M', 'origin' => 'generated']);
        $sync = $this->sync();
        $section = $this->papers()->section(1, 10, '76561198000000010', false);
        $reports = $sync->gameReports(1, 10, $section['identities'], false);
        self::assertCount(1, $reports, 'identité tirée par le mod : visible sans habilitation');
        self::assertSame('generated', $reports[0]['origin']);
        self::assertSame(['last_name', 'first_name', 'birth_date', 'birth_place'], array_column($reports[0]['diff'], 'field'));

        // Rien n'est écrit sans choix.
        self::assertFalse($sync->resolve(1, 10, $gc, [], false, false, 1)['ok']);
        self::assertSame('Gylenhall', (new SseIdentityRepository())->find($id, 1)['last_name']);

        // Choix par champ : le lieu vient du jeu, le reste reste Athena ; chaque valeur écartée est historisée.
        $res = $sync->resolve(1, 10, $gc, ['birth_place' => 'game', 'last_name' => 'athena', 'first_name' => 'athena', 'birth_date' => 'athena'], false, false, 7);
        self::assertTrue($res['ok'], $res['message']);
        $after = (new SseIdentityRepository())->find($id, 1);
        self::assertSame(['Gylenhall', 'Jake', '1988-07-16', 'Telos'], [$after['last_name'], $after['first_name'], $after['birth_date'], $after['birth_place']]);
        $history = $sync->historyFor(1, 10, [['id' => $id]]);
        self::assertCount(4, $history);
        $byField = array_column($history, null, 'field');
        self::assertSame(['Colombus', 'athena', 'game'], [$byField['birth_place']['lost_value'], $byField['birth_place']['lost_side'], $byField['birth_place']['decision']]);
        self::assertSame(['PAPADOPOULOS', 'game', 'athena'], [$byField['last_name']['lost_value'], $byField['last_name']['lost_side'], $byField['last_name']['decision']]);
        self::assertTrue($sync->gameReports(1, 10, $this->papers()->section(1, 10, '', false)['identities'], false)[0]['reviewed'], 'différence examinée');

        // Restaurer : Colombus revient, Telos part dans l'historique.
        self::assertTrue($sync->restore(1, 10, (int) $byField['birth_place']['id'], false, 7)['ok']);
        self::assertSame('Colombus', (new SseIdentityRepository())->find($id, 1)['birth_place']);
        self::assertFalse($sync->restore(1, 10, (int) $byField['birth_place']['id'], false, 7)['ok'], 'déjà restauré');
        self::assertSame('restore', $sync->historyFor(1, 10, [['id' => $id]])[0]['decision']);

        $actions = array_column($this->audit, 'action');
        self::assertContains('id_member.sync_resolve', $actions);
        self::assertContains('id_member.sync_restore', $actions);
    }

    public function testKeepBothNewContentReopensAndInvalidGameValuesAreRefused(): void
    {
        [$id] = $this->jake();
        $gc = $this->report(10, ['name' => 'GYLENHALL Jake', 'dob' => '1988-07-16', 'pob' => 'Telos', 'country' => 'ZZZ', 'origin' => 'athena']);
        $sync = $this->sync();
        self::assertSame(['birth_place', 'nationality'], array_column($sync->gameReports(1, 10, $this->papers()->section(1, 10, '', false)['identities'], false)[0]['diff'], 'field'), 'ancien format « NOM Prénom » découpé');
        $refused = $sync->resolve(1, 10, $gc, ['nationality' => 'game'], false, false, 1);
        self::assertFalse($refused['ok']);
        self::assertStringContainsString('pays inconnu', $refused['message']);
        self::assertSame('ALT', (new SseIdentityRepository())->find($id, 1)['nationality']);

        self::assertTrue($sync->resolve(1, 10, $gc, [], true, false, 1)['ok']);
        self::assertSame('Colombus', (new SseIdentityRepository())->find($id, 1)['birth_place'], 'garder les deux : Athena intacte');
        self::assertSame(['both', 'both'], array_column($sync->historyFor(1, 10, [['id' => $id]]), 'decision'));
        self::assertContains('id_member.sync_keep_both', array_column($this->audit, 'action'));
        // Le jeu envoie autre chose : la différence revient.
        $this->report(10, ['name' => 'GYLENHALL Jake', 'pob' => 'Kavala', 'origin' => 'athena']);
        self::assertFalse($sync->gameReports(1, 10, $this->papers()->section(1, 10, '', false)['identities'], false)[0]['reviewed']);
    }

    public function testCoverReportsNeedClearanceAndCommunitiesAreSealed(): void
    {
        $this->jake();
        $gc = $this->report(10, ['name' => 'MARKOS Petros', 'pob' => 'Kavala']);
        $sync = $this->sync();
        $identities = $this->papers()->section(1, 10, '', false)['identities'];
        self::assertSame([], $sync->gameReports(1, 10, $identities, false), 'couverture (origine absente) : Confidentiel');
        self::assertCount(1, $sync->gameReports(1, 10, $identities, true));
        self::assertFalse($sync->resolve(1, 10, $gc, ['birth_place' => 'game'], false, false, 1)['ok']);
        self::assertFalse($sync->resolve(2, 10, $gc, ['birth_place' => 'game'], false, true, 1)['ok'], 'autre communauté');
        self::assertFalse($sync->resolve(1, 12, $gc, ['birth_place' => 'game'], false, true, 1)['ok'], 'autre membre');
        self::assertFalse($sync->restore(2, 10, 1, true, 1)['ok']);
    }

    public function testTheMemberSheetBlockRendersStatesDifferencesAndHistory(): void
    {
        [$id] = $this->jake();
        $gc = $this->report(10, ['name' => 'PAPADOPOULOS Panagiotis', 'last' => 'PAPADOPOULOS', 'first' => 'Panagiotis', 'dob' => '1967-07-15', 'pob' => 'Telos', 'origin' => 'generated']);
        $this->sync()->resolve(1, 10, $gc, ['birth_place' => 'athena'], false, true, 1);
        $section = $this->papers()->section(1, 10, '76561198000000010', true);
        $section['sync'] = \App\Controllers\Admin\MemberIdentityPapersController::syncFor(1, 10, $section, true);
        $render = static function (array $pp, bool $manage): string {
            $ph = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            $truth = true;
            $base = '/back-office/ressources/effectifs/membres/10/papiers';
            $csrfToken = 'jeton';
            $countryLabel = static fn (?string $c): string => (string) $c;
            $docById = array_column($pp['documents'], null, 'id');
            ob_start();
            require dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/partials/member_papers_sync.php';

            return (string) ob_get_clean();
        };
        $html = $render($section, true);
        foreach (['id="papiers-synchro"', 'Transmission en jeu', 'Incomplet', 'Photo absente', 'Différence avec le jeu', 'PAPADOPOULOS Panagiotis',
            'Identité tirée par le mod', 'Reprendre la version du jeu', 'name="choice[last_name]"', 'Historique des valeurs écartées (1)', '/historique/', 'value="jeton"'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertStringNotContainsString('name="choice[birth_place]"', $html, 'champ déjà tranché : la différence reste ouverte pour les autres seulement');
        $readOnly = $render($section, false);
        self::assertStringNotContainsString('<form', $readOnly, 'lecture seule : aucun bouton');
        self::assertSame($id, $section['sync']['diag']['sent_identity_id']);
    }

    public function testSendToGameComposesTheDefaultKitWithTheIdentityPapers(): void
    {
        [$id, $doc] = $this->jake();
        $sync = $this->sync();
        self::assertFalse($sync->sendToGame(1, 10, 999, true, 1)['ok']);
        $res = $sync->sendToGame(1, 10, $id, true, 1);
        self::assertTrue($res['ok'], $res['message']);
        self::assertSame([['uid' => '76561198000000010', 'user_id' => 10, 'scope' => 'default', 'documents' => [$doc]]], $this->pack(1)['kits']);
        self::assertContains('id_member.sync_send_game', array_column($this->audit, 'action'));
        self::assertFalse($sync->sendToGame(2, 10, $id, true, 1)['ok'], 'autre communauté');
    }
}
