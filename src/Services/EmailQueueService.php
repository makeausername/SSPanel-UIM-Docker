<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailQueue;
use App\Utils\Tools;
use Throwable;
use function bin2hex;
use function json_decode;
use function max;
use function min;
use function random_bytes;
use function substr;
use function time;

final class EmailQueueService
{
    private const MAX_ATTEMPTS = 5;
    private const LEASE_SECONDS = 600;

    public function processAvailable(int $maxSeconds = 60): int
    {
        $startedAt = time();
        $processed = 0;

        while (time() - $startedAt <= $maxSeconds && $this->processOne()) {
            $processed++;
        }

        return $processed;
    }

    public function processOne(?callable $sender = null): bool
    {
        $token = bin2hex(random_bytes(16));
        $queue = DB::connection()->transaction(static function () use ($token): ?EmailQueue {
            $now = time();
            $queue = (new EmailQueue())
                ->where(static function ($query) use ($now): void {
                    $query->where(static function ($ready) use ($now): void {
                        $ready->whereIn('status', ['pending', 'retry'])
                            ->where('next_attempt_at', '<=', $now);
                    })->orWhere(static function ($stale) use ($now): void {
                        $stale->where('status', 'processing')
                            ->where('locked_at', '<=', $now - self::LEASE_SECONDS);
                    });
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($queue === null) {
                return null;
            }

            $queue->status = 'processing';
            $queue->attempts = (int) $queue->attempts + 1;
            $queue->locked_at = $now;
            $queue->lock_token = $token;
            $queue->last_error = null;
            $queue->save();

            return $queue;
        });

        if ($queue === null) {
            return false;
        }

        try {
            if (! Tools::isEmail((string) $queue->to_email)) {
                throw new \InvalidArgumentException('Invalid recipient email address.');
            }

            $send = $sender ?? static function (EmailQueue $message): void {
                Mail::send(
                    $message->to_email,
                    $message->subject,
                    $message->template,
                    json_decode((string) $message->array)
                );
            };
            $send($queue);
            $this->markSent((int) $queue->id, $token);
        } catch (Throwable $error) {
            $this->markFailed((int) $queue->id, $token, $error);
        }

        return true;
    }

    private function markSent(int $id, string $token): void
    {
        DB::connection()->transaction(static function () use ($id, $token): void {
            $queue = (new EmailQueue())->where('id', $id)->where('lock_token', $token)->lockForUpdate()->first();

            if ($queue !== null) {
                $queue->delete();
            }
        });
    }

    private function markFailed(int $id, string $token, Throwable $error): void
    {
        DB::connection()->transaction(static function () use ($id, $token, $error): void {
            $queue = (new EmailQueue())->where('id', $id)->where('lock_token', $token)->lockForUpdate()->first();

            if ($queue === null) {
                return;
            }

            $attempts = max(1, (int) $queue->attempts);
            $queue->status = $attempts >= self::MAX_ATTEMPTS ? 'dead' : 'retry';
            $queue->next_attempt_at = time() + min(3600, 60 * (2 ** ($attempts - 1)));
            $queue->locked_at = null;
            $queue->lock_token = null;
            $queue->last_error = substr($error->getMessage(), 0, 512);
            $queue->save();
        });
    }
}
