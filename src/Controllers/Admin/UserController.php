<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AuthController;
use App\Controllers\BaseController;
use App\Models\Config;
use App\Models\User;
use App\Models\UserMoneyLog;
use App\Services\AdminPermissionService;
use App\Services\ClientSessionService;
use App\Services\DB;
use App\Services\DataTableRequest;
use App\Services\I18n;
use App\Services\InvoiceAccountingService;
use App\Services\UserPortService;
use App\Utils\Hash;
use App\Utils\Tools;
use Exception;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function htmlspecialchars;
use function is_numeric;
use function in_array;
use function strlen;
use function trim;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

final class UserController extends BaseController
{
    private static array $details = [
        'field' => [
            'op' => '操作',
            'id' => '用户ID',
            'user_name' => '昵称',
            'email' => '邮箱',
            'money' => '余额',
            'ref_by' => '邀请人',
            'transfer_enable' => '流量限制',
            'transfer_used' => '当期用量',
            'class' => '等级',
            'is_admin' => '是否管理员',
            'is_banned' => '是否封禁',
            'is_inactive' => '是否闲置',
            'reg_date' => '注册时间',
            'class_expire' => '等级过期',
        ],
        'create_dialog' => [
            [
                'id' => 'email',
                'info' => '登录邮箱',
                'type' => 'input',
                'placeholder' => '',
            ],
            [
                'id' => 'password',
                'info' => '登录密码',
                'type' => 'input',
                'placeholder' => '留空则随机生成',
            ],
            [
                'id' => 'ref_by',
                'info' => '邀请人',
                'type' => 'input',
                'placeholder' => '邀请人的用户id，可留空',
            ],
            [
                'id' => 'balance',
                'info' => '账户余额',
                'type' => 'input',
                'placeholder' => '-1为按默认设置，其他为指定值',
            ],
        ],
    ];

    private static array $update_field = [
        'email',
        'user_name',
        'pass',
        'money',
        'ref_by',
        'port',
        'method',
        'transfer_enable',
        'node_group',
        'class',
        'class_expire',
        'auto_reset_day',
        'auto_reset_bandwidth',
        'node_speedlimit',
        'node_iplimit',
        'locale',
        'banned_reason',
        'remark',
    ];

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/user/index.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function create(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $email = trim((string) $request->getParam('email'));
        $refByRaw = trim((string) $request->getParam('ref_by'));
        $password = (string) $request->getParam('password');
        $balance = trim((string) $request->getParam('balance'));

        if (! Tools::isEmail($email)) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '邮箱格式无效',
            ]);
        }

        $exist = (new User())->where('email', $email)->first();

        if ($exist !== null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '邮箱已存在',
            ]);
        }

        if ($password !== '' && strlen($password) < 8) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '密码长度不能少于 8 位',
            ]);
        }

        if ($balance !== '' && $balance !== '-1' && (! is_numeric($balance) || (float) $balance < 0)) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '账户余额无效',
            ]);
        }

        $refBy = 0;
        if ($refByRaw !== '') {
            if (! ctype_digit($refByRaw) || (int) $refByRaw <= 0) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => '邀请人用户 ID 无效',
                ]);
            }

            $refBy = (int) $refByRaw;
            if (! (new User())->where('id', $refBy)->exists()) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => '邀请人用户不存在',
                ]);
            }
        }

        if ($password === '') {
            $password = Tools::genRandomChar(16);
        }

        try {
            $result = DB::connection()->transaction(static function () use (
                $response,
                $email,
                $password,
                $balance,
                $refBy
            ): array {
                (new AuthController())->registerHelper(
                    $response,
                    'user',
                    $email,
                    $password,
                    '',
                    0,
                    '',
                    $balance === '' || $balance === '-1' ? 0 : $balance,
                    1
                );

                $user = (new User())->where('email', $email)->lockForUpdate()->first();
                if ($user === null) {
                    return ['ret' => 0, 'msg' => '用户创建失败，请检查配置后重试'];
                }

                if ($refBy > 0) {
                    $user->ref_by = $refBy;
                    if (! $user->save()) {
                        throw new \RuntimeException('Unable to save admin-created user referrer.');
                    }
                }

                return [
                    'ret' => 1,
                    'msg' => '添加成功，用户邮箱：' . $email . ' 密码：' . $password,
                ];
            });
        } catch (QueryException) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '用户创建失败，请检查邮箱是否已存在',
            ]);
        }

        return $response->withJson($result);
    }

    /**
     * @throws Exception
     */
    public function edit(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = (new User())->find($args['id']);

        if ($user === null) {
            return $response->withRedirect('/admin/user');
        }

        $user->last_use_time = Tools::toDateTime($user->last_use_time);
        $user->last_check_in_time = Tools::toDateTime($user->last_check_in_time);
        $user->last_login_time = Tools::toDateTime($user->last_login_time);

        return $response->write(
            $this->view()
                ->assign('update_field', self::$update_field)
                ->assign('edit_user', $user)
                ->assign('ss_methods', Tools::getSsMethod())
                ->assign('locales', I18n::getLocaleList())
                ->assign('admin_roles', AdminPermissionService::ROLES)
                ->fetch('admin/user/edit.tpl')
        );
    }

    public function update(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $actor = $this->user;
        $actorId = (int) $actor->id;
        $actorIsOwner = AdminPermissionService::isOwner($actor);
        $requestedIsAdmin = $request->getParam('is_admin') === 'true';
        $requestedRole = (string) $request->getParam('admin_role', 'administrator');
        if (! in_array($requestedRole, AdminPermissionService::ROLES, true)) {
            $requestedRole = 'administrator';
        }

        $result = DB::connection()->transaction(static function () use (
            $id,
            $request,
            $actorId,
            $actorIsOwner,
            $requestedIsAdmin,
            $requestedRole
        ): array {
            $user = (new User())->where('id', $id)->lockForUpdate()->first();

            if ($user === null) {
                return ['ret' => 0, 'msg' => '用户不存在'];
            }

            $actor = new User();
            $actor->id = $actorId;
            $actor->admin_role = $actorIsOwner ? 'owner' : 'administrator';
            if (! AdminPermissionService::canUpdateUser($actor, $user)) {
                return ['ret' => 0, 'msg' => '只有所有者可以修改其他管理员账号'];
            }

            $requestedPort = filter_var($request->getParam('port'), FILTER_VALIDATE_INT);
            if ($requestedPort === false || ! UserPortService::isAvailableForUser($requestedPort, $id)) {
                return ['ret' => 0, 'msg' => '端口必须位于配置范围内且未被其他用户占用'];
            }

            $passwordChanged = $request->getParam('pass') !== '' && $request->getParam('pass') !== null;
            if ($passwordChanged) {
                $user->pass = Hash::passwordHash($request->getParam('pass'));

                if (Config::obtain('enable_forced_replacement')) {
                    $user->removeLink();
                }
            }

            $currentIsAdmin = (int) $user->is_admin === 1;
            $currentRole = AdminPermissionService::role($user);
            $privilegeChanged = $currentIsAdmin !== $requestedIsAdmin
                || ($requestedIsAdmin && $currentRole !== $requestedRole);

            if ($privilegeChanged && ! $actorIsOwner) {
                return ['ret' => 0, 'msg' => '只有所有者可以修改管理员权限'];
            }

            $requestedBanned = $request->getParam('is_banned') === 'true';
            $removesActiveOwner = $currentIsAdmin
                && $currentRole === 'owner'
                && (! $requestedIsAdmin || $requestedRole !== 'owner' || $requestedBanned);
            if ($removesActiveOwner) {
                $otherOwners = (new User())
                    ->where('id', '!=', $id)
                    ->where('is_admin', 1)
                    ->where('is_banned', 0)
                    ->where('admin_role', 'owner')
                    ->lockForUpdate()
                    ->get()
                    ->count();

                if ($otherOwners === 0) {
                    return ['ret' => 0, 'msg' => '不能停用最后一个有效所有者'];
                }
            }

            $moneyChange = null;
            if ($request->getParam('money') !== '' && $request->getParam('money') !== null) {
                $moneyBefore = InvoiceAccountingService::money($user->money);
                $moneyAfter = InvoiceAccountingService::money($request->getParam('money'));

                if (bccomp($moneyBefore, $moneyAfter, 2) !== 0) {
                    $diff = bcsub($moneyAfter, $moneyBefore, 2);
                    $moneyChange = [$moneyBefore, $moneyAfter, $diff];
                    $user->money = $moneyAfter;
                }
            }

            $user->email = $request->getParam('email');
            $user->user_name = $request->getParam('user_name');
            $user->ref_by = $request->getParam('ref_by');
            $user->port = $requestedPort;
            $user->method = $request->getParam('method');
            $user->transfer_enable = Tools::autoBytesR($request->getParam('transfer_enable'));
            $user->node_group = $request->getParam('node_group');
            $user->class = $request->getParam('class');
            $user->class_expire = $request->getParam('class_expire');
            $user->auto_reset_day = $request->getParam('auto_reset_day');
            $user->auto_reset_bandwidth = $request->getParam('auto_reset_bandwidth');
            $user->node_speedlimit = $request->getParam('node_speedlimit');
            $user->node_iplimit = $request->getParam('node_iplimit');
            $user->locale = $request->getParam('locale');
            $user->is_admin = $requestedIsAdmin ? 1 : 0;
            $user->admin_role = $requestedIsAdmin ? $requestedRole : null;
            $user->is_shadow_banned = $request->getParam('is_shadow_banned') === 'true' ? 1 : 0;
            $user->is_banned = $requestedBanned ? 1 : 0;
            $user->banned_reason = $request->getParam('banned_reason');
            $user->remark = $request->getParam('remark');

            if (! $user->save()) {
                throw new \RuntimeException('User update failed.');
            }

            if ($passwordChanged || $requestedBanned) {
                (new ClientSessionService())->revokeAllForUser($id);
            }

            if ($moneyChange !== null) {
                [$moneyBefore, $moneyAfter, $diff] = $moneyChange;
                (new UserMoneyLog())->add(
                    $id,
                    (float) $moneyBefore,
                    (float) $moneyAfter,
                    (float) $diff,
                    bccomp($diff, '0.00', 2) > 0 ? '管理员添加余额' : '管理员扣除余额'
                );
            }

            return ['ret' => 1, 'msg' => '修改成功'];
        });

        return $response->withJson($result);
    }

    public function delete(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = (int) $args['id'];
        $actorId = (int) $this->user->id;
        $actorIsOwner = AdminPermissionService::isOwner($this->user);

        $result = DB::connection()->transaction(static function () use ($id, $actorId, $actorIsOwner): array {
            if ($actorId === $id) {
                return ['ret' => 0, 'msg' => '不能删除当前登录的管理员'];
            }

            $user = (new User())->where('id', $id)->lockForUpdate()->first();
            if ($user === null) {
                return ['ret' => 0, 'msg' => '删除失败'];
            }

            if ((int) $user->is_admin === 1) {
                if (! $actorIsOwner) {
                    return ['ret' => 0, 'msg' => '只有所有者可以删除管理员'];
                }

                if (AdminPermissionService::role($user) === 'owner') {
                    $otherOwners = (new User())
                        ->where('id', '!=', $id)
                        ->where('is_admin', 1)
                        ->where('is_banned', 0)
                        ->where('admin_role', 'owner')
                        ->lockForUpdate()
                        ->get()
                        ->count();

                    if ($otherOwners === 0) {
                        return ['ret' => 0, 'msg' => '不能删除最后一个有效所有者'];
                    }
                }
            }

            return $user->kill()
                ? ['ret' => 1, 'msg' => '删除成功']
                : ['ret' => 0, 'msg' => '删除失败'];
        });

        return $response->withJson($result);
    }

    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $table = DataTableRequest::from(
            $request,
            ['id', 'user_name', 'email', 'money', 'ref_by', 'class', 'is_admin', 'is_banned', 'is_inactive', 'reg_date', 'class_expire'],
            'id'
        );
        $query = User::query();
        $total = (new User())->count();
        if ($table->search !== '') {
            $query->where(static function ($query) use ($table): void {
                $query->where('id', $table->search)
                    ->orWhere('user_name', 'LIKE', "%{$table->search}%")
                    ->orWhere('email', 'LIKE', "%{$table->search}%")
                    ->orWhere('remark', 'LIKE', "%{$table->search}%");
            });
        }
        $filtered = $query->count();
        $query->orderBy($table->orderBy, $table->orderDirection);
        if ($table->orderBy !== 'id') {
            $query->orderBy('id', 'desc');
        }
        $users = $query->paginate($table->length, '*', '', $table->page);

        $canMutate = AdminPermissionService::allows($this->user, 'PUT', '/admin/user/1');

        $users->getCollection()->transform(static function (User $user) use ($canMutate): array {
            $user->op = $canMutate ? '<button class="btn btn-red" id="delete-user-' . $user->id . '"
            onclick="deleteUser(' . $user->id . ')">删除</button>
            <a class="btn btn-primary" href="/admin/user/' . $user->id . '/edit">编辑</a>' : '';
            $user->transfer_enable = $user->enableTraffic();
            $user->transfer_used = $user->usedTraffic();
            $user->user_name = htmlspecialchars(
                (string) $user->user_name,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
            $user->email = htmlspecialchars((string) $user->email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $user->is_admin = $user->is_admin === 1 ? '是' : '否';
            $user->is_banned = $user->is_banned === 1 ? '是' : '否';
            $user->is_inactive = $user->is_inactive === 1 ? '是' : '否';

            return $user->only([
                'op',
                'id',
                'user_name',
                'email',
                'money',
                'ref_by',
                'transfer_enable',
                'transfer_used',
                'class',
                'is_admin',
                'is_banned',
                'is_inactive',
                'reg_date',
                'class_expire',
            ]);
        });

        return $response->withJson([
            'draw' => $table->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'users' => $users,
        ]);
    }
}
