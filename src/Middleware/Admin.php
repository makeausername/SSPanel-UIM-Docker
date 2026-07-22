<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Auth as AuthService;
use App\Services\AdminPermissionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;

final class Admin implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = AuthService::getUser();

        if (! $user->isLogin) {
            return AppFactory::determineResponseFactory()->createResponse(302)->withHeader('Location', '/auth/login');
        }

        if (! $user->is_admin || (int) $user->is_banned === 1) {
            return AppFactory::determineResponseFactory()->createResponse(302)->withHeader('Location', '/user');
        }

        if (! AdminPermissionService::allows($user, $request->getMethod(), $request->getUri()->getPath())) {
            return AppFactory::determineResponseFactory()->createResponse(403);
        }

        return $handler->handle($request->withAttribute('admin_role', AdminPermissionService::role($user)));
    }
}
