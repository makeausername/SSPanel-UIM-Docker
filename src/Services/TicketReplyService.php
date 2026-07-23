<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use function count;
use function is_array;
use function json_decode;
use function json_encode;
use function max;
use function time;

final class TicketReplyService
{
    public const MAX_COMMENTS = 200;

    public function append(
        int $ticketId,
        string $commenterType,
        string $commenterName,
        string $comment,
        string $nextStatus,
        ?int $userId = null
    ): ?Ticket {
        return DB::connection()->transaction(static function () use (
            $ticketId,
            $commenterType,
            $commenterName,
            $comment,
            $nextStatus,
            $userId
        ): ?Ticket {
            $query = (new Ticket())->where('id', $ticketId);
            if ($userId !== null) {
                $query->where('userid', $userId);
            }

            $ticket = $query->lockForUpdate()->first();
            if ($ticket === null || (string) $ticket->status === 'closed') {
                return null;
            }

            $content = json_decode((string) $ticket->content, true);
            $content = is_array($content) ? $content : [];
            if (count($content) >= self::MAX_COMMENTS) {
                return null;
            }

            $lastCommentId = -1;
            foreach ($content as $entry) {
                if (is_array($entry)) {
                    $lastCommentId = max($lastCommentId, (int) ($entry['comment_id'] ?? -1));
                }
            }

            $content[] = [
                'comment_id' => $lastCommentId + 1,
                'commenter_type' => $commenterType,
                'commenter_name' => $commenterName,
                'comment' => $comment,
                'datetime' => time(),
            ];

            $ticket->content = json_encode($content, JSON_THROW_ON_ERROR);
            $ticket->status = $nextStatus;
            $ticket->save();

            return $ticket;
        });
    }
}
