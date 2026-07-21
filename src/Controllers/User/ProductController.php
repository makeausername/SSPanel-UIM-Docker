<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Product;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function json_decode;

final class ProductController extends BaseController
{
    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $tabps = (new Product())->where('status', '1')
            ->where('type', 'tabp')
            ->orderBy('id')
            ->get();

        $bandwidths = (new Product())->where('status', '1')
            ->where('type', 'bandwidth')
            ->orderBy('id')
            ->get();

        foreach ($tabps as $tabp) {
            $tabp->content = self::normalizeContent(json_decode($tabp->content));
        }

        foreach ($bandwidths as $bandwidth) {
            $bandwidth->content = self::normalizeContent(json_decode($bandwidth->content));
        }

        return $response->write(
            $this->view()
                ->assign('tabps', $tabps)
                ->assign('bandwidths', $bandwidths)
                ->fetch('user/product.tpl')
        );
    }

    private static function normalizeContent(object $content): object
    {
        $content->monthly_plan = $content->monthly_plan ?? false;
        $content->unlimited_bandwidth = $content->unlimited_bandwidth ?? false;
        $content->current_month_only = $content->current_month_only ?? false;

        return $content;
    }
}
