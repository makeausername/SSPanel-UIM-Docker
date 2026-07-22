<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $db = new Capsule();
        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ], 'default');
        $db->setAsGlobal();
        $db->bootEloquent();

        Capsule::schema()->create('config', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('item')->unique();
            $table->text('value');
            $table->string('class');
            $table->integer('is_public')->default(0);
            $table->string('type')->default('string');
            $table->text('default')->nullable();
            $table->string('mark')->default('');
        });

        Capsule::table('config')->insert([
            'item' => 'daily_job_hour',
            'value' => '0',
            'class' => 'cron',
            'type' => 'int',
        ]);
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();
        parent::tearDown();
    }

    public function testSetUpdatesExistingValueAndRejectsUnknownItem(): void
    {
        $this->assertTrue(Config::set('daily_job_hour', 6));
        $this->assertSame('6', (string) Capsule::table('config')->where('item', 'daily_job_hour')->value('value'));
        $this->assertFalse(Config::set('missing_item', 1));
    }
}
