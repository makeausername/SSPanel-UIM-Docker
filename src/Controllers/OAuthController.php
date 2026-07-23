<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Config;
use App\Models\User;
use App\Services\Cache;
use App\Services\FrontendI18n;
use App\Services\OneTimeTokenService;
use App\Utils\ResponseHelper;
use App\Utils\Tools;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RedisException;
use RuntimeException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Smarty\Exception as SmartyException;
use function hash;
use function hash_equals;
use function hash_hmac;
use function http_build_query;
use function implode;
use function is_array;
use function is_scalar;
use function is_string;
use function json_decode;
use function rawurlencode;
use function time;
use function trim;

final class OAuthController extends BaseController
{
    /**
     * @throws SmartyException
     * @throws RedisException
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return match ($args['type']) {
            'slack' => $this->slack($request, $response, $args),
            'discord' => $this->discord($request, $response, $args),
            'telegram' => $this->telegram($request, $response, $args),
            default => $response->withStatus(404)->write($this->view()->fetch('404.tpl')),
        };
    }

    /**
     * @throws RedisException
     * @throws Exception
     */
    public function slack(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->user;
        $redis = (new Cache())->initRedis();

        if ($request->getParam('code') === null) {
            $state = Tools::genRandomChar(32);
            if (! is_string($state)) {
                throw new RuntimeException('Unable to generate OAuth state.');
            }
            $redis->setex('slack_state:' . $user->id, 300, $state);
            $client_id = Config::obtain('slack_client_id');
            $team_id = Config::obtain('slack_team_id');
            $redirect_uri = $_ENV['baseUrl'] . '/oauth/slack';

            return $response->withJson([
                'ret' => 1,
                'redir' => 'https://slack.com/openid/connect/authorize?' . http_build_query([
                    'response_type' => 'code',
                    'scope' => 'openid profile',
                    'client_id' => $client_id,
                    'state' => $state,
                    'team' => $team_id,
                    'nonce' => $state,
                    'redirect_uri' => $redirect_uri,
                ]),
            ]);
        }

        $code = $request->getParam('code');
        $state = $request->getParam('state');

        $expectedState = OneTimeTokenService::consume($redis, 'slack_state:' . $user->id);
        if ($expectedState === false || ! hash_equals($expectedState, (string) $state)) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        $client = new Client();
        $slack_api_url = 'https://slack.com/api/openid.connect.token';

        $code_headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $code_body = [
            'client_id' => Config::obtain('slack_client_id'),
            'client_secret' => Config::obtain('slack_client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $_ENV['baseUrl'] . '/oauth/slack',
        ];

        try {
            $code_response = $client->post($slack_api_url, [
                'headers' => $code_headers,
                'form_params' => $code_body,
                'connect_timeout' => 3,
                'timeout' => 3,
            ]);
        } catch (GuzzleException) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        $tokenResponse = json_decode($code_response->getBody()->__toString(), true);
        if (
            ! is_array($tokenResponse)
            || ! ($tokenResponse['ok'] ?? false)
            || ! is_string($tokenResponse['access_token'] ?? null)
            || $tokenResponse['access_token'] === ''
        ) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        try {
            $identityResponse = $client->post('https://slack.com/api/openid.connect.userInfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenResponse['access_token'],
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'connect_timeout' => 3,
                'timeout' => 3,
            ]);
        } catch (GuzzleException) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }
        $identity = json_decode($identityResponse->getBody()->__toString(), true);
        $slack_user_id = is_array($identity) && ($identity['ok'] ?? false)
            ? ($identity['https://slack.com/user_id'] ?? $identity['sub'] ?? null)
            : null;
        $configuredTeamId = trim((string) Config::obtain('slack_team_id'));
        $identityTeamId = is_array($identity) ? (string) ($identity['https://slack.com/team_id'] ?? '') : '';
        if (
            ! is_string($slack_user_id)
            || $slack_user_id === ''
            || ($configuredTeamId !== '' && ! hash_equals($configuredTeamId, $identityTeamId))
        ) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        if ((new User())->where('im_type', 1)->where('im_value', $slack_user_id)->first() !== null ||
            ($user->im_type === 1 && $user->im_value === $slack_user_id)) {
            return ResponseHelper::error($response, FrontendI18n::trans(
                'response.auth.account_already_bound',
                ['%provider%' => 'Slack']
            ));
        }

        $user->im_type = 1;
        $user->im_value = $slack_user_id;
        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        return $response->withRedirect($_ENV['baseUrl'] . '/user/edit');
    }

    /**
     * @throws RedisException
     */
    public function discord(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->user;
        $redis = (new Cache())->initRedis();

        if ($request->getParam('code') === null) {
            $state = Tools::genRandomChar(32);
            if (! is_string($state)) {
                throw new RuntimeException('Unable to generate OAuth state.');
            }
            $redis->setex('discord_state:' . $user->id, 300, $state);
            $client_id = Config::obtain('discord_client_id');
            $redirect_uri = $_ENV['baseUrl'] . '/oauth/discord';

            return $response->withJson([
                'ret' => 1,
                'redir' => 'https://discord.com/api/oauth2/authorize?' . http_build_query([
                    'client_id' => $client_id,
                    'redirect_uri' => $redirect_uri,
                    'response_type' => 'code',
                    'scope' => 'guilds.join identify',
                    'state' => $state,
                ]),
            ]);
        }

        $code = $request->getParam('code');
        $state = $request->getParam('state');

        $expectedState = OneTimeTokenService::consume($redis, 'discord_state:' . $user->id);
        if ($expectedState === false || ! hash_equals($expectedState, (string) $state)) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        $client = new Client();
        $discord_api_url = 'https://discord.com/api/oauth2/token';

        $code_headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $code_body = [
            'client_id' => Config::obtain('discord_client_id'),
            'client_secret' => Config::obtain('discord_client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $_ENV['baseUrl'] . '/oauth/discord',
        ];

        try {
            $code_response = $client->post($discord_api_url, [
                'headers' => $code_headers,
                'form_params' => $code_body,
                'connect_timeout' => 3,
                'timeout' => 3,
            ]);
        } catch (GuzzleException) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        if ($code_response->getStatusCode() !== 200) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        $tokenResponse = json_decode($code_response->getBody()->getContents(), true);
        $access_token = is_array($tokenResponse) ? ($tokenResponse['access_token'] ?? null) : null;
        if (! is_string($access_token) || $access_token === '') {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }
        $discord_user_url = 'https://discord.com/api/users/@me';

        $user_headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Bearer ' . $access_token,
        ];

        try {
            $user_response = $client->get($discord_user_url, [
                'headers' => $user_headers,
                'connect_timeout' => 3,
                'timeout' => 3,
            ]);
        } catch (GuzzleException) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        if ($user_response->getStatusCode() !== 200) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        $identity = json_decode($user_response->getBody()->getContents(), true);
        $discord_user_id = is_array($identity) ? ($identity['id'] ?? null) : null;
        if (! is_scalar($discord_user_id) || trim((string) $discord_user_id) === '') {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }
        $discord_user_id = trim((string) $discord_user_id);

        if ((new User())->where('im_type', 2)->where('im_value', $discord_user_id)->first() !== null ||
            ($user->im_type === 2 && $user->im_value === $discord_user_id)) {
            return ResponseHelper::error($response, FrontendI18n::trans(
                'response.auth.account_already_bound',
                ['%provider%' => 'Discord']
            ));
        }

        if (Config::obtain('discord_guild_id') !== 0) {
            $discord_guild_url = self::discordGuildMemberUrl(
                (string) Config::obtain('discord_guild_id'),
                $discord_user_id
            );

            $guild_headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bot ' . Config::obtain('discord_bot_token'),
            ];

            $guild_body = [
                'access_token' => $access_token,
            ];

            try {
                $client->put($discord_guild_url, [
                    'headers' => $guild_headers,
                    'json' => $guild_body,
                    'connect_timeout' => 3,
                    'timeout' => 3,
                ]);
            } catch (GuzzleException) {
                return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
            }
        }

        $user->im_type = 2;
        $user->im_value = $discord_user_id;
        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        return $response->withRedirect($_ENV['baseUrl'] . '/user/edit');
    }

    public static function discordGuildMemberUrl(string $guildId, string $discordUserId): string
    {
        return 'https://discord.com/api/guilds/' . rawurlencode($guildId)
            . '/members/' . rawurlencode($discordUserId);
    }

    public function telegram(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user_auth = json_decode((string) $request->getParam('user'), true);

        if (! is_array($user_auth) || ! isset($user_auth['hash'], $user_auth['auth_date'], $user_auth['id'])) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        $check_hash = (string) $user_auth['hash'];
        unset($user_auth['hash']);
        $data_check_arr = [];

        foreach ($user_auth as $key => $value) {
            if (! is_scalar($value)) {
                return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
            }
            $data_check_arr[] = $key . '=' . $value;
        }

        sort($data_check_arr);
        $data_check_string = implode("\n", $data_check_arr);
        $secret_key = hash('sha256', Config::obtain('telegram_token'), true);
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);

        $authDate = (int) $user_auth['auth_date'];
        $age = time() - $authDate;
        if (! hash_equals($hash, $check_hash) || $age < -30 || $age > 300) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        $telegram_id = $this->antiXss->xss_clean($user_auth['id']);
        $user = $this->user;

        if ((new User())->where('im_type', 4)->where('im_value', $telegram_id)->first() !== null ||
            ($user->im_type === 4 && $user->im_value === $telegram_id)) {
            return ResponseHelper::error($response, FrontendI18n::trans(
                'response.auth.account_already_bound',
                ['%provider%' => 'Telegram']
            ));
        }

        $user->im_type = 4;
        $user->im_value = $telegram_id;

        if (! $user->save()) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.auth.oauth_failed'));
        }

        return ResponseHelper::success($response, FrontendI18n::trans('response.auth.bind_success'));
    }
}
