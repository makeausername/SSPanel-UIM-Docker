<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\Subscribe;
use App\Services\UserAccessPolicy;
use App\Utils\Hash;
use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function array_key_exists;
use function is_array;
use function json_decode;
use function max;
use function min;
use function preg_match;
use function strtolower;
use function strtotime;
use function time;
use function trim;

final class ClientApiController extends BaseController
{
    public function login(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $email = strtolower(trim((string) $this->getBodyParam($request, 'email', '')));
        $password = (string) $this->getBodyParam($request, 'password', '');

        if ($email === '' || $password === '') {
            return ResponseHelper::error($response, '请输入邮箱和密码', 400);
        }

        $user = (new User())->where('email', $email)->first();

        if ($user === null || ! Hash::checkPassword($user->pass, $password)) {
            return ResponseHelper::error($response, '邮箱或者密码错误', 401);
        }

        if ((int) $user->is_banned === 1) {
            return ResponseHelper::error($response, '账号已被禁用，请联系客服', 403);
        }

        return ResponseHelper::successWithData($response, '登录成功', $this->buildClientPayload($user));
    }

    public function me(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->getUserByBearerToken($request);

        if ($user === null) {
            return ResponseHelper::error($response, '登录已失效，请重新登录', 401);
        }

        if ((int) $user->is_banned === 1) {
            return ResponseHelper::error($response, '账号已被禁用，请联系客服', 403);
        }

        return ResponseHelper::successWithData($response, '获取成功', $this->buildClientPayload($user));
    }

    public function subscription(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->getUserByBearerToken($request);

        if ($user === null) {
            return ResponseHelper::error($response, '登录已失效，请重新登录', 401);
        }

        if ((int) $user->is_banned === 1) {
            return ResponseHelper::error($response, '账号已被禁用，请联系客服', 403);
        }

        return ResponseHelper::successWithData($response, '获取成功', [
            'subscription' => $this->buildSubscriptionPayload($user),
            'usage' => $this->buildUsagePayload($user),
        ]);
    }

    public function logout(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return ResponseHelper::success($response, '已退出登录');
    }

    private function buildClientPayload(User $user): array
    {
        return [
            'accessToken' => (string) $user->api_token,
            'tokenType' => 'Bearer',
            'user' => [
                'id' => (int) $user->id,
                'email' => (string) $user->email,
                'userName' => (string) $user->user_name,
                'isBanned' => (int) $user->is_banned === 1,
                'isShadowBanned' => (int) $user->is_shadow_banned === 1,
                'class' => (int) $user->class,
                'classExpire' => (string) $user->class_expire,
            ],
            'usage' => $this->buildUsagePayload($user),
            'subscription' => $this->buildSubscriptionPayload($user),
        ];
    }

    private function buildSubscriptionPayload(User $user): array
    {
        return [
            'url' => Subscribe::getUniversalSubLink($user) . '/v2ray',
            'type' => 'v2ray',
            'updateIntervalHours' => 6,
        ];
    }

    private function buildUsagePayload(User $user): array
    {
        $upload = (int) $user->u;
        $download = (int) $user->d;
        $used = $upload + $download;
        $todayUsed = min(max((int) $user->transfer_today, 0), max($used, 0));
        $pastUsed = max($used - $todayUsed, 0);
        $total = (int) $user->transfer_enable;
        $remaining = max($total - $used, 0);
        $expireAt = (string) $user->class_expire;
        $expireTimestamp = strtotime($expireAt);

        $isAdmin = (int) $user->is_admin === 1;
        $isExpired = ! $isAdmin && $expireTimestamp !== false && $expireTimestamp > 0 && $expireTimestamp < time();
        $canConnect = UserAccessPolicy::canUseNodes($user);

        return [
            'upload' => $upload,
            'download' => $download,
            'used' => $used,
            'todayUsed' => $todayUsed,
            'pastUsed' => $pastUsed,
            'total' => $total,
            'remaining' => $remaining,
            'expireAt' => $expireAt,
            'expireTimestamp' => $expireTimestamp === false ? null : $expireTimestamp,
            'isExpired' => $isExpired,
            'isUnlimited' => $isAdmin,
            'canConnect' => $canConnect,
        ];
    }

    private function getUserByBearerToken(ServerRequest $request): ?User
    {
        $authorization = $request->getHeaderLine('Authorization');

        if ($authorization === '') {
            return null;
        }

        if (! preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        if ($token === '') {
            return null;
        }

        return (new User())->where('api_token', $token)->first();
    }

    private function getBodyParam(ServerRequest $request, string $key, mixed $default = null): mixed
    {
        $parsed = $request->getParsedBody();

        if (is_array($parsed) && array_key_exists($key, $parsed)) {
            return $parsed[$key];
        }

        $raw = (string) $request->getBody();

        if ($raw !== '') {
            $json = json_decode($raw, true);

            if (is_array($json) && array_key_exists($key, $json)) {
                return $json[$key];
            }
        }

        return $request->getParam($key, $default);
    }
}
