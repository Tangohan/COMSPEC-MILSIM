<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MessagingThreadPresenter as P;
use PHPUnit\Framework\TestCase;

final class MessagingThreadPresenterTest extends TestCase
{
    private int $now;

    protected function setUp(): void
    {
        date_default_timezone_set('Europe/Paris');
        $this->now = (int) strtotime('2026-10-05 15:00:00');
    }

    public function testInitials(): void
    {
        self::assertSame('CD', P::initials('Cpt. Durand'));
        self::assertSame('VI', P::initials('Viper'));
        self::assertSame('ÉL', P::initials('élodie'));
        self::assertSame('?', P::initials('  '));
    }

    public function testListTime(): void
    {
        self::assertSame('À l’instant', P::listTime('2026-10-05 14:59:30', $this->now));
        self::assertSame('12 min', P::listTime('2026-10-05 14:48:00', $this->now));
        self::assertSame('09:15', P::listTime('2026-10-05 09:15:00', $this->now));
        self::assertSame('Hier', P::listTime('2026-10-04 23:59:00', $this->now));
        self::assertSame('jeu.', P::listTime('2026-10-01 10:00:00', $this->now));
        self::assertSame('12 sept.', P::listTime('2026-09-12 10:00:00', $this->now));
        self::assertSame('12/09/2025', P::listTime('2025-09-12 10:00:00', $this->now));
        self::assertSame('', P::listTime('', $this->now));
    }

    public function testDayLabel(): void
    {
        self::assertSame('Aujourd’hui', P::dayLabel('2026-10-05 00:01:00', $this->now));
        self::assertSame('Hier', P::dayLabel('2026-10-04 08:00:00', $this->now));
        self::assertSame('lundi 28 septembre', P::dayLabel('2026-09-28 08:00:00', $this->now));
        self::assertSame('vendredi 12 septembre 2025', P::dayLabel('2025-09-12 08:00:00', $this->now));
    }

    public function testPeerLabel(): void
    {
        self::assertSame('Encadrement', P::peerLabel(['created_by_user_id' => 7, 'creator_name' => 'Viper'], 7));
        self::assertSame('Viper', P::peerLabel(['created_by_user_id' => 7, 'creator_name' => 'Viper'], 2));
        self::assertSame('Membre', P::peerLabel(['created_by_user_id' => 7, 'creator_name' => ''], 2));
    }

    public function testGroupMessagesByDayAuthorAndUnread(): void
    {
        $messages = [
            ['id' => 1, 'sender_user_id' => 1, 'display_name' => 'Viper', 'body' => 'a', 'created_at' => '2026-10-04 10:00:00'],
            ['id' => 2, 'sender_user_id' => 2, 'display_name' => 'Cpt. Durand', 'body' => 'b', 'created_at' => '2026-10-04 10:05:00'],
            ['id' => 3, 'sender_user_id' => 2, 'display_name' => 'Cpt. Durand', 'body' => 'c', 'created_at' => '2026-10-04 10:06:00'],
            ['id' => 4, 'sender_user_id' => 2, 'display_name' => 'Cpt. Durand', 'body' => 'd', 'created_at' => '2026-10-04 11:00:00'],
            ['id' => 5, 'sender_user_id' => 1, 'display_name' => 'Viper', 'body' => 'e', 'created_at' => '2026-10-05 09:00:00'],
            ['id' => 6, 'sender_user_id' => 2, 'display_name' => 'Cpt. Durand', 'body' => 'f', 'created_at' => '2026-10-05 09:30:00'],
            ['id' => 7, 'sender_user_id' => 2, 'display_name' => 'Cpt. Durand', 'body' => 'g', 'created_at' => '2026-10-05 09:31:00'],
        ];
        $days = P::groupMessages($messages, 1, '2026-10-05 09:10:00', $this->now);

        self::assertCount(2, $days);
        self::assertSame('Hier', $days[0]['label']);
        self::assertSame('Aujourd’hui', $days[1]['label']);

        // Hier : moi, Durand (2 messages rapprochés), Durand (1 h plus tard → nouveau bloc).
        $g = $days[0]['groups'];
        self::assertCount(3, $g);
        self::assertTrue($g[0]['mine']);
        self::assertSame('Vous', $g[0]['name']);
        self::assertSame(['b', 'c'], array_column($g[1]['messages'], 'body'));
        self::assertSame('CD', $g[1]['initials']);
        self::assertSame(['d'], array_column($g[2]['messages'], 'body'));
        self::assertFalse($g[1]['unread_before']);

        // Aujourd’hui : le premier message reçu après la dernière lecture porte le repère.
        $g = $days[1]['groups'];
        self::assertCount(2, $g);
        self::assertFalse($g[0]['unread_before']);
        self::assertTrue($g[1]['unread_before']);
        self::assertSame(['f', 'g'], array_column($g[1]['messages'], 'body'));
    }

    public function testNoUnreadMarkerWithoutReadStateOrForOwnMessages(): void
    {
        $messages = [
            ['id' => 1, 'sender_user_id' => 1, 'display_name' => 'Viper', 'body' => 'a', 'created_at' => '2026-10-05 10:00:00'],
        ];
        $days = P::groupMessages($messages, 1, '2026-10-05 09:00:00', $this->now);
        self::assertFalse($days[0]['groups'][0]['unread_before']);

        $days = P::groupMessages([['sender_user_id' => 2, 'body' => 'x', 'created_at' => '2026-10-05 10:00:00']], 1, null, $this->now);
        self::assertFalse($days[0]['groups'][0]['unread_before']);
        self::assertSame('Participant', $days[0]['groups'][0]['name']);
    }
}
