<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UserPortExhaustedException;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

final class UserPortServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $capsule->schema()->create('config', static function ($table): void {
            $table->string('item')->primary();
            $table->string('value');
            $table->string('type');
        });
        $capsule->schema()->create('user', static function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('port')->unique('user_port_unique');
        });
        Capsule::table('config')->insert([
            ['item' => 'min_port', 'value' => '10000', 'type' => 'int'],
            ['item' => 'max_port', 'value' => '10001', 'type' => 'int'],
        ]);
    }

    public function testAssignAndSaveNeverUsesZeroAndReservesAnUnusedPort(): void
    {
        User::query()->insert(['port' => 10000]);
        $user = new User();

        $this->assertTrue(UserPortService::assignAndSave($user));
        $this->assertSame(10001, (int) $user->port);
    }

    public function testExhaustedPoolFailsExplicitly(): void
    {
        User::query()->insert([['port' => 10000], ['port' => 10001]]);

        $this->expectException(UserPortExhaustedException::class);
        UserPortService::nextAvailable();
    }

    public function testAdminPortValidationRejectsCollisionsAndOutOfRangeValues(): void
    {
        $first = User::query()->create(['port' => 10000]);
        $second = User::query()->create(['port' => 10001]);

        $this->assertTrue(UserPortService::isAvailableForUser(10000, (int) $first->id));
        $this->assertFalse(UserPortService::isAvailableForUser(10001, (int) $first->id));
        $this->assertFalse(UserPortService::isAvailableForUser(9999, (int) $first->id));
        $this->assertTrue(UserPortService::isAvailableForUser(10001, (int) $second->id));
    }

    public function testResetWorksWhenEveryConfiguredPortIsAlreadyOccupied(): void
    {
        $first = User::query()->create(['port' => 10000]);
        $second = User::query()->create(['port' => 10001]);

        $this->assertSame(2, UserPortService::reassignAll());
        $this->assertSame(10001, (int) $first->fresh()->port);
        $this->assertSame(10000, (int) $second->fresh()->port);
        $this->assertSame(0, User::query()->where('port', 0)->count());
    }
}
