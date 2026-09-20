<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakC2OrderIssueAssetTest extends TestCase
{
    public function testIssueOrderUsesAthenaIdentityAndAvoidsNetworkHashMap(): void
    {
        $root = dirname(__DIR__, 2);
        $issue = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_issueOrder.sqf');
        $recv = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_receiveOrder.sqf');
        $issuer = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_orderIssuerLabel.sqf');
        $poll = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollChatMessages.sqf');
        $status = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateOrderStatus.sqf');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/AtakDataRepository.php');

        self::assertStringContainsString('versionStr = "1.6.5"', $cfg);
        self::assertStringContainsString('class orderIssuerLabel', $cfg);

        self::assertStringContainsString('toFixed 0', $issue);
        self::assertStringContainsString('orderIssuerLabel', $issue);
        self::assertStringNotContainsString('_issuer = name player', $issue);
        self::assertStringContainsString('setVariable ["COMSPEC_Orders", _orders, false]', $issue);
        self::assertStringContainsString('remoteExecCall ["comspec_overwatch_connect_fnc_receiveOrder", -2, false]', $issue);
        self::assertStringNotContainsString('[_order] remoteExecCall', $issue);

        self::assertStringContainsString('comspec_profile_name', $issuer);
        self::assertStringNotContainsString('name player', $issuer);

        self::assertStringContainsString('createHashMapFromArray _order', $recv);
        self::assertStringContainsString('orderIssuerLabel', $recv);

        self::assertStringContainsString('setVariable ["COMSPEC_Orders", _orders, false]', $status);
        self::assertStringNotContainsString('name player', $status);

        self::assertStringContainsString('find "ORDER|") >= 0', $poll);
        self::assertStringContainsString('hidden_from_chat', $api);
        self::assertStringContainsString('withoutProtocolOrderChat', $repo);
    }
}
