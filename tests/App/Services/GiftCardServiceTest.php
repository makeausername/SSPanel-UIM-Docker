<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GiftCard;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GiftCardServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $capsule->schema()->create('gift_card', static function ($table): void {
            $table->increments('id');
            $table->string('card', 64)->unique();
            $table->integer('balance');
            $table->integer('create_time');
            $table->integer('status');
            $table->integer('use_time');
            $table->integer('use_user');
        });
    }

    public function testCreateBatchPersistsTheRequestedNumberOfUniqueCodes(): void
    {
        $codes = GiftCardService::createBatch(5, 100, 12);

        $this->assertCount(5, $codes);
        $this->assertCount(5, array_unique($codes));
        $this->assertSame(5, GiftCard::query()->count());
        $this->assertSame([12], array_values(array_unique(array_map('strlen', $codes))));
    }

    public function testCreateBatchRejectsUnboundedRequests(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GiftCardService::createBatch(GiftCardService::MAX_BATCH_SIZE + 1, 100, 12);
    }
}
