<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\MFADevice;
use App\Services\Cache;
use App\Services\FrontendI18n;
use App\Services\MFA\FIDO;
use App\Services\MFA\TOTP;
use App\Services\MFA\WebAuthn;
use App\Services\RateLimit;
use App\Utils\Hash;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Throwable;
use function hash;
use function hash_equals;
use function is_string;
use function session_id;


/**
 *  MFAController
 */
final class MFAController extends BaseController
{
    public function webauthnRegisterRequest(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->hasRecentAuthentication()) {
            return $this->reauthRequired($response);
        }

        return $response->withJson(WebAuthn::RegisterRequest($this->user));
    }

    public function webauthnRegisterHandle(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->hasRecentAuthentication()) {
            return $this->reauthRequired($response);
        }

        try {
            return $response->withJson(WebAuthn::RegisterHandle($this->user, $this->antiXss->xss_clean($request)));
        } catch (Exception) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.update_failed'),
            ]);
        }
    }

    public function webauthnDelete(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->hasRecentAuthentication()) {
            return $this->reauthRequired($response);
        }

        $webauthnDevice = (new MFADevice())
            ->where('id', (int) $args['id'])
            ->where('userid', $this->user->id)
            ->where('type', 'passkey')
            ->first();
        if ($webauthnDevice === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.device_not_found'),
            ]);
        }
        $webauthnDevice->delete();
        return $response->withHeader('HX-Refresh', 'true')->withJson([
            'ret' => 1,
            'msg' => FrontendI18n::trans('response.device_delete_success'),
        ]);
    }

    public function totpRegisterRequest(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->hasRecentAuthentication()) {
            return $this->reauthRequired($response);
        }

        return $response->withJson(TOTP::RegisterRequest($this->user));
    }

    public function totpRegisterHandle(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->hasRecentAuthentication()) {
            return $this->reauthRequired($response);
        }

        try {
            return $response->withJson(TOTP::RegisterHandle($this->user, $this->antiXss->xss_clean($request->getParam('code', ''))));
        } catch (Exception) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.update_failed'),
            ]);
        }
    }

    public function totpDelete(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->hasRecentAuthentication()) {
            return $this->reauthRequired($response);
        }

        $totpDevice = (new MFADevice())
            ->where('userid', $this->user->id)
            ->where('type', 'totp')
            ->first();
        if ($totpDevice === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.device_not_found'),
            ]);
        }
        $totpDevice->delete();
        return $response->withHeader('HX-Refresh', 'true')->withJson([
            'ret' => 1,
            'msg' => FrontendI18n::trans('response.device_delete_success'),
        ]);
    }

    public function fidoRegisterRequest(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->hasRecentAuthentication()) {
            return $this->reauthRequired($response);
        }

        return $response->withJson(FIDO::RegisterRequest($this->user));
    }

    public function fidoRegisterHandle(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->hasRecentAuthentication()) {
            return $this->reauthRequired($response);
        }

        try {
            return $response->withJson(FIDO::RegisterHandle($this->user, $this->antiXss->xss_clean($request->getParsedBody())));
        } catch (Exception) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.update_failed'),
            ]);
        }
    }

    public function fidoDelete(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! $this->hasRecentAuthentication()) {
            return $this->reauthRequired($response);
        }

        $fidoDevice = (new MFADevice())
            ->where('id', (int) $args['id'])
            ->where('userid', $this->user->id)
            ->where('type', 'fido')
            ->first();
        if ($fidoDevice === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.device_not_found'),
            ]);
        }
        $fidoDevice->delete();
        return $response->withHeader('HX-Refresh', 'true')->withJson([
            'ret' => 1,
            'msg' => FrontendI18n::trans('response.device_delete_success'),
        ]);
    }

    public function reauthenticate(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        try {
            $allowed = (new RateLimit())->checkRateLimit(
                'login_account',
                hash('sha256', 'mfa-manage:' . (int) $this->user->id)
            );
        } catch (Throwable) {
            $allowed = false;
        }

        if (! $allowed) {
            return $response->withStatus(429)->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.too_many_requests'),
            ]);
        }

        $password = (string) $request->getParam('password', '');
        if ($password === '' || ! Hash::checkPassword($this->user->pass, $password)) {
            return $response->withStatus(401)->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.password_wrong'),
            ]);
        }

        try {
            (new Cache())->initRedis()->setex($this->reauthKey(), 300, (string) $this->user->id);
        } catch (Throwable) {
            return $response->withStatus(503)->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.update_failed'),
            ]);
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => FrontendI18n::trans('response.mfa_reauth_success'),
        ]);
    }

    private function hasRecentAuthentication(): bool
    {
        try {
            $value = (new Cache())->initRedis()->get($this->reauthKey());
        } catch (Throwable) {
            return false;
        }

        return is_string($value) && hash_equals((string) $this->user->id, $value);
    }

    private function reauthKey(): string
    {
        return 'mfa_manage_' . session_id();
    }

    private function reauthRequired(Response $response): ResponseInterface
    {
        return $response->withStatus(403)->withJson([
            'ret' => 0,
            'msg' => FrontendI18n::trans('response.mfa_reauth_required'),
            'code' => 'MFA_REAUTH_REQUIRED',
        ]);
    }
}
