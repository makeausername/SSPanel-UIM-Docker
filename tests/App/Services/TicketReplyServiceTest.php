<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class TicketReplyServiceTest extends TestCase
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

        Capsule::schema()->create('ticket', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('userid');
            $table->text('content');
            $table->string('status');
        });
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();
        parent::tearDown();
    }

    public function testRepliesAreAppendedWithMonotonicIdsUnderRowLock(): void
    {
        Capsule::table('ticket')->insert([
            'id' => 1,
            'userid' => 10,
            'content' => json_encode([[
                'comment_id' => 0,
                'comment' => 'first',
            ]]),
            'status' => 'open_wait_admin',
        ]);
        $service = new TicketReplyService();

        self::assertNotNull($service->append(1, 'admin', 'Admin', 'second', 'open_wait_user'));
        self::assertNotNull($service->append(1, 'user', 'User', 'third', 'open_wait_admin', 10));

        $content = json_decode((string) Capsule::table('ticket')->find(1)->content, true);
        self::assertSame([0, 1, 2], array_column($content, 'comment_id'));
        self::assertSame('open_wait_admin', Capsule::table('ticket')->find(1)->status);
    }

    public function testClosedOrForeignTicketCannotBeReopened(): void
    {
        Capsule::table('ticket')->insert([
            'id' => 2,
            'userid' => 10,
            'content' => '[]',
            'status' => 'closed',
        ]);
        $service = new TicketReplyService();

        self::assertNull($service->append(2, 'user', 'User', 'reply', 'open_wait_admin', 10));
        self::assertNull($service->append(2, 'user', 'User', 'reply', 'open_wait_admin', 11));
    }
}
