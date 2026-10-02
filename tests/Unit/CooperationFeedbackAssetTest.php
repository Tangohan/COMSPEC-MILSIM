<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CooperationFeedbackAssetTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testShortActionsAnswerJsonOnlyWhenAskedAndKeepTheirChecks(): void
    {
        $c = $this->read('app/Controllers/Web/InterteamMissionWebController.php');
        foreach (['invite', 'accept', 'decline', 'remindPartner', 'removePartner', 'revokeGrant', 'addSitrep', 'assignMissionMember'] as $a) {
            $h = 'handle' . ucfirst($a);
            self::assertStringContainsString('return $this->ajaxify($this->' . $h . '($request, $params));', $c, $a);
        }
        self::assertStringContainsString("HTTP_X_REQUESTED_WITH", $c);
        self::assertStringContainsString("'field' => \$ok ? null : \$this->errorField", $c);
    }

    public function testToastIsGloballyAvailableAndAccessible(): void
    {
        $js = $this->read('public/assets/js/ath-toast.js');
        self::assertStringContainsString('window.athToast = function (variant, message)', $js);
        self::assertStringContainsString("'aria-live'", $js);
        self::assertStringContainsString("v !== 'error') setTimeout(close, 5000)", $js);
        self::assertStringContainsString('assets/js/ath-toast.js', $this->read('views/layout/main.php'));
    }

    public function testProgressiveFormsAndEmptyStates(): void
    {
        $ajax = $this->read('public/assets/js/cooperation/ajax-actions.js');
        self::assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $ajax);
        self::assertStringContainsString('aria-busy', $ajax);
        self::assertStringContainsString('fr-message--error', $ajax);
        foreach (['timeline', 'meeting', 'rex', 'exchange', 'negotiate', 'orbat'] as $view) {
            self::assertStringContainsString('ui/empty_state.php', $this->read('views/back_office/cooperation/missions/' . $view . '.php'), $view);
        }
    }
}
