<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class CronInactiveUserTest extends TestCase
{
    private Capsule $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new Capsule();
        $this->db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ], 'default');
        $this->db->setAsGlobal();
        $this->db->bootEloquent();

        Capsule::schema()->create('config', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('item');
            $table->string('value');
            $table->string('type');
        });
        Capsule::schema()->create('user', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->boolean('is_admin');
            $table->boolean('is_inactive');
            $table->integer('last_check_in_time');
            $table->integer('last_login_time');
            $table->integer('last_use_time');
        });

        foreach ([
            'detect_inactive_user_checkin_days',
            'detect_inactive_user_login_days',
            'detect_inactive_user_use_days',
        ] as $item) {
            Capsule::table('config')->insert([
                'item' => $item,
                'value' => '7',
                'type' => 'int',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();
        parent::tearDown();
    }

    public function testAnyRecentActivityReactivatesUserWhileAllStaleActivityMarksInactive(): void
    {
        $stale = time() - 10 * 86400;
        $recent = time() - 3600;
        Capsule::table('user')->insert([
            [
                'id' => 1,
                'is_admin' => false,
                'is_inactive' => true,
                'last_check_in_time' => $stale,
                'last_login_time' => $recent,
                'last_use_time' => $stale,
            ],
            [
                'id' => 2,
                'is_admin' => false,
                'is_inactive' => false,
                'last_check_in_time' => $stale,
                'last_login_time' => $stale,
                'last_use_time' => $stale,
            ],
        ]);

        ob_start();
        Cron::detectInactiveUser();
        ob_end_clean();

        self::assertSame(0, (int) Capsule::table('user')->find(1)->is_inactive);
        self::assertSame(1, (int) Capsule::table('user')->find(2)->is_inactive);
    }
}
