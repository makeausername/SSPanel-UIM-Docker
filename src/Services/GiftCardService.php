<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GiftCard;
use App\Utils\Tools;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use function array_keys;
use function count;
use function in_array;
use function str_contains;
use function strtolower;
use function time;

final class GiftCardService
{
    public const MAX_BATCH_SIZE = 500;
    public const MAX_VALUE = 1_000_000;
    public const ALLOWED_LENGTHS = [12, 18, 24, 30, 36];

    /**
     * @return list<string>
     */
    public static function createBatch(int $count, int $value, int $length): array
    {
        if ($count < 1 || $count > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException('Gift card batch size is outside the allowed range.');
        }
        if ($value < 1 || $value > self::MAX_VALUE) {
            throw new InvalidArgumentException('Gift card value is outside the allowed range.');
        }
        if (! in_array($length, self::ALLOWED_LENGTHS, true)) {
            throw new InvalidArgumentException('Gift card length is not allowed.');
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $codes = [];
            while (count($codes) < $count) {
                $code = Tools::genRandomChar($length);
                if ($code === false) {
                    throw new RuntimeException('Unable to generate a secure gift card code.');
                }
                $codes[$code] = true;
            }
            $codeList = array_keys($codes);

            if ((new GiftCard())->whereIn('card', $codeList)->exists()) {
                continue;
            }

            $createdAt = time();
            $rows = [];
            foreach ($codeList as $code) {
                $rows[] = [
                    'card' => $code,
                    'balance' => $value,
                    'create_time' => $createdAt,
                    'status' => 0,
                    'use_time' => 0,
                    'use_user' => 0,
                ];
            }

            try {
                $saved = DB::connection()->transaction(
                    static fn (): bool => (new GiftCard())->insert($rows)
                );
                if (! $saved) {
                    throw new RuntimeException('Unable to save the generated gift cards.');
                }

                return $codeList;
            } catch (QueryException $e) {
                if (! self::isCodeCollision($e)) {
                    throw $e;
                }
                // A concurrent request won the unique-code race. Regenerate the whole batch.
            }
        }

        throw new RuntimeException('Unable to reserve unique gift card codes after repeated attempts.');
    }

    private static function isCodeCollision(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'gift_card_card_unique')
            || str_contains($message, 'gift_card.card');
    }
}
