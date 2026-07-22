<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Node;
use App\Services\DynamicRate;
use App\Services\FrontendI18n;
use App\Services\Subscribe;
use App\Utils\ResponseHelper;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function array_fill;
use function count;
use function json_decode;
use function json_encode;

final class RateController extends BaseController
{
    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $nodes = Subscribe::getUserNodes($this->user);
        $node_list = [];

        foreach ($nodes as $node) {
            $node_list[] = [
                'id' => $node->id,
                'name' => $node->name,
            ];
        }

        if (count($node_list) === 0) {
            $node_list[] = [
                'id' => 0,
                'name' => FrontendI18n::trans('response.no_nodes'),
            ];
        }

        $initial_node = $nodes->first();
        $initial_chart = $initial_node === null
            ? ['msg' => $node_list[0]['name'], 'data' => []]
            : $this->buildRateData($initial_node);

        return $response->write(
            $this->view()
                ->assign('node_list', $node_list)
                ->assign(
                    'initial_chart',
                    json_encode(
                        $initial_chart,
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
                    )
                )
                ->fetch('user/rate.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $nodes = Subscribe::getUserNodes($this->user);
        $node = $nodes->find($request->getParam('node_id'));

        if ($node === null) {
            return ResponseHelper::error($response, FrontendI18n::trans('response.node_not_found'));
        }

        $event = json_encode([
            'drawChart' => $this->buildRateData($node),
        ], JSON_THROW_ON_ERROR);

        return $response->withHeader('HX-Trigger', $event)->withJson([
            'ret' => 1,
        ]);
    }

    /**
     * @return array{msg: string, data: array<int, float>}
     */
    private function buildRateData(Node $node): array
    {
        if ($node->is_dynamic_rate) {
            $dynamic_rate_config = json_decode($node->dynamic_rate_config);

            $dynamic_rate_type = match ($node->dynamic_rate_type) {
                1 => 'linear',
                default => 'logistic',
            };

            $rates = DynamicRate::getFullDayRates(
                (float) $dynamic_rate_config?->max_rate,
                (int) $dynamic_rate_config?->max_rate_time,
                (float) $dynamic_rate_config?->min_rate,
                (int) $dynamic_rate_config?->min_rate_time,
                $dynamic_rate_type
            );
        } else {
            $rates = array_fill(0, 24, (float) $node->traffic_rate);
        }

        return [
            'msg' => (string) $node->name,
            'data' => $rates,
        ];
    }
}
