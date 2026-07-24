<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Ann;
use App\Models\Config;
use App\Models\EmailQueue;
use App\Models\User;
use App\Services\AdminPermissionService;
use App\Services\DataTableRequest;
use App\Services\Notification;
use App\Utils\Tools;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Telegram\Bot\Exceptions\TelegramSDKException;
use function htmlspecialchars;
use function in_array;
use function strip_tags;
use function strlen;
use function time;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const PHP_EOL;

final class AnnController extends BaseController
{
    private static array $details =
        [
            'field' => [
                'op' => '操作',
                'id' => 'ID',
                'status' => '状态',
                'sort' => '排序',
                'date' => '日期',
                'content' => '内容（节选）',
            ],
        ];

    private static array $update_field = [
        'status',
        'sort',
    ];

    /**
     * 后台公告页面
     *
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/announcement/index.tpl')
        );
    }

    /**
     * 后台公告创建页面
     *
     * @throws Exception
     */
    public function create(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('update_field', self::$update_field)
                ->fetch('admin/announcement/create.tpl')
        );
    }

    /**
     * 后台添加公告
     */
    public function add(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $status = (int) $request->getParam('status');
        $sort = (int) $request->getParam('sort');
        $email_notify_class = (int) $request->getParam('email_notify_class');
        $email_notify = $request->getParam('email_notify') === 'true' ? 1 : 0;
        $content = $request->getParam('content');

        if ($content === '') {
            return $response->withJson([
                'ret' => 0,
                'msg' => '内容不能为空',
            ]);
        }

        $ann = new Ann();
        $ann->status = in_array($status, [0, 1, 2]) ? $status : 1;
        $ann->sort = $sort > 999 || $sort < 0 ? 0 : $sort;
        $ann->date = Tools::toDateTime(time());
        $ann->content = $content;

        if (! $ann->save()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '公告保存失败',
            ]);
        }

        if ($email_notify) {
            $users = (new User())->where('class', '>=', $email_notify_class)
                ->where('is_banned', '=', 0)
                ->lazyById(500);
            $subject = $_ENV['appName'] . ' - 新公告发布';

            foreach ($users as $user) {
                (new EmailQueue())->add(
                    $user->email,
                    $subject,
                    'warn.tpl',
                    [
                        'user' => $user,
                        'text' => $content,
                    ]
                );
            }
        }

        if (Config::obtain('im_bot_group_notify_ann_create')) {
            $converter = new HtmlConverter(['strip_tags' => true]);
            $content = $converter->convert($content);

            try {
                Notification::notifyUserGroup('新公告：' . PHP_EOL . $content);
            } catch (TelegramSDKException | GuzzleException) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => $email_notify === 1 ? '公告添加成功，邮件发送成功，IM Bot 发送失败' : '公告添加成功，IM Bot 发送失败',
                ]);
            }
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => $email_notify === 1 ? '公告添加成功，邮件发送成功' : '公告添加成功',
        ]);
    }

    /**
     * 后台编辑公告页面
     *
     * @throws Exception
     */
    public function edit(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $announcement = (new Ann())->find($args['id']);

        if ($announcement === null) {
            return $response->withRedirect('/admin/announcement');
        }

        return $response->write(
            $this->view()
                ->assign('ann', $announcement)
                ->assign('update_field', self::$update_field)
                ->fetch('admin/announcement/edit.tpl')
        );
    }

    /**
     * 后台编辑公告提交
     */
    public function update(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $status = (int) $request->getParam('status');
        $sort = (int) $request->getParam('sort');
        $content = $request->getParam('content');

        if ($content === '') {
            return $response->withJson([
                'ret' => 0,
                'msg' => '内容不能为空',
            ]);
        }

        $ann = (new Ann())->find($args['id']);

        if ($ann === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '公告不存在',
            ]);
        }

        $ann->status = in_array($status, [0, 1, 2]) ? $status : 1;
        $ann->sort = $sort > 999 || $sort < 0 ? 0 : $sort;
        $ann->content = $content;
        $ann->date = Tools::toDateTime(time());

        if (! $ann->save()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '公告更新失败',
            ]);
        }

        if (Config::obtain('im_bot_group_notify_ann_update')) {
            $converter = new HtmlConverter(['strip_tags' => true]);
            $content = $converter->convert($ann->content);

            try {
                Notification::notifyUserGroup('公告更新：' . PHP_EOL . $content);
            } catch (TelegramSDKException | GuzzleException) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => '公告更新成功，IM Bot 发送失败',
                ]);
            }
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '公告更新成功',
        ]);
    }

    /**
     * 后台删除公告
     */
    public function delete(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $announcement = (new Ann())->find($args['id']);

        if ($announcement !== null && $announcement->delete()) {
            return $response->withJson([
                'ret' => 1,
                'msg' => '删除成功',
            ]);
        }

        return $response->withJson([
            'ret' => 0,
            'msg' => '删除失败',
        ]);
    }

    /**
     * 后台公告页面 AJAX
     */
    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $table = DataTableRequest::from($request, ['id', 'status', 'sort', 'date'], 'id');
        $query = Ann::query();
        $total = (new Ann())->count();
        if ($table->search !== '') {
            $query->where('content', 'LIKE', "%{$table->search}%");
        }
        $filtered = $query->count();
        $query->orderBy($table->orderBy, $table->orderDirection);
        $anns = $query->paginate($table->length, '*', '', $table->page);
        $canMutate = AdminPermissionService::allows($this->user, 'DELETE', '/admin/announcement/1');

        foreach ($anns as $ann) {
            $ann->op = $canMutate ? '<button class="btn btn-red" id="delete-announcement-' . $ann->id . '"
            onclick="deleteAnn(' . $ann->id . ')">删除</button>
            <a class="btn btn-primary" href="/admin/announcement/' . $ann->id . '/edit">编辑</a>' : '';
            $ann->status = $ann->status();
            $plainContent = strip_tags((string) $ann->content);
            $plainContent = strlen($plainContent) > 40
                ? mb_substr($plainContent, 0, 40, 'UTF-8') . '...'
                : $plainContent;
            $ann->content = htmlspecialchars($plainContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $response->withJson([
            'draw' => $table->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'anns' => $anns,
        ]);
    }
}
