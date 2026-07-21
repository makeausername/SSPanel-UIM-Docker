<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Config;
use App\Models\User;
use App\Services\Cache;
use App\Services\Captcha;
use App\Services\OneTimeTokenService;
use App\Services\Password;
use App\Services\RateLimit;
use App\Utils\Hash;
use App\Utils\ResponseHelper;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use RedisException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Smarty\Exception;
use function strlen;
use function strtolower;

final class PasswordController extends BaseController
{
    /**
     * @throws Exception
     */
    public function reset(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $captcha = [];

        if (Config::obtain('enable_reset_password_captcha')) {
            $captcha = Captcha::generate();
        }

        return $response->write(
            $this->view()
                ->assign('captcha', $captcha)
                ->fetch('password/reset.tpl')
        );
    }

    public function handleReset(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (Config::obtain('enable_reset_password_captcha')) {
            $ret = Captcha::verify($request->getParams());

            if (! $ret) {
                return ResponseHelper::error($response, '系统无法接受你的验证结果，请刷新页面后重试');
            }
        }

        $email = strtolower($this->antiXss->xss_clean($request->getParam('email')));

        if ($email === '') {
            return ResponseHelper::error($response, '未填写邮箱');
        }

        if (! (new RateLimit())->checkRateLimit('email_request_ip', $request->getServerParam('REMOTE_ADDR')) ||
            ! (new RateLimit())->checkRateLimit('email_request_address', $email)
        ) {
            return ResponseHelper::error($response, '你的请求过于频繁，请稍后再试');
        }

        $user = (new User())->where('email', $email)->first();
        $msg = '如果你的账户存在于我们的数据库中，那么重置密码的链接将会发送到你账户所对应的邮箱';

        if ($user !== null) {
            try {
                Password::sendResetEmail($email);
            } catch (ClientExceptionInterface|RedisException) {
                $msg = '邮件发送失败';
            }
        }

        return ResponseHelper::success($response, $msg);
    }

    /**
     * @throws Exception
     */
    public function token(ServerRequest $request, Response $response, array $args)
    {
        $token = $this->antiXss->xss_clean($args['token']);
        $redis = (new Cache())->initRedis();

        try {
            $email = $redis->get('password_reset:' . $token);
        } catch (RedisException) {
            return $response->withStatus(302)->withHeader('Location', '/password/reset');
        }

        if (! $email) {
            return $response->withStatus(302)->withHeader('Location', '/password/reset');
        }

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/password/token')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withAddedHeader('Set-Cookie', self::resetTokenCookie($token));
    }

    /**
     * @throws Exception
     */
    public function tokenForm(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $token = $this->antiXss->xss_clean($request->getCookieParam('password_reset_token', ''));
        $redis = (new Cache())->initRedis();

        try {
            $email = $token === '' ? false : $redis->get('password_reset:' . $token);
        } catch (RedisException) {
            $email = false;
        }

        if (! $email) {
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/password/reset')
                ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
        }

        return $response
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->write($this->view()->fetch('password/token.tpl'));
    }

    private static function resetTokenCookie(string $token): string
    {
        $ttl = max(1, min((int) Config::obtain('email_password_reset_ttl'), 900));
        $cookie = 'password_reset_token=' . rawurlencode($token)
            . '; Max-Age=' . $ttl
            . '; Path=/password/token; HttpOnly; SameSite=Lax';

        if (str_starts_with((string) $_ENV['baseUrl'], 'https://')) {
            $cookie .= '; Secure';
        }

        return $cookie;
    }

    private static function clearResetTokenCookie(): string
    {
        $cookie = 'password_reset_token=; Max-Age=0; Path=/password/token; HttpOnly; SameSite=Lax';

        if (str_starts_with((string) $_ENV['baseUrl'], 'https://')) {
            $cookie .= '; Secure';
        }

        return $cookie;
    }

    public function handleToken(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $token = $this->antiXss->xss_clean($request->getCookieParam('password_reset_token', ''));
        $password = $request->getParam('password');
        $confirm_password = $request->getParam('confirm_password');

        if ($password !== $confirm_password) {
            return ResponseHelper::error($response, '两次输入不符合');
        }

        if (strlen($password) < 8) {
            return ResponseHelper::error($response, '密码过短');
        }

        $redis = (new Cache())->initRedis();

        try {
            $email = OneTimeTokenService::consume($redis, 'password_reset:' . $token);
        } catch (RedisException) {
            return ResponseHelper::error($response, '链接无效')
                ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
        }

        if (! $email) {
            return ResponseHelper::error($response, '链接无效')
                ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
        }

        $user = (new User())->where('email', $email)->first();

        if ($user === null) {
            return ResponseHelper::error($response, '链接无效')
                ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
        }
        // reset password
        $hashPassword = Hash::passwordHash($password);
        $user->pass = $hashPassword;

        if (! $user->save()) {
            return ResponseHelper::error($response, '重置失败，请重试')
                ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
        }

        if (Config::obtain('enable_forced_replacement')) {
            $user->removeLink();
        }

        return ResponseHelper::success($response, '重置成功')
            ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
    }
}
