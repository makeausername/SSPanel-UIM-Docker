<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AdminPermissionService;
use App\Services\DataTableRequest;
use App\Services\LLM;
use App\Services\Notification;
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
use function count;
use function htmlspecialchars;
use function json_decode;
use function mb_strlen;
use function nl2br;
use function trim;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

final class TicketController extends BaseController
{
    private const MAX_COMMENT_LENGTH = 5000;

    private static array $details =
        [
            'field' => [
                'op' => '操作',
                'id' => '工单ID',
                'title' => '主题',
                'status' => '工单状态',
                'type' => '工单类型',
                'userid' => '提交用户',
                'datetime' => '创建时间',
            ],
        ];

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/ticket/index.tpl')
        );
    }

    public function reply(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $comment = trim((string) ($request->getParam('comment') ?? ''));

        if ($comment === '' || mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            return ResponseHelper::error($response, '请输入评论内容');
        }

        $ticket = (new TicketReplyService())->append(
            (int) $id,
            'admin',
            'Admin',
            $this->antiXss->xss_clean($comment),
            'open_wait_user'
        );

        if ($ticket === null) {
            return ResponseHelper::error($response, '工单不存在');
        }
        $ticketUser = (new User())->find($ticket->userid);

        if ($ticketUser === null) {
            return $response->withHeader('HX-Refresh', 'true');
        }

        try {
            Notification::notifyUser(
                $ticketUser,
                $_ENV['appName'] . '-工单被回复',
                '你好，有人回复了<a href="' . $_ENV['baseUrl'] . '/user/ticket/' . $ticket->id . '/view">工单</a>，请你查看。'
            );
        } catch (TelegramSDKException|GuzzleException|ClientExceptionInterface $e) {
            return $response->withHeader('HX-Refresh', 'true');
        }

        return $response->withHeader('HX-Refresh', 'true');
    }

    public function llmReply(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $ticket = (new Ticket())->where('id', $id)->first();

        if ($ticket === null) {
            return ResponseHelper::error($response, '工单不存在');
        }

        $content_old = json_decode($ticket->content, true);

        if (count($content_old) === 1) {
            $context = [
                [
                    'role' => 'user',
                    'content' => $ticket->title,
                ],
                [
                    'role' => 'user',
                    'content' => $content_old[0]['comment'],
                ],
            ];
        } else {
            $context = [
                [
                    'role' => 'user',
                    'content' => $ticket->title,
                ],
            ];

            foreach ($content_old as $comment) {
                $commenterType = $comment['commenter_type'] ?? null;
                $isUserComment = $commenterType !== null
                    ? $commenterType === 'user'
                    : ($comment['commenter_name'] ?? '') !== 'Admin';
                $context[] = [
                    'role' => $isUserComment ? 'user' : 'admin',
                    'content' => $comment['comment'],
                ];
            }
        }

        $llm_response = LLM::genTextResponseWithContext($context);

        $ticket = (new TicketReplyService())->append(
            (int) $id,
            'llm',
            'AI Assistant',
            $this->antiXss->xss_clean((string) $llm_response),
            'open_wait_user'
        );

        if ($ticket === null) {
            return ResponseHelper::error($response, '工单不存在');
        }
        $ticketUser = (new User())->find($ticket->userid);

        if ($ticketUser === null) {
            return $response->withHeader('HX-Refresh', 'true');
        }

        try {
            Notification::notifyUser(
                $ticketUser,
                $_ENV['appName'] . '-工单被回复',
                '你好，AI助理回复了<a href="' . $_ENV['baseUrl'] . '/user/ticket/' . $ticket->id . '/view">工单</a>，请你查看。'
            );
        } catch (TelegramSDKException|GuzzleException|ClientExceptionInterface $e) {
            return $response->withHeader('HX-Refresh', 'true');
        }

        return $response->withHeader('HX-Refresh', 'true');
    }

    /**
     * 后台查看指定工单
     *
     * @throws Exception
     */
    public function detail(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $ticket = (new Ticket())->where('id', '=', $id)->first();

        if ($ticket === null) {
            return $response->withRedirect('/admin/ticket');
        }

        $comments = json_decode($ticket->content);

        foreach ($comments as $comment) {
            $comment->comment = nl2br($comment->comment);
            $comment->datetime = Tools::toDateTime((int) $comment->datetime);
        }

        return $response->write(
            $this->view()
                ->assign('ticket', $ticket)
                ->assign('comments', $comments)
                ->fetch('admin/ticket/view.tpl')
        );
    }

    /**
     * 后台关闭工单
     */
    public function close(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $ticket = (new Ticket())->where('id', '=', $id)->first();

        if ($ticket === null) {
            return ResponseHelper::error($response, '工单不存在');
        }

        if ($ticket->status === 'closed') {
            return ResponseHelper::error($response, '工单已关闭，无需重复操作');
        }

        $ticket->status = 'closed';
        $ticket->save();

        return ResponseHelper::success($response, '工单关闭成功');
    }

    /**
     * 后台删除工单
     */
    public function delete(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        (new Ticket())->where('id', '=', $id)->delete();

        return ResponseHelper::success($response, '工单删除成功');
    }

    /**
     * 后台工单页面 Ajax
     */
    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $table = DataTableRequest::from(
            $request,
            ['id', 'title', 'type', 'status', 'userid', 'datetime'],
            'id'
        );
        $query = Ticket::query();
        $total = (new Ticket())->count();
        if ($table->search !== '') {
            $query->where(static function ($query) use ($table): void {
                $query->where('id', $table->search)
                    ->orWhere('userid', $table->search)
                    ->orWhere('title', 'LIKE', "%{$table->search}%")
                    ->orWhere('status', 'LIKE', "%{$table->search}%");
            });
        }
        $filtered = $query->count();
        $query->orderBy($table->orderBy, $table->orderDirection);
        if ($table->orderBy !== 'id') {
            $query->orderBy('id', 'desc');
        }
        $tickets = $query->paginate($table->length, '*', '', $table->page);
        $canMutate = AdminPermissionService::allows($this->user, 'DELETE', '/admin/ticket/1');

        foreach ($tickets as $ticket) {
            $ticket->op = $canMutate
                ? '<button class="btn btn-red" id="delete-ticket"
            onclick="deleteTicket(' . $ticket->id . ')">删除</button>'
                : '';

            if ($canMutate && $ticket->status !== 'closed') {
                $ticket->op .= '
                <button class="btn btn-orange" id="close-ticket" 
                onclick="closeTicket(' . $ticket->id . ')">关闭</button>';
            }

            $ticket->op .= '
            <a class="btn btn-primary" href="/admin/ticket/' . $ticket->id . '/view">查看</a>';
            $ticket->status = $ticket->status();
            $ticket->type = $ticket->type();
            $ticket->title = htmlspecialchars((string) $ticket->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $ticket->datetime = Tools::toDateTime((int) $ticket->datetime);
        }

        return $response->withJson([
            'draw' => $table->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'tickets' => $tickets,
        ]);
    }
}
