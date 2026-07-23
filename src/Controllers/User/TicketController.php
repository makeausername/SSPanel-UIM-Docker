<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Config;
use App\Models\Ticket;
use App\Services\FrontendI18n;
use App\Services\Notification;
use App\Services\RateLimit;
use App\Services\TicketReplyService;
use App\Utils\ResponseHelper;
use App\Utils\Tools;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Smarty\Exception;
use Telegram\Bot\Exceptions\TelegramSDKException;
use function json_decode;
use function json_encode;
use function in_array;
use function mb_strlen;
use function nl2br;
use function trim;
use function time;

final class TicketController extends BaseController
{
    private const MAX_TITLE_LENGTH = 120;
    private const MAX_COMMENT_LENGTH = 5000;

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! Config::obtain('enable_ticket')) {
            return $response->withRedirect('/user');
        }

        $tickets = (new Ticket())->where('userid', $this->user->id)->orderBy('datetime', 'desc')->get();

        foreach ($tickets as $ticket) {
            $ticket->status = self::ticketStatus((string) $ticket->status);
            $ticket->type = self::ticketType((string) $ticket->type);
            $ticket->datetime = Tools::toDateTime((int) $ticket->datetime);
        }

        return $response->write(
            $this->view()
                ->assign('tickets', $tickets)
                ->fetch('user/ticket/index.tpl')
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws TelegramSDKException
     * @throws GuzzleException
     */
    public function add(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $title = trim((string) ($request->getParam('title') ?? ''));
        $comment = trim((string) ($request->getParam('comment') ?? ''));
        $type = trim((string) ($request->getParam('type') ?? ''));

        if (! Config::obtain('enable_ticket') ||
            $this->user->is_shadow_banned ||
            ! RateLimit::checkSafely('ticket', (string) $this->user->id, true) ||
            $title === '' ||
            $comment === '' ||
            ! in_array($type, ['howto', 'billing', 'account', 'other'], true) ||
            mb_strlen($title) > self::MAX_TITLE_LENGTH ||
            mb_strlen($comment) > self::MAX_COMMENT_LENGTH
        ) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.ticket_create_failed'));
        }

        $content = [
            [
                'comment_id' => 0,
                'commenter_type' => 'user',
                'commenter_name' => $this->user->user_name,
                'comment' => $this->antiXss->xss_clean($comment),
                'datetime' => time(),
            ],
        ];

        $ticket = new Ticket();
        $ticket->title = $this->antiXss->xss_clean($title);
        $ticket->content = json_encode($content);
        $ticket->userid = $this->user->id;
        $ticket->datetime = time();
        $ticket->status = 'open_wait_admin';
        $ticket->type = $this->antiXss->xss_clean($type);
        $ticket->save();

        if (Config::obtain('mail_ticket')) {
            Notification::notifyAdmin(
                $_ENV['appName'] . '-新工单被开启',
                '管理员，有人开启了新的工单，请你及时处理。'
            );
        }

        return $response->withHeader('HX-Redirect', '/user/ticket/' . $ticket->id . '/view');
    }

    /**
     * @throws GuzzleException
     * @throws TelegramSDKException
     * @throws ClientExceptionInterface
     */
    public function reply(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $comment = trim((string) ($request->getParam('comment') ?? ''));

        if (! Config::obtain('enable_ticket') ||
            $this->user->is_shadow_banned ||
            $comment === '' ||
            mb_strlen($comment) > self::MAX_COMMENT_LENGTH ||
            ! RateLimit::checkSafely('ticket_reply', (string) $this->user->id, true)
        ) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.ticket_reply_failed'));
        }

        $ticket = (new TicketReplyService())->append(
            (int) $id,
            'user',
            (string) $this->user->user_name,
            $this->antiXss->xss_clean($comment),
            'open_wait_admin',
            (int) $this->user->id
        );

        if ($ticket === null) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.ticket_not_found'));
        }

        if (Config::obtain('mail_ticket')) {
            Notification::notifyAdmin(
                $_ENV['appName'] . '-工单被回复',
                '管理员，有人回复了 <a href="' .
                $_ENV['baseUrl'] . '/admin/ticket/' . $ticket->id . '/view">#' . $ticket->id .
                '</a> 工单，请你及时处理。'
            );
        }

        return $response->withHeader('HX-Refresh', 'true');
    }

    /**
     * @throws Exception
     */
    public function detail(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! Config::obtain('enable_ticket')) {
            return $response->withRedirect('/user');
        }

        $id = $args['id'];
        $ticket = (new Ticket())->where('id', '=', $id)->where('userid', $this->user->id)->first();

        if ($ticket === null) {
            return $response->withRedirect('/user/ticket');
        }

        $comments = json_decode($ticket->content);

        foreach ($comments as $comment) {
            $comment->comment = nl2br($comment->comment);
            $comment->datetime = Tools::toDateTime((int) $comment->datetime);
        }

        $ticket->status = self::ticketStatus((string) $ticket->status);
        $ticket->type = self::ticketType((string) $ticket->type);
        $ticket->datetime = Tools::toDateTime((int) $ticket->datetime);

        return $response->write(
            $this->view()
                ->assign('ticket', $ticket)
                ->assign('comments', $comments)
                ->fetch('user/ticket/view.tpl')
        );
    }

    private static function ticketStatus(string $status): string
    {
        return match ($status) {
            'closed' => FrontendI18n::trans('ticket.status_closed'),
            'open_wait_user' => FrontendI18n::trans('ticket.status_open_wait_user'),
            'open_wait_admin' => FrontendI18n::trans('ticket.status_open_wait_admin'),
            default => FrontendI18n::trans('ticket.status_unknown'),
        };
    }

    private static function ticketType(string $type): string
    {
        return match ($type) {
            'howto' => FrontendI18n::trans('ticket.type_howto'),
            'billing' => FrontendI18n::trans('ticket.type_billing'),
            'account' => FrontendI18n::trans('ticket.type_account'),
            default => FrontendI18n::trans('ticket.type_other'),
        };
    }
}
