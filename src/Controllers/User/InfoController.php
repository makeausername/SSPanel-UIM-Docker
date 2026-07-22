<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Config;
use App\Models\MFADevice;
use App\Models\User;
use App\Services\Auth;
use App\Services\Cache;
use App\Services\ClientSessionService;
use App\Services\Filter;
use App\Services\FrontendI18n;
use App\Services\OneTimeTokenService;
use App\Services\View;
use App\Utils\Hash;
use App\Utils\ResponseHelper;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use RedisException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function hash_equals;
use function in_array;
use function is_string;
use function strlen;
use function strtolower;
use function trim;
use const BASE_PATH;

final class InfoController extends BaseController
{
    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $themes = Tools::getDir(BASE_PATH . '/resources/views');
        $methods = Tools::getSsMethod();
        $webauthnDevices = array_map(static fn ($item) => (object) $item, (new MFADevice())->where('userid', $this->user->id)->where('type', 'passkey')->get()->toArray());
        $totpDevices = array_map(static fn ($item) => (object) $item, (new MFADevice())->where('userid', $this->user->id)->where('type', 'totp')->get()->toArray());
        $fidoDevices = array_map(static fn ($item) => (object) $item, (new MFADevice())->where('userid', $this->user->id)->where('type', 'fido')->get()->toArray());

        return $response->write(
            $this->view()
                ->assign('user', $this->user)
                ->assign('themes', $themes)
                ->assign('methods', $methods)
                ->assign('webauthnDevices', $webauthnDevices)
                ->assign('totpDevices', $totpDevices)
                ->assign('fidoDevices', $fidoDevices)
                ->fetch('user/edit.tpl')
        );
    }

    /**
     * @throws RedisException
     */
    public function updateEmail(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $new_email = strtolower(trim($this->antiXss->xss_clean($request->getParam('newemail'))));
        $user = $this->user;
        $old_email = $user->email;

        if (! $_ENV['enable_change_email'] || $user->is_shadow_banned) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.update_failed'));
        }

        if ($new_email === '') {
            return ResponseHelper::error($response, FrontendI18n::trans('response.email_required'));
        }

        if (! Filter::checkEmailFilter($new_email)) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.email_invalid'));
        }

        if ($new_email === $old_email) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.email_same'));
        }

        if ((new User())->where('email', $new_email)->first() !== null) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.email_in_use'));
        }

        if (Config::obtain('reg_email_verify')) {
            $redis = (new Cache())->initRedis();
            $email_verify_code = trim($this->antiXss->xss_clean($request->getParam('emailcode')));
            $email_verify = OneTimeTokenService::consume($redis, 'email_verify:' . $email_verify_code);

            if (! is_string($email_verify) || ! hash_equals(strtolower(trim($email_verify)), $new_email)) {
                return ResponseHelper::error($response, FrontendI18n::trans('response.email_verification_invalid'));
            }
        }

        $user->email = $new_email;

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.update_failed'));
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => FrontendI18n::trans('response.update_success'),
            'data' => [
                'email' => $user->email,
            ],
        ]);
    }

    public function updateUsername(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $newusername = $this->antiXss->xss_clean($request->getParam('newusername'));
        $user = $this->user;

        if ($user->is_shadow_banned) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.update_failed'));
        }

        $user->user_name = $newusername;

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.update_failed'));
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => FrontendI18n::trans('response.update_success'),
            'data' => [
                'username' => $user->user_name,
            ],
        ]);
    }

    public function unbindIm(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->user->unbindIM()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.unbind_failed'));
        }

        return $response->withHeader('HX-Refresh', 'true');
    }

    public function updatePassword(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $password = $request->getParam('password');
        $new_password = $request->getParam('new_password');
        $confirm_new_password = $request->getParam('confirm_new_password');
        $user = $this->user;

        if ($password === '' || $new_password === '' || $confirm_new_password === '') {
            return ResponseHelper::error($response, FrontendI18n::trans('response.password_required'));
        }

        if (! Hash::checkPassword($user->pass, $password)) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.old_password_wrong'));
        }

        if ($new_password !== $confirm_new_password) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.password_mismatch'));
        }

        if (strlen($new_password) < 8) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.password_too_short'));
        }

        $user->pass = Hash::passwordHash($new_password);

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.update_failed'));
        }

        (new ClientSessionService())->revokeAllForUser((int) $user->id);

        if (Config::obtain('enable_forced_replacement')) {
            $user->removeLink();
        }

        return ResponseHelper::success($response, FrontendI18n::trans('response.update_success'));
    }

    public function resetPasswd(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->user;
        $user->passwd = Tools::genRandomChar(16);
        $user->uuid = Uuid::uuid4();

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.reset_failed'));
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => FrontendI18n::trans('response.reset_success'),
            'data' => [
                'passwd' => $user->passwd,
                'uuid' => $user->uuid,
            ],
        ]);
    }

    public function resetApiToken(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->user;
        $user->api_token = Tools::genRandomChar(32);

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.reset_failed'));
        }

        (new ClientSessionService())->revokeAllForUser((int) $user->id);

        return ResponseHelper::success($response, FrontendI18n::trans('response.reset_success'));
    }

    public function updateMethod(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->user;
        $method = strtolower($this->antiXss->xss_clean($request->getParam('method')));

        if ($method === '') {
            return ResponseHelper::error($response, FrontendI18n::trans('response.invalid_input'));
        }

        if (! Tools::isParamValidate('method', $method)) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.encryption_invalid'));
        }

        $user->method = $method;

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.update_failed'));
        }

        return ResponseHelper::success($response, FrontendI18n::trans('response.update_success'));
    }

    public function resetUrl(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $this->user->removeLink();

        return ResponseHelper::success($response, FrontendI18n::trans('response.reset_success'));
    }

    public function updateDailyMail(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $value = (int) $request->getParam('mail');

        if (! in_array($value, [0, 1, 2])) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.invalid_parameter'));
        }

        $user = $this->user;
        $user->daily_mail_enable = $value;

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.update_failed'));
        }

        return ResponseHelper::success($response, FrontendI18n::trans('response.update_success'));
    }

    public function updateContactMethod(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $value = (int) $request->getParam('contact');

        if (! in_array($value, [1, 2])) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.invalid_parameter'));
        }

        $user = $this->user;
        $user->contact_method = $value;

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.update_failed'));
        }

        return ResponseHelper::success($response, FrontendI18n::trans('response.update_success'));
    }

    public function updateTheme(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $theme = $this->antiXss->xss_clean($request->getParam('theme'));
        $user = $this->user;

        if (! is_string($theme) || ! View::isValidTheme($theme)) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.theme_required'));
        }

        $user->theme = $theme;

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.update_failed'));
        }

        return $response->withHeader('HX-Refresh', 'true');
    }

    public function updateThemeMode(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $theme_mode = (int) $this->antiXss->xss_clean($request->getParam('theme_mode'));
        $user = $this->user;

        $user->is_dark_mode = in_array($theme_mode, [0, 1, 2]) ? $theme_mode : 0;

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.switch_failed'));
        }

        return $response->withHeader('HX-Refresh', 'true');
    }

    public function sendToGulag(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->user;
        $password = $request->getParam('password');

        if ($password === '' || ! Hash::checkPassword($user->pass, $password)) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.password_wrong'));
        }

        if ($_ENV['enable_kill']) {
            Auth::logout();
            $user->kill();

            return $response->withHeader('HX-Redirect', '/auth/login');
        }

        return ResponseHelper::error($response, FrontendI18n::trans('response.self_delete_disabled'));
    }
}
