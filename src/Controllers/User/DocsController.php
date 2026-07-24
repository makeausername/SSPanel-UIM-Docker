<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Config;
use App\Models\Docs;
use App\Services\Subscribe;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class DocsController extends BaseController
{
    public function androidRedirect(
        ServerRequest $request,
        Response $response,
        array $args
    ): ResponseInterface {
        return $response->withRedirect('/user/docs/android');
    }

    public function shadowrocketRedirect(
        ServerRequest $request,
        Response $response,
        array $args
    ): ResponseInterface {
        return $response->withRedirect('/user/docs/shadowrocket');
    }

    /**
     * @throws Exception
     */
    public function android(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()->fetch('user/docs/android.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function windows(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()->fetch('user/docs/windows.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function shadowrocket(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $subscriptionUrl = Subscribe::getUniversalSubLink($this->user) . '/v2ray';

        return $response->write(
            $this->view()
                ->assign('subscriptionUrl', $subscriptionUrl)
                ->assign('shadowrocketImportUrl', Subscribe::shadowrocketImportUrl($subscriptionUrl))
                ->fetch('user/docs/shadowrocket.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! Config::obtain('display_docs') ||
            (Config::obtain('display_docs_only_for_paid_user') && $this->user->class === 0)) {
            return $response->withRedirect('/user');
        }

        $docs = (new Docs())->where('status', 1)
            ->orderBy('sort')
            ->orderBy('id', 'desc')->get();

        return $response->write(
            $this->view()
                ->assign('docs', $docs)
                ->fetch('user/docs/index.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function detail(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        if (! Config::obtain('display_docs') ||
            (Config::obtain('display_docs_only_for_paid_user') && $this->user->class === 0)) {
            return $response->withRedirect('/user/docs');
        }

        $doc = (new Docs())->where('status', 1)->where('id', $args['id'])->first();

        if (! $doc) {
            return $response->withRedirect('/user/docs');
        }

        return $response->write(
            $this->view()
                ->assign('doc', $doc)
                ->fetch('user/docs/view.tpl')
        );
    }
}
