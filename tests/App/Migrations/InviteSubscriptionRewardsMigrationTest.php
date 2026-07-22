<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class InviteSubscriptionRewardsMigrationTest extends TestCase
{
    public function testMigrationBackfillsReferralsAndEnforcesOneRewardPerInvitedUser(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE `user` (`id` INTEGER PRIMARY KEY, `ref_by` INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec('INSERT INTO `user` (`id`, `ref_by`) VALUES (1, 0), (2, 1), (3, 1)');
        $migration = require dirname(__DIR__, 3)
            . '/db/migrations/2026072201-add_invite_subscription_rewards.php';

        $migration->apply($pdo);
        $migration->apply($pdo);

        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM `user_referral`')->fetchColumn());
        $this->assertSame(
            1,
            (int) $pdo->query('SELECT `inviter_user_id` FROM `user_referral` WHERE `invited_user_id` = 2')
                ->fetchColumn()
        );

        $pdo->exec(
            "INSERT INTO `invite_subscription_reward`
             (`inviter_user_id`, `invited_user_id`, `qualifying_order_id`, `invoice_id`,
              `product_sku`, `reward_days`, `create_time`)
             VALUES (1, 2, 10, 20, 'mini', 30, 1)"
        );

        $this->expectException(PDOException::class);
        $pdo->exec(
            "INSERT INTO `invite_subscription_reward`
             (`inviter_user_id`, `invited_user_id`, `qualifying_order_id`, `invoice_id`,
              `product_sku`, `reward_days`, `create_time`)
             VALUES (1, 2, 11, 21, 'standard', 60, 2)"
        );
    }
}
