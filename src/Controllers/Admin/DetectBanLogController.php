<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DetectBanLog;
use App\Services\DataTableRequest;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class DetectBanLogController extends BaseController
{
    private static array $details =
        [
            'field' => [
                'id' => '事件ID',
                'user_id' => '用户ID',
                'detect_number' => '违规次数',
                'ban_time' => '封禁时长(分钟)',
                'start_time' => '统计开始时间',
                'end_time' => '统计结束&封禁开始时间',
                'ban_end_time' => '封禁结束时间',
                'all_detect_number' => '累计违规次数',
            ],
        ];

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/log/detect_ban.tpl')
        );
    }

    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $table = DataTableRequest::from(
            $request,
            ['id', 'user_id', 'detect_number', 'ban_time', 'start_time', 'end_time', 'all_detect_number'],
            'id'
        );

        $detect_ban_log = DetectBanLog::query();

        if ($table->search !== '') {
            $detect_ban_log->where('user_id', '=', $table->search);
        }

        $detect_ban_log->orderBy($table->orderBy, $table->orderDirection);
        if ($table->orderBy !== 'id') {
            $detect_ban_log->orderBy('id', 'desc');
        }

        $filtered = $detect_ban_log->count();
        $total = (new DetectBanLog())->count();

        $bans = $detect_ban_log->paginate($table->length, '*', '', $table->page);

        foreach ($bans as $ban) {
            $ban->start_time = Tools::toDateTime((int) $ban->start_time);
            $ban->end_time = Tools::toDateTime((int) $ban->end_time);
            $ban->ban_end_time = $ban->banEndTime();
        }

        return $response->withJson([
            'draw' => $table->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'bans' => $bans,
        ]);
    }
}
