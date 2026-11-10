<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Monitoring\AtakModReportClientState as State;
use App\Services\Monitoring\AtakModReportDiscordTicketService;
use App\Support\AtakReleaseInfo;
use PHPUnit\Framework\TestCase;

/** Tickets Discord des rapports du mod : versions comparées aux dernières publiées, intégrité du client. */
final class AtakModReportClientStateTest extends TestCase
{
    private const LATEST = ['atak' => '4.2.1', 'link' => '2.0.123', 'connect' => '1.6.34', 'sse' => '1.6.1'];

    private const STATS = ['reports' => 1, 'hits' => 1, 'players' => [], 'last_seen' => ''];

    private static function report(array $extra = []): array
    {
        return array_merge([
            'id' => 956, 'message' => 'ÉCHEC · PhotoUpload — file_not_found', 'channel' => 'Tx', 'source' => 'auto',
            'player_name' => 'GK2', 'callsign' => 'HAN', 'mod_version' => '1.6.34', 'extension_version' => '2.0.123',
        ], $extra);
    }

    public function testUpToDateComponentsAreLabelledByWhatTheyAre(): void
    {
        $field = State::versionsField(self::report(), self::LATEST);

        self::assertStringContainsString('Link 2.0.123 ✅ à jour · up to date', $field);
        // mod_version est la liaison Overwatch connect, pas l'ATAK natif.
        self::assertStringContainsString('Overwatch connect 1.6.34 ✅ à jour', $field);
        self::assertStringNotContainsString('Mod 1.6.34', $field);
        self::assertFalse(State::isOutdated(self::report(), self::LATEST));
        self::assertSame('', State::titleBadges(self::report(['client_attest' => 'valid']), self::LATEST));
    }

    public function testOutdatedComponentSaysWhichVersionIsAvailable(): void
    {
        $report = self::report(['extension_version' => '2.0.120', 'context_json' => '{"atak_version":"4.2.0","sse_version":"1.6.1"}']);
        $field = State::versionsField($report, self::LATEST);

        self::assertStringContainsString('ATAK 4.2.0 ⚠️ pas à jour (4.2.1 disponible) · outdated (4.2.1 available)', $field);
        self::assertStringContainsString('Link 2.0.120 ⚠️ pas à jour (2.0.123 disponible)', $field);
        self::assertStringContainsString('SSE 1.6.1 ✅ à jour', $field);
        self::assertTrue(State::isOutdated($report, self::LATEST));
        self::assertSame('⚠️ version périmée', State::titleBadges($report, self::LATEST));
    }

    public function testNumericComparisonAndNewerBuild(): void
    {
        self::assertSame(State::UP_TO_DATE, State::compare('2.0.123', '2.0.123.0'));
        self::assertSame(State::OUTDATED, State::compare('2.0.99', '2.0.123'));
        self::assertSame(State::NEWER, State::compare('2.0.130-dev', '2.0.123'));
        self::assertStringContainsString('🧪 plus récente que la publication (2.0.123)', State::versionsField(self::report(['extension_version' => '2.0.130']), self::LATEST));
    }

    public function testMissingOrUnknownVersions(): void
    {
        $report = self::report(['mod_version' => null, 'extension_version' => '']);
        $field = State::versionsField($report, self::LATEST);

        // ATAK natif : pas envoyé par le mod aujourd'hui ; SSE facultatif : masqué s'il n'est pas transmis.
        self::assertStringContainsString('ATAK — version non transmise · not reported', $field);
        self::assertStringContainsString('Link — version non transmise', $field);
        self::assertStringContainsString('Overwatch connect — version non transmise', $field);
        self::assertStringNotContainsString('SSE', $field);
        self::assertSame(State::UNREADABLE, State::compare('beta', '1.0'));
        self::assertSame(State::NO_REFERENCE, State::compare('1.0', null));
        self::assertStringContainsString('❔ dernière version inconnue', State::versionsField(self::report(), ['atak' => null, 'link' => null, 'connect' => null, 'sse' => null]));
        self::assertFalse(State::isOutdated($report, self::LATEST));
    }

    public function testGameValuesCannotInjectDiscordMarkup(): void
    {
        $field = State::versionsField(self::report(['extension_version' => '2.0.1 <@&123> **x**']), self::LATEST);

        self::assertStringNotContainsString('<@', $field);
        self::assertStringNotContainsString('**', $field);
    }

    public function testIntegrityOkKoAndUnknown(): void
    {
        self::assertSame(State::INTEGRITY_OK, State::integrityState('valid'));
        self::assertSame('✅ client vérifié : DLL officielle · verified official client', State::integrityField('valid'));

        self::assertSame(State::INTEGRITY_KO, State::integrityState('invalid'));
        self::assertSame(State::INTEGRITY_KO, State::integrityState('absent'));
        self::assertStringStartsWith('❌ client non vérifié', State::integrityField('invalid'));
        self::assertStringStartsWith('❌ client non vérifié', State::integrityField('absent'));
        self::assertSame('❌ client non vérifié', State::titleBadges(self::report(['client_attest' => 'absent']), self::LATEST));

        self::assertSame(State::INTEGRITY_UNKNOWN, State::integrityState(null));
        self::assertSame(State::INTEGRITY_UNKNOWN, State::integrityState('off'));
        self::assertSame('➖ non vérifié · not checked', State::integrityField(null));
        self::assertStringStartsWith('➖ non vérifié : attestation pas activée', State::integrityField('off'));
    }

    public function testHeaderEmbedShowsVersionsIntegrityAndBadges(): void
    {
        $ok = AtakModReportDiscordTicketService::buildHeaderEmbed(self::report(['client_attest' => 'valid']), 'new', self::STATS, '', self::LATEST);
        $fields = array_column($ok['fields'], 'value', 'name');

        self::assertSame('Ticket #956 · ÉCHEC · PhotoUpload — file\_not\_found', $ok['title']);
        self::assertStringContainsString('Link 2.0.123 ✅ à jour', $fields['Versions']);
        self::assertStringStartsWith('✅ client vérifié', $fields['Intégrité / Integrity']);

        $ko = AtakModReportDiscordTicketService::buildHeaderEmbed(
            self::report(['client_attest' => 'invalid', 'extension_version' => '2.0.120']),
            'new',
            self::STATS,
            '',
            self::LATEST
        );
        self::assertStringStartsWith('Ticket #956 · ❌ client non vérifié · ⚠️ version périmée · ', $ko['title']);
    }

    public function testLatestVersionsAreReadFromTheRepository(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', (string) AtakReleaseInfo::sseVersion($root));
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', (string) AtakReleaseInfo::connectVersion($root));
    }
}
