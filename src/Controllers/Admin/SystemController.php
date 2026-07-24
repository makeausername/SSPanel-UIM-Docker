<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Config;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function file_get_contents;
use function preg_match;
use function stream_context_create;
use function trim;
use function version_compare;
use const VERSION;

final class SystemController extends BaseController
{
    /**
     * 后台系统状态页面
     *
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $last_daily_job_time = Tools::toDateTime(Config::obtain('last_daily_job_time'));
        $db_version = Config::obtain('db_version');

        return $response->write(
            $this->view()
                ->assign('version', VERSION)
                ->assign('last_daily_job_time', $last_daily_job_time)
                ->assign('db_version', $db_version)
                ->fetch('admin/system.tpl')
        );
    }

    /**
     * 检查版本更新
     */
    public function checkUpdate(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $latestVersion = self::normalizeLatestVersion(@file_get_contents(
            'https://ota.sspanel.io/get-latest-version',
            false,
            stream_context_create([
            'http' => [
                'timeout' => 3,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ])));
        if ($latestVersion === null) {
            return $response->withStatus(503)->withJson([
                'ret' => 0,
                'msg' => '版本服务暂时不可用',
            ]);
        }
        $is_upto_date = version_compare($latestVersion, VERSION, '<=');

        return $response->withJson([
            'ret' => 1,
            'is_upto_date' => $is_upto_date,
            'latest_version' => $latestVersion,
        ]);
    }

    public static function normalizeLatestVersion(string|false $value): ?string
    {
        if ($value === false) {
            return null;
        }

        $version = trim($value);

        return preg_match('/^\d{2,4}\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/D', $version) === 1
            ? $version
            : null;
    }
}
