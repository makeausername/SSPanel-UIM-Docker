<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Setting;

use App\Controllers\BaseController;
use App\Models\Config;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Smarty\Exception;
use function array_diff;
use function array_values;
use function in_array;
use function is_numeric;

final class CronController extends BaseController
{
    private const INTERNAL_FIELDS = [
        'last_daily_job_time',
        'last_daily_finance_mail_time',
        'last_weekly_finance_mail_time',
        'last_monthly_finance_mail_time',
        'last_detect_gfw_job_time',
        'last_detect_ban_job_time',
    ];

    private const RETENTION_FIELDS = [
        'node_report_retention_days',
        'node_probe_retention_days',
        'email_dead_retention_days',
    ];

    private array $update_field;
    private array $settings;

    public function __construct()
    {
        parent::__construct();
        $this->update_field = array_values(array_diff(
            Config::getItemListByClass('cron'),
            self::INTERNAL_FIELDS
        ));
        $this->settings = Config::getClass('cron');
    }

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('update_field', $this->update_field)
                ->assign('settings', $this->settings)
                ->fetch('admin/setting/cron.tpl')
        );
    }

    public function save(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $daily_job_hour = (int) $request->getParam('daily_job_hour');
        $daily_job_minute = (int) $request->getParam('daily_job_minute');

        if ($daily_job_hour < 0 || $daily_job_hour > 23) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '每日任务执行时间的小时数必须在 0-23 之间',
            ]);
        }

        if ($daily_job_minute < 0 || $daily_job_minute > 59) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '每日任务执行时间的分钟数必须在 0-59 之间',
            ]);
        }

        foreach (self::RETENTION_FIELDS as $item) {
            $value = $request->getParam($item);

            if (! is_numeric($value) || (int) $value < 1 || (int) $value > 3650) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => $item . ' 必须在 1-3650 天之间',
                ]);
            }
        }

        foreach ($this->update_field as $item) {
            if ($item === 'daily_job_minute') {
                if (! Config::set($item, $daily_job_minute - ($daily_job_minute % 5))) {
                    return $response->withJson([
                        'ret' => 0,
                        'msg' => '保存 ' . $item . ' 时出错',
                    ]);
                }
                continue;
            }

            $value = $request->getParam($item);

            if ($value === null) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => '缺少设置项 ' . $item,
                ]);
            }

            if (in_array($item, self::RETENTION_FIELDS, true)) {
                $value = (int) $value;
            }

            if (! Config::set($item, $value)) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => '保存 ' . $item . ' 时出错',
                ]);
            }
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '保存成功',
        ]);
    }
}
