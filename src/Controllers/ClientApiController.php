<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\ClientSessionService;
use App\Services\FrontendI18n;
use App\Services\MFA\TOTP;
use App\Services\RateLimit;
use App\Services\Subscribe;
use App\Services\UserAccessPolicy;
use App\Utils\Hash;
use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Throwable;
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
        try {
            $ipAllowed = (new RateLimit())->checkRateLimit(
                'login_ip',
                (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown')
            );
        } catch (Throwable) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.login_unavailable'), 503);
        }

        if (! $ipAllowed) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.login_rate_limited'), 429);
        }

        $email = strtolower(trim((string) $this->getBodyParam($request, 'email', '')));
        $password = (string) $this->getBodyParam($request, 'password', '');

        if ($email === '' || $password === '') {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.credentials_required'), 400);
        }

        try {
            $accountAllowed = (new RateLimit())->checkRateLimit('login_account', hash('sha256', $email));
        } catch (Throwable) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.login_unavailable'), 503);
        }

        if (! $accountAllowed) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.login_rate_limited'), 429);
        }

        $user = (new User())->where('email', $email)->first();

        if ($user === null || ! Hash::checkPassword($user->pass, $password)) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.credentials_invalid'), 401);
        }

        if ((int) $user->is_banned === 1) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.account_disabled'), 403);
        }

        $mfaStatus = $user->checkMfaStatus();
        if ($mfaStatus['require'] ?? false) {
            $mfaCode = trim((string) $this->getBodyParam($request, 'mfa_code', ''));
            if (! ($mfaStatus['totp'] ?? false)) {
                return $this->mfaError(
                    $response,
                    FrontendI18n::trans('response.client.mfa_interactive_required'),
                    'MFA_INTERACTIVE_REQUIRED'
                );
            }

            if ($mfaCode === '') {
                return $this->mfaError(
                    $response,
                    FrontendI18n::trans('response.client.mfa_required'),
                    'MFA_REQUIRED'
                );
            }

            $mfaResult = TOTP::AssertHandle($user, $mfaCode);
            if (($mfaResult['ret'] ?? 0) !== 1) {
                return $this->mfaError(
                    $response,
                    FrontendI18n::trans('response.client.mfa_invalid'),
                    'MFA_INVALID',
                    401
                );
            }
        }

        $session = (new ClientSessionService())->issue(
            (int) $user->id,
            trim((string) $this->getBodyParam($request, 'device_name', 'windows-client'))
        );

        return ResponseHelper::successWithData(
            $response,
            FrontendI18n::trans('response.client.login_success'),
            $this->buildClientPayload($user, $session['token'], $session['expires_at'])
        );
    }

    public function me(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->getUserByBearerToken($request);

        if ($user === null) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.session_expired'), 401);
        }

        if ((int) $user->is_banned === 1) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.account_disabled'), 403);
        }

        return ResponseHelper::successWithData(
            $response,
            FrontendI18n::trans('response.client.fetch_success'),
            $this->buildClientPayload($user)
        );
    }

    public function subscription(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->getUserByBearerToken($request);

        if ($user === null) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.session_expired'), 401);
        }

        if ((int) $user->is_banned === 1) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.client.account_disabled'), 403);
        }

        return ResponseHelper::successWithData($response, FrontendI18n::trans('response.client.fetch_success'), [
            'subscription' => $this->buildSubscriptionPayload($user),
            'usage' => $this->buildUsagePayload($user),
        ]);
    }

    public function logout(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        (new ClientSessionService())->revoke($this->bearerToken($request) ?? '');

        return ResponseHelper::success($response, FrontendI18n::trans('response.client.logout_success'));
    }

    private function buildClientPayload(User $user, ?string $accessToken = null, ?int $expiresAt = null): array
    {
        $payload = [
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

        if ($accessToken !== null) {
            $payload['accessToken'] = $accessToken;
            $payload['tokenType'] = 'Bearer';
            $payload['accessTokenExpiresAt'] = $expiresAt;
        }

        return $payload;
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
        $token = $this->bearerToken($request);

        return $token === null ? null : (new ClientSessionService())->authenticate($token);
    }

    private function bearerToken(ServerRequest $request): ?string
    {
        $authorization = $request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }

        $token = trim($matches[1]);

        return $token === '' ? null : $token;
    }

    private function mfaError(
        Response $response,
        string $message,
        string $code,
        int $status = 403
    ): ResponseInterface {
        return $response->withStatus($status)->withJson([
            'ret' => 0,
            'msg' => $message,
            'code' => $code,
        ]);
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
