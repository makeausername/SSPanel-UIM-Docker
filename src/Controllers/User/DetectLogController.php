<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Config;
use App\Models\DetectLog;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class DetectLogController extends BaseController
{
    /**
     * 审计碰撞记录
     *
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! Config::obtain('display_detect_log')) {
            return $response->withRedirect('/user');
        }

        $page = max(1, (int) $request->getQueryParam('page', 1));
        $logPage = (new DetectLog())
            ->orderBy('id', 'desc')
            ->where('user_id', $this->user->id)
            ->paginate(50, '*', 'page', $page);
        $logs = $logPage->items();

        foreach ($logs as $log) {
            $log->node_name = $log->nodeName();
            $rule = $log->rule();
            if ($rule !== null) {
                $rule->type = $rule->type();
            }
            $log->rule = $rule;
            $log->datetime = Tools::toDateTime($log->datetime);
        }

        return $response->write($this->view()
            ->assign('logs', $logs)
            ->assign('current_page', $logPage->currentPage())
            ->assign('last_page', $logPage->lastPage())
            ->fetch('user/detect/log.tpl'));
    }
}
