<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Config;
use App\Models\User;
use App\Services\Cache;
use App\Services\ClientSessionService;
use App\Services\Captcha;
use App\Services\FrontendI18n;
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
                return ResponseHelper::error($response, FrontendI18n::trans('response.auth.captcha_invalid'));
            }
        }

        $email = strtolower($this->antiXss->xss_clean($request->getParam('email')));

        if ($email === '') {
            return ResponseHelper::error($response, FrontendI18n::trans('response.email_required'));
        }

        if (! (new RateLimit())->checkRateLimit('email_request_ip', $request->getServerParam('REMOTE_ADDR')) ||
            ! (new RateLimit())->checkRateLimit('email_request_address', $email)
        ) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.too_many_requests'));
        }

        $user = (new User())->where('email', $email)->first();
        $msg = FrontendI18n::trans('response.auth.password_reset_email');

        if ($user !== null) {
            try {
                Password::sendResetEmail($email);
            } catch (ClientExceptionInterface | RedisException) {
                // Keep the same outward response to avoid disclosing account existence.
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
            return ResponseHelper::error($response, FrontendI18n::trans('response.password_mismatch'));
        }

        if (strlen($password) < 8) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.password_too_short'));
        }

        $redis = (new Cache())->initRedis();

        try {
            $email = OneTimeTokenService::consume($redis, 'password_reset:' . $token);
        } catch (RedisException) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.reset_link_invalid'))
                ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
        }

        if (! $email) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.reset_link_invalid'))
                ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
        }

        $user = (new User())->where('email', $email)->first();

        if ($user === null) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.reset_link_invalid'))
                ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
        }
        // reset password
        $hashPassword = Hash::passwordHash($password);
        $user->pass = $hashPassword;

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.reset_failed'))
                ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
        }

        (new ClientSessionService())->revokeAllForUser((int) $user->id);

        if (Config::obtain('enable_forced_replacement')) {
            $user->removeLink();
        }

        return ResponseHelper::success($response, FrontendI18n::trans('response.reset_success'))
            ->withAddedHeader('Set-Cookie', self::clearResetTokenCookie());
    }
}
