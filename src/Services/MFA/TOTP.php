<?php

declare(strict_types=1);

namespace App\Services\MFA;

use App\Models\MFADevice;
use App\Models\User;
use App\Services\Cache;
use App\Services\FrontendI18n;
use Exception;
use Vectorface\GoogleAuthenticator;

final class TOTP
{
    /**
     * @throws Exception
     */
    public static function generateGaToken(): string
    {
        $ga = new GoogleAuthenticator();
        return $ga->createSecret(32);
    }

    public static function RegisterRequest(User $user): array
    {
        try {
            $TOTPDevice = (new MFADevice())->where('userid', $user->id)
                ->where('type', 'totp')
                ->first();
            if ($TOTPDevice !== null) {
                return [
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.auth.totp_already_registered'),
                ];
            }
            $ga = new GoogleAuthenticator();
            $token = $ga->createSecret(32);
            $redis = (new Cache())->initRedis();
            $redis->setex('totp_register_' . session_id(), 300, $token);
            return [
                'ret' => 1,
                'msg' => '',
                'token' => $token,
                'url' => self::getGaUrl($user, $token),
            ];
        } catch (Exception) {
            return [
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.update_failed'),
            ];
        }
    }

    public static function getGaUrl(User $user, string $token): string
    {
        return 'otpauth://totp/' . rawurlencode($_ENV['appName']) . ':' . rawurlencode($user->email) . '?secret=' . $token . '&issuer=' . rawurlencode($_ENV['appName']);
    }

    public static function RegisterHandle(User $user, string $code): array
    {
        $redis = (new Cache())->initRedis();
        $token = $redis->get('totp_register_' . session_id());
        if ($token === false) {
            return ['ret' => 0, 'msg' => FrontendI18n::trans('response.auth.totp_request_expired')];
        }
        $ga = new GoogleAuthenticator();
        if (! $ga->verifyCode($token, $code)) {
            return ['ret' => 0, 'msg' => FrontendI18n::trans('response.auth.verification_code_invalid')];
        }
        if ((new MFADevice())->where('userid', $user->id)->where('type', 'totp')->exists()) {
            $redis->del('totp_register_' . session_id());

            return ['ret' => 0, 'msg' => FrontendI18n::trans('response.auth.totp_already_registered')];
        }
        $MFADevice = new MFADevice();
        $MFADevice->userid = $user->id;
        $MFADevice->name = 'TOTP';
        $MFADevice->rawid = 'TOTP';
        $MFADevice->body = json_encode(['token' => $token]);
        $MFADevice->type = 'totp';
        $MFADevice->created_at = date('Y-m-d H:i:s');
        $MFADevice->save();
        $redis->del('totp_register_' . session_id());
        return ['ret' => 1, 'msg' => FrontendI18n::trans('response.auth.registration_success')];
    }

    public static function AssertHandle(User $user, string $code): array
    {
        try {
            $TOTPDevice = (new MFADevice())->where('userid', $user->id)
                ->where('type', 'totp')
                ->first();
            if ($TOTPDevice === null) {
                return [
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.auth.totp_not_registered'),
                ];
            }
            $ga = new GoogleAuthenticator();
            if (! $ga->verifyCode(json_decode($TOTPDevice->body, true)['token'], $code)) {
                return [
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.auth.verification_code_invalid'),
                ];
            }
            return [
                'ret' => 1,
                'msg' => '',
            ];
        } catch (Exception) {
            return [
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.update_failed'),
            ];
        }
    }
}
