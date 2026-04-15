<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Platform\ModuleReleaseAccessResolver;
use PHPUnit\Framework\TestCase;

final class ModuleReleaseAccessResolverTest extends TestCase
{
    public function testDeniesInactiveModule(): void
    {
        $resolver = new ModuleReleaseAccessResolver();

        $result = $resolver->resolve(
            ['is_active' => false, 'is_public' => true],
            ['PROD' => ['id' => 1, 'version' => '1.0.0']],
            [],
            [],
            []
        );

        self::assertFalse($result['allowed']);
        self::assertSame('module_inactive', $result['reason']);
    }

    public function testAllowsPrivateModuleForAuthorizedCommunityOnTestChannel(): void
    {
        $resolver = new ModuleReleaseAccessResolver();

        $result = $resolver->resolve(
            ['is_active' => true, 'is_public' => false],
            ['TEST' => ['id' => 4, 'version' => '3.0.0-beta', 'module_version_id' => 4]],
            [
                [
                    'id' => 10,
                    'rule_type' => 'allow_community',
                    'community_id' => 55,
                    'environment_channel_id' => 2,
                    'channel_map' => [2 => 'TEST'],
                    'priority' => 200,
                    'is_active' => 1,
                ],
            ],
            [],
            [],
            ['target_channel' => 'TEST', 'community_ids' => [55]]
        );

        self::assertTrue($result['allowed']);
        self::assertSame('3.0.0-beta', $result['release']['version']);
    }

    public function testFeatureFlagCanBeLimitedToCommunities(): void
    {
        $resolver = new ModuleReleaseAccessResolver();

        $result = $resolver->resolve(
            ['is_active' => true, 'is_public' => true],
            ['PROD' => ['id' => 2, 'version' => '1.4.2', 'module_version_id' => 2]],
            [],
            ['BETA_UI_UX'],
            [
                'rich_reply_editor' => [
                    'default_state' => false,
                    'rules' => [
                        [
                            'id' => 1,
                            'rule_type' => 'allow_community',
                            'community_code' => 'BETA_UI_UX',
                            'state' => true,
                            'priority' => 100,
                            'is_active' => 1,
                        ],
                    ],
                ],
            ],
            ['target_channel' => 'PROD']
        );

        self::assertTrue($result['allowed']);
        self::assertTrue($result['feature_flags']['rich_reply_editor']);
    }

    public function testHigherPriorityDenyAllBeatsAllowCommunity(): void
    {
        $resolver = new ModuleReleaseAccessResolver();

        $result = $resolver->resolve(
            ['is_active' => true, 'is_public' => true],
            ['TEST' => ['id' => 6, 'version' => '2.0.0-rc1', 'module_version_id' => 6]],
            [
                [
                    'id' => 7,
                    'rule_type' => 'allow_community',
                    'community_code' => 'QA_ADMIN',
                    'priority' => 80,
                    'is_active' => 1,
                ],
                [
                    'id' => 8,
                    'rule_type' => 'deny_all',
                    'priority' => 100,
                    'is_active' => 1,
                ],
            ],
            ['QA_ADMIN'],
            [],
            ['target_channel' => 'TEST']
        );

        self::assertFalse($result['allowed']);
        self::assertSame('module_denied_all', $result['reason']);
    }

    public function testAllowCommunityQaAdminByCode(): void
    {
        $resolver = new ModuleReleaseAccessResolver();

        $result = $resolver->resolve(
            ['is_active' => true, 'is_public' => false],
            ['TEST' => ['id' => 8, 'version' => '1.0.0-rc1', 'module_version_id' => 8]],
            [
                [
                    'id' => 20,
                    'rule_type' => 'allow_community',
                    'community_code' => 'QA_ADMIN',
                    'priority' => 200,
                    'is_active' => 1,
                ],
            ],
            ['QA_ADMIN'],
            [],
            ['target_channel' => 'TEST']
        );

        self::assertTrue($result['allowed']);
        self::assertSame('module_allowed_community', $result['reason']);
    }
}
