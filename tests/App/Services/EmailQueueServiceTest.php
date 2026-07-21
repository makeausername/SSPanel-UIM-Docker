<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailQueue;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function time;

final class EmailQueueServiceTest extends TestCase
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

        Capsule::schema()->create('email_queue', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('to_email');
            $table->string('subject');
            $table->string('template');
            $table->text('array');
            $table->integer('time');
            $table->string('status')->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('next_attempt_at')->default(0);
            $table->integer('locked_at')->nullable();
            $table->string('lock_token')->nullable();
            $table->string('last_error')->nullable();
            $table->integer('sent_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();
        parent::tearDown();
    }

    public function testSuccessfulMessageIsRemovedAfterSend(): void
    {
        $this->seedQueue();
        $sent = 0;

        $processed = (new EmailQueueService())->processOne(
            static function (EmailQueue $message) use (&$sent): void {
                $sent++;
            }
        );

        $this->assertTrue($processed);
        $this->assertSame(1, $sent);
        $this->assertSame(0, Capsule::table('email_queue')->count());
    }

    public function testFailureIsRetriedThenMovedToDeadLetterState(): void
    {
        $this->seedQueue(['attempts' => 4]);

        (new EmailQueueService())->processOne(static function (): void {
            throw new RuntimeException('SMTP unavailable');
        });

        $queue = Capsule::table('email_queue')->first();
        $this->assertSame('dead', $queue->status);
        $this->assertSame(5, (int) $queue->attempts);
        $this->assertSame('SMTP unavailable', $queue->last_error);
        $this->assertNull($queue->lock_token);
    }

    public function testExpiredProcessingLeaseCanBeReclaimed(): void
    {
        $this->seedQueue([
            'status' => 'processing',
            'locked_at' => time() - 601,
            'lock_token' => 'abandoned',
        ]);

        $this->assertTrue((new EmailQueueService())->processOne(static function (): void {
        }));
        $this->assertSame(0, Capsule::table('email_queue')->count());
    }

    private function seedQueue(array $overrides = []): void
    {
        Capsule::table('email_queue')->insert(array_merge([
            'to_email' => 'user@example.com',
            'subject' => 'Subject',
            'template' => 'template.tpl',
            'array' => '{}',
            'time' => time(),
            'status' => 'pending',
            'attempts' => 0,
            'next_attempt_at' => 0,
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => null,
            'sent_at' => null,
        ], $overrides));
    }
}
