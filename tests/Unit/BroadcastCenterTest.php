<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BroadcastCenter as BC;
use PHPUnit\Framework\TestCase;

/** Règles du centre de diffusion : canaux par type, droits, cibles, dates, texte brut, correspondances de chaque canal. */
final class BroadcastCenterTest extends TestCase
{
    private const ALL = ['site', 'phone', 'tasks', 'game', 'discord'];

    /** @param array<string, mixed> $in */
    private static function check(array $in, array $allowed = self::ALL, ?int $now = null): array
    {
        return BC::validate($in + ['type' => 'message', 'title' => 'Briefing', 'body' => 'Rendez-vous à 20 h.', 'importance' => 'info', 'channels' => ['game'], 'target' => 'all'], $allowed, $now ?? strtotime('2026-11-08 12:00:00'));
    }

    public function testValidMessageKeepsEverythingNeededForEachChannel(): void
    {
        $r = self::check(['channels' => ['game', 'site'], 'importance' => 'urgent', 'ends_at' => '2026-11-10T12:00']);

        self::assertSame([], $r['errors']);
        self::assertSame(['game', 'site'], $r['data']['channels']);
        self::assertSame('urgent', $r['data']['importance']);
        self::assertSame(strtotime('2026-11-10 12:00'), $r['data']['game_expires_at']);
        self::assertTrue($r['data']['require_ack']);
    }

    public function testChannelMustExistForTheTypeAndBeAllowed(): void
    {
        $r = self::check(['type' => 'annonce', 'channels' => ['tasks']]);
        self::assertStringContainsString('n’existe pas pour le type', implode(' ', $r['errors']));

        $r = self::check(['type' => 'ordre', 'channels' => ['tasks']], ['site', 'phone', 'game', 'discord']);
        self::assertStringContainsString('pas le droit', implode(' ', $r['errors']));

        $r = self::check(['channels' => []]);
        self::assertContains('Choisissez au moins un canal de diffusion.', $r['errors']);
    }

    public function testSiteAndPhoneOnlyTargetTheWholeCommunity(): void
    {
        $r = self::check(['type' => 'consigne', 'channels' => ['phone'], 'target' => 'unit', 'unit_ids' => ['3']]);
        self::assertStringContainsString('ne sait pas viser', implode(' ', $r['errors']));

        $r = self::check(['channels' => ['game'], 'target' => 'unit', 'unit_ids' => ['3', 'x', '3']]);
        self::assertSame([], $r['errors']);
        self::assertSame([3], $r['data']['refs']);

        $r = self::check(['channels' => ['game'], 'target' => 'person']);
        self::assertStringContainsString('Choisissez qui reçoit', implode(' ', $r['errors']));
    }

    public function testOnlyTheSiteCanWaitForAStartDate(): void
    {
        $ok = self::check(['type' => 'annonce', 'channels' => ['site'], 'starts_at' => '2026-11-09T08:00']);
        self::assertSame([], $ok['errors']);

        $ko = self::check(['type' => 'annonce', 'channels' => ['site', 'game'], 'starts_at' => '2026-11-09T08:00']);
        self::assertStringContainsString('Seul le site sait attendre', implode(' ', $ko['errors']));

        $past = self::check(['ends_at' => '2026-11-01T08:00']);
        self::assertContains('La date de fin est déjà passée.', $past['errors']);

        $bad = self::check(['ends_at' => 'demain']);
        self::assertContains('Date illisible : utilisez le sélecteur de date.', $bad['errors']);
    }

    public function testGameChannelsRefuseTagsAndPhoneLimitsApply(): void
    {
        $r = self::check(['body' => 'Voir <b>ici</b>']);
        self::assertStringContainsString('Retirez les balises', implode(' ', $r['errors']));

        $site = self::check(['type' => 'annonce', 'channels' => ['site'], 'body' => 'a < b']);
        self::assertSame([], $site['errors']);

        $long = self::check(['type' => 'consigne', 'channels' => ['phone'], 'title' => str_repeat('x', 61)]);
        self::assertStringContainsString('App Communauté du téléphone', implode(' ', $long['errors']));

        $note = self::check(['type' => 'tip', 'channels' => ['phone'], 'title' => 'Radio', 'body' => "Canal 2\nsecours 5"]);
        self::assertSame(['kind' => 'tip', 'title' => 'Radio', 'body' => 'Canal 2 secours 5'], $note['data']['note']);
    }

    public function testTitleIsOneLineAndLengthsAreCapped(): void
    {
        $r = self::check(['title' => "  Alerte\nrouge  "]);
        self::assertSame('Alerte rouge', $r['data']['title']);

        $r = self::check(['title' => str_repeat('é', BC::MAX_TITLE + 1)]);
        self::assertContains('Titre trop long : 120 caractères au plus.', $r['errors']);

        $r = self::check(['body' => str_repeat('a', BC::MAX_BODY + 1)]);
        self::assertContains('Texte trop long : 2000 caractères au plus.', $r['errors']);
    }

    public function testKeyCarriesTypeAndDiscordAndOrderIdsRoundTrip(): void
    {
        $key = BC::newKey('ordre', true);
        self::assertMatchesRegularExpression('/^od[0-9a-f]{10}$/', $key);
        self::assertSame(['type' => 'ordre', 'discord' => true], BC::parseKey($key));
        self::assertNull(BC::parseKey('zz0123456789'));
        self::assertSame($key, BC::keyFromOrderExternalId(BC::orderExternalId($key)));
        self::assertSame($key, BC::keyFromOrderExternalId(BC::orderExternalId($key, 3)));
        self::assertNull(BC::keyFromOrderExternalId('ORD-W-1a2b3c4d5e6f'));
    }

    public function testImportanceMapsToWhatEachChannelAlreadyStores(): void
    {
        self::assertSame('urgent', BC::siteKind('annonce', 'urgent'));
        self::assertSame('notice', BC::siteKind('consigne', 'info'));
        self::assertSame('important', BC::siteImportance('info', ['highlight' => true]));
        self::assertSame(['info', 'warning', 'critical'], array_map([BC::class, 'gameLevel'], ['info', 'important', 'urgent']));
        self::assertSame(['ROUTINE', 'IMPORTANT', 'URGENT'], array_map([BC::class, 'orderPriority'], ['info', 'important', 'urgent']));
        self::assertSame('NOTIFY_FULL', BC::orderType('alerte', 'urgent'));
        self::assertSame('NOTIFY', BC::orderType('alerte', 'info'));
        self::assertSame('CUSTOM', BC::orderType('ordre', 'urgent'));
        self::assertSame('Accepté', BC::orderStepLabel('ACK'));
        self::assertSame('Exécuté', BC::orderStepLabel('DONE'));
    }

    public function testStates(): void
    {
        $now = strtotime('2026-11-08 12:00:00');
        self::assertSame('scheduled', BC::siteState(['is_active' => 1, 'starts_at' => '2026-11-09 08:00:00'], $now));
        self::assertSame('expired', BC::siteState(['is_active' => 1, 'ends_at' => '2026-11-01 08:00:00'], $now));
        self::assertSame('withdrawn', BC::siteState(['is_active' => 0], $now));
        self::assertSame('active', BC::siteState(['is_active' => 1], $now));

        $pending = ['acked_at' => null, 'cancelled_at' => null, 'expires_at' => '2026-11-09 12:00:00'];
        $acked = ['acked_at' => '2026-11-08 10:00:00', 'cancelled_at' => null, 'expires_at' => null];
        $cancelled = ['acked_at' => null, 'cancelled_at' => '2026-11-08 10:00:00', 'expires_at' => null];
        self::assertSame('active', BC::gameState([$pending, $acked], $now));
        self::assertSame('done', BC::gameState([$acked, $cancelled], $now));
        self::assertSame('withdrawn', BC::gameState([$cancelled], $now));
        self::assertSame('expired', BC::gameState([['acked_at' => null, 'cancelled_at' => null, 'expires_at' => '2026-11-01 00:00:00']], $now));
        self::assertSame('active', BC::mergeState(['withdrawn', 'active']));
    }
}
