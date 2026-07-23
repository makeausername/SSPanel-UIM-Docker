<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Config;
use App\Models\InviteCode;
use App\Models\LoginIp;
use App\Models\User;
use App\Services\Auth;
use App\Services\Cache;
use App\Services\Captcha;
use App\Services\Filter;
use App\Services\FrontendI18n;
use App\Services\InviteSubscriptionRewardService;
use App\Services\Locale;
use App\Services\Mail;
use App\Services\MFA\FIDO;
use App\Services\MFA\TOTP;
use App\Services\MFA\WebAuthn;
use App\Services\OneTimeTokenService;
use App\Services\RateLimit;
use App\Services\UserAccessPolicy;
use App\Utils\Cookie;
use App\Utils\Hash;
use App\Utils\ResponseHelper;
use App\Utils\Tools;
use Exception;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use RedisException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Throwable;
use function array_rand;
use function date;
use function explode;
use function hash_equals;
use function is_string;
use function strlen;
use function strtolower;
use function time;
use function trim;

final class AuthController extends BaseController
{
    /**
     * @throws Exception
     */
    public function login(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $captcha = [];

        if (Config::obtain('enable_login_captcha')) {
            $captcha = Captcha::generate();
        }

        return $response->write($this->view()
            ->assign('base_url', $_ENV['baseUrl'])
            ->assign('captcha', $captcha)
            ->fetch('auth/login.tpl'));
    }

    public function loginHandle(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->loginRateAllowed('login_ip', (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown'))) {
            return $response->withStatus(429)->withHeader('Retry-After', '60')->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.login_rate_limited'),
            ]);
        }

        if (Config::obtain('enable_login_captcha') && ! Captcha::verify($request->getParams())) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.captcha_invalid'),
            ]);
        }

        $password = $request->getParam('password');
        $rememberMe = $request->getParam('remember_me') === 'true' ? 1 : 0;
        $email = strtolower(trim($this->antiXss->xss_clean($request->getParam('email'))));
        if (! $this->loginRateAllowed('login_account', hash('sha256', $email))) {
            return $response->withStatus(429)->withHeader('Retry-After', '60')->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.login_rate_limited'),
            ]);
        }
        $redir = $this->redirectTarget(Cookie::get('redir'));
        $user = (new User())->where('email', $email)->first();
        $loginIp = new LoginIp();

        if ($user === null) {
            $loginIp->collectLoginIP($_SERVER['REMOTE_ADDR'], 1);

            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.credentials_invalid'),
            ]);
        }

        if (! Hash::checkPassword($user->pass, $password)) {
            $loginIp->collectLoginIP($_SERVER['REMOTE_ADDR'], 1, $user->id);

            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.credentials_invalid'),
            ]);
        }

        $mfaStatus = $user->checkMfaStatus();
        if ($mfaStatus['require']) {
            $redis = (new Cache())->initRedis();
            $redis->setex('mfa_login_' . session_id(), 300, json_encode([
                'userid' => $user->id,
                'method' => $mfaStatus,
                'redir' => $redir,
                'remember_me' => $rememberMe,
            ]));

            return $response
                ->withHeader('HX-Redirect', '/auth/mfa')
                ->withJson([
                    'ret' => 1,
                    'msg' => FrontendI18n::trans('response.auth.mfa_complete'),
                ]);
        }

        $time = $rememberMe ? 86400 * ($_ENV['rememberMeDuration'] ?? 7) : 3600; // Cookie 过期时间

        Auth::login($user->id, $time);
        // 记录登录成功
        $loginIp->collectLoginIP($_SERVER['REMOTE_ADDR'], 0, $user->id);
        $user->last_login_time = time();
        $user->save();

        return $response->withHeader('HX-Redirect', $redir);
    }

    public function mfaPage(ServerRequest $request, Response $response, $next): ResponseInterface
    {
        $redis = (new Cache())->initRedis();
        $mfa_session = $redis->get('mfa_login_' . session_id());
        if ($mfa_session === false) {
            return $response->withStatus(302)->withHeader('Location', '/auth/login');
        }
        $mfa_session = json_decode($mfa_session, true);
        return $response->write(
            $this->view()
                ->assign('base_url', $_ENV['baseUrl'])
                ->assign('method', $mfa_session['method'])
                ->fetch('auth/mfa.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function register(ServerRequest $request, Response $response, $next): ResponseInterface
    {
        $captcha = [];

        if (Config::obtain('enable_reg_captcha')) {
            $captcha = Captcha::generate();
        }

        $invite_code = $this->antiXss->xss_clean($request->getParam('code'));

        return $response->write(
            $this->view()
                ->assign('invite_code', $invite_code)
                ->assign('base_url', $_ENV['baseUrl'])
                ->assign('captcha', $captcha)
                ->fetch('auth/register.tpl')
        );
    }

    /**
     * @throws RedisException
     */
    public function sendVerify(ServerRequest $request, Response $response, $next): ResponseInterface
    {
        if (Config::obtain('reg_email_verify')) {
            $email = strtolower(trim($this->antiXss->xss_clean($request->getParam('email'))));

            if ($email === '') {
                return ResponseHelper::error($response, FrontendI18n::trans('response.email_required'));
            }

            // check email format
            $email_check = Filter::checkEmailFilter($email);

            if (! $email_check) {
                return ResponseHelper::error($response, FrontendI18n::trans('response.email_invalid'));
            }

            if (! (new RateLimit())->checkRateLimit('email_request_ip', $request->getServerParam('REMOTE_ADDR')) ||
                ! (new RateLimit())->checkRateLimit('email_request_address', $email)
            ) {
                return ResponseHelper::error($response, FrontendI18n::trans('response.too_many_requests'));
            }

            $user = (new User())->where('email', $email)->first();

            if ($user !== null) {
                return ResponseHelper::error($response, FrontendI18n::trans('response.auth.email_already_registered'));
            }

            $redis = (new Cache())->initRedis();
            $email_code = OneTimeTokenService::issueEmailCode(
                $redis,
                $email,
                (int) Config::obtain('email_verify_code_ttl')
            );

            try {
                Mail::send(
                    $email,
                    FrontendI18n::trans('response.auth.verification_email_subject', [
                        '%app%' => $_ENV['appName'],
                    ]),
                    'verify_code.tpl',
                    [
                        'code' => $email_code,
                        'expire' => date('Y-m-d H:i:s', time() + Config::obtain('email_verify_code_ttl')),
                    ]
                );
            } catch (Exception|ClientExceptionInterface) {
                OneTimeTokenService::consume($redis, 'email_verify:' . $email_code);
                return ResponseHelper::error($response, FrontendI18n::trans('response.auth.email_send_failed'));
            }

            return ResponseHelper::success($response, FrontendI18n::trans('response.auth.email_verification_sent'));
        }

        return ResponseHelper::error($response, FrontendI18n::trans('response.auth.email_verification_disabled'));
    }

    /**
     * @throws Exception
     */
    public function registerHelper(
        Response $response,
        $name,
        $email,
        $password,
        $invite_code,
        $imtype,
        $imvalue,
        $money,
        $is_admin_reg
    ): ResponseInterface {
        $redir = $this->redirectTarget(Cookie::get('redir'));
        $configs = Config::getClass('reg');
        // do reg user
        $user = new User();

        $user->user_name = $name;
        $user->email = $email;
        $user->remark = '';
        $user->pass = Hash::passwordHash($password);
        $user->passwd = Tools::genRandomChar(16);
        $user->uuid = Uuid::uuid4();
        $user->api_token = Tools::genRandomChar(32);
        $user->port = Tools::getSsPort();
        $user->u = 0;
        $user->d = 0;
        $user->method = $configs['reg_method'];
        $user->im_type = $imtype;
        $user->im_value = $imvalue;
        $user->transfer_enable = Tools::gbToB($configs['reg_traffic']);
        $user->auto_reset_day = Config::obtain('free_user_reset_day');
        $user->auto_reset_bandwidth = Config::obtain('free_user_reset_bandwidth');
        $user->daily_mail_enable = $configs['reg_daily_report'];

        if ($money > 0) {
            $user->money = $money;
        } else {
            $user->money = 0;
        }

        $user->ref_by = 0;

        if ($invite_code !== '') {
            $invite = (new InviteCode())->where('code', $invite_code)->first();

            if ($invite !== null) {
                $user->ref_by = $invite->user_id;
            }
        }

        $user->class = $configs['reg_class'];
        $user->class_expire = date('Y-m-d H:i:s', time() + (int) $configs['reg_class_time'] * 86400);
        $user->node_iplimit = $configs['reg_ip_limit'];
        $user->node_speedlimit = $configs['reg_speed_limit'];
        $user->reg_date = date('Y-m-d H:i:s');
        UserAccessPolicy::applyRegistrationPolicy($user, (bool) $is_admin_reg);
        $user->reg_ip = $_SERVER['REMOTE_ADDR'];
        $user->theme = $_ENV['theme'];
        $user->locale = $_ENV['locale'];
        $random_group = Config::obtain('random_group');

        if ($random_group === '') {
            $user->node_group = 0;
        } else {
            $user->node_group = $random_group[array_rand(explode(',', $random_group))];
        }

        $user->last_login_time = time();

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.unknown_error'));
        }

        if ($is_admin_reg) {
            return ResponseHelper::success($response, '');
        }

        if ($user->ref_by !== 0) {
            InviteSubscriptionRewardService::bindReferral(
                (int) $user->id,
                (int) $user->ref_by,
                (string) $invite_code
            );
        }

        Auth::login($user->id, 3600);
        (new LoginIp())->collectLoginIP($_SERVER['REMOTE_ADDR'], 0, $user->id);

        return $response->withHeader('HX-Redirect', $redir);
    }

    /**
     * @throws RedisException
     * @throws Exception
     */
    public function registerHandle(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (Config::obtain('reg_mode') === 'close') {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.registration_closed'));
        }

        if (Config::obtain('enable_reg_captcha') && ! Captcha::verify($request->getParams())) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.captcha_invalid'));
        }

        $tos = $request->getParam('tos') === 'true' ? 1 : 0;
        $email = strtolower(trim($this->antiXss->xss_clean($request->getParam('email'))));
        $name = $this->antiXss->xss_clean($request->getParam('name'));
        $password = $request->getParam('password');
        $confirm_password = $request->getParam('confirm_password');
        $invite_code = $this->antiXss->xss_clean(trim($request->getParam('invite_code')));

        if (! $this->loginRateAllowed(
            'register_ip',
            (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown')
        ) || ! $this->loginRateAllowed('register_account', hash('sha256', $email))) {
            return ResponseHelper::error(
                $response,
                FrontendI18n::trans('response.registration_rate_limited'),
                429
            );
        }

        if (! $tos) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.terms_required'));
        }

        if (strlen($password) < 8) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.password_too_short'));
        }

        if ($password !== $confirm_password) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.password_mismatch'));
        }

        if ($invite_code === '' && Config::obtain('reg_mode') === 'invite') {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.invite_required'));
        }

        if ($invite_code !== '') {
            $invite = (new InviteCode())->where('code', $invite_code)->first();

            if ($invite === null) {
                return ResponseHelper::error($response, FrontendI18n::trans('response.auth.invite_invalid'));
            }

            $ref_user = (new User())->where('id', $invite->user_id)->first();

            if ($ref_user === null) {
                return ResponseHelper::error($response, FrontendI18n::trans('response.auth.invite_invalid'));
            }
        }

        $imtype = 0;
        $imvalue = '';

        // check email format
        $email_check = Filter::checkEmailFilter($email);

        if (! $email_check) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.email_invalid'));
        }
        // check email
        $user = (new User())->where('email', $email)->first();

        if ($user !== null) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.email_invalid'));
        }

        if (Config::obtain('reg_email_verify')) {
            $redis = (new Cache())->initRedis();
            $email_verify_code = trim($this->antiXss->xss_clean($request->getParam('emailcode')));
            $email_verify = OneTimeTokenService::consume($redis, 'email_verify:' . $email_verify_code);

            if (! is_string($email_verify) || ! hash_equals(strtolower(trim($email_verify)), $email)) {
                return ResponseHelper::error($response, FrontendI18n::trans('response.email_verification_invalid'));
            }
        }

        return $this->registerHelper($response, $name, $email, $password, $invite_code, $imtype, $imvalue, 0, 0);
    }

    public function logout(ServerRequest $request, Response $response, $next): Response
    {
        Auth::logout();

        return $response->withStatus(302)->withHeader('Location', '/auth/login');
    }

    public function webauthnRequest(ServerRequest $request, Response $response, $next): ResponseInterface
    {
        return $response->withJson(WebAuthn::AssertRequest());
    }

    public function webauthnHandle(ServerRequest $request, Response $response, $next): ResponseInterface
    {
        $data = $this->antiXss->xss_clean((array) $request->getParsedBody());
        $redir = $this->redirectTarget(Cookie::get('redir'));
        $result = WebAuthn::AssertHandle($data);
        if ($result['ret'] === 1) {
            $user = $result['user'];
            if ($user === null) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.auth.user_not_found'),
                ]);
            }
            $rememberMe = $request->getParam('remember_me') === 'true';
            $time = $rememberMe ? 86400 * ($_ENV['rememberMeDuration'] ?? 7) : 3600;
            Auth::login($user->id, $time);
            $loginIp = new LoginIp();
            $loginIp->collectLoginIP($_SERVER['REMOTE_ADDR'], 0, $user->id);
            $user->last_login_time = time();
            $user->save();
            return $response->withJson([
                'ret' => 1,
                'msg' => FrontendI18n::trans('response.auth.login_success'),
                'redir' => $redir,
            ]);
        }
        return $response->withJson($result);
    }

    public function totpHandle(ServerRequest $request, Response $response, $next): ResponseInterface
    {
        $redis = (new Cache())->initRedis();
        $login_session = $redis->get('mfa_login_' . session_id());
        if ($login_session === false) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.login_session_expired'),
            ])->withHeader('HX-Redirect', '/auth/login');
        }
        $login_session = json_decode($login_session, true);
        $code = $this->antiXss->xss_clean($request->getParam('code'));
        $user = (new User())->where('id', $login_session['userid'])->first();
        if ($user === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.user_not_found'),
            ])->withHeader('HX-Redirect', '/auth/login');
        }
        if (! $this->loginRateAllowed('login_account', hash('sha256', 'mfa:' . (int) $user->id))) {
            return $response->withStatus(429)->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.mfa_rate_limited'),
            ]);
        }
        $result = TOTP::AssertHandle($user, $code);
        if ($result['ret'] === 1) {
            $redis->del('mfa_login_' . session_id());
            $rememberMe = $login_session['remember_me'];
            $redir = $this->redirectTarget($login_session['redir'] ?? null);
            $time = $rememberMe ? 86400 * ($_ENV['rememberMeDuration'] ?? 7) : 3600;
            Auth::login($user->id, $time);
            $loginIp = new LoginIp();
            $loginIp->collectLoginIP($_SERVER['REMOTE_ADDR'], 0, $user->id);
            $user->last_login_time = time();
            $user->save();
            return $response
                ->withHeader('HX-Redirect', $redir)
                ->withJson(['ret' => 1, 'msg' => FrontendI18n::trans('response.auth.login_success')]);
        }
        return $response->withJson($result);
    }

    public function fidoRequest(ServerRequest $request, Response $response, $next): ResponseInterface
    {
        $redis = (new Cache())->initRedis();
        $login_session = $redis->get('mfa_login_' . session_id());
        if ($login_session === false) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.login_session_expired'),
            ])->withHeader('HX-Redirect', '/auth/login');
        }
        $login_session = json_decode($login_session, true);
        $user = (new User())->where('id', $login_session['userid'])->first();
        if ($user === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.user_not_found'),
            ])->withHeader('HX-Redirect', '/auth/login');
        }
        return $response->withJson(FIDO::AssertRequest($user));
    }

    public function fidoHandle(ServerRequest $request, Response $response, $next): ResponseInterface
    {
        $redis = (new Cache())->initRedis();
        $login_session = $redis->get('mfa_login_' . session_id());
        if ($login_session === false) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.login_session_expired'),
            ])->withHeader('HX-Redirect', '/auth/login');
        }
        $login_session = json_decode($login_session, true);
        $data = $this->antiXss->xss_clean((array) $request->getParsedBody());
        $user = (new User())->where('id', $login_session['userid'])->first();
        if ($user === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.auth.user_not_found'),
            ])->withHeader('HX-Redirect', '/auth/login');
        }
        $result = FIDO::AssertHandle($user, $data);
        if ($result['ret'] === 1) {
            $redis->del('mfa_login_' . session_id());
            $rememberMe = $login_session['remember_me'];
            $redir = $this->redirectTarget($login_session['redir'] ?? null);
            $time = $rememberMe ? 86400 * ($_ENV['rememberMeDuration'] ?? 7) : 3600;
            Auth::login($user->id, $time);
            $loginIp = new LoginIp();
            $loginIp->collectLoginIP($_SERVER['REMOTE_ADDR'], 0, $user->id);
            $user->last_login_time = time();
            $user->save();
            return $response->withJson([
                'ret' => 1,
                'msg' => FrontendI18n::trans('response.auth.login_success'),
                'redir' => $redir,
            ]);
        }
        return $response->withJson($result);
    }

    private function loginRateAllowed(string $type, string $value): bool
    {
        try {
            return (new RateLimit())->checkRateLimit($type, $value);
        } catch (Throwable) {
            return false;
        }
    }

    private function redirectTarget(mixed $target): string
    {
        if (! is_string($target)) {
            return '/user';
        }

        $target = $this->antiXss->xss_clean($target);

        if (! is_string($target)) {
            return '/user';
        }

        return Locale::sanitizeRedirect($target, $_SERVER['HTTP_HOST'] ?? '') ?? '/user';
    }
}
