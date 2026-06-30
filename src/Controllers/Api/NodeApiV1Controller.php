<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\NodeEnrollmentService;
use App\Services\NodeProfileService;
use App\Services\NodeRuntimeService;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function array_key_exists;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function json_decode;
use function time;
use function trim;
use function uniqid;

final class NodeApiV1Controller extends BaseController
{
    /**
     * POST /node/api/v1/enroll
     */
    public function enroll(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request);

        if ($payload === null) {
            return $this->validationError($request, $response, 'Invalid JSON body.', 'invalid_json');
        }

        if (! array_key_exists('node_id', $payload) || ! is_scalar($payload['node_id']) || $payload['node_id'] === '') {
            return $this->validationError($request, $response, 'node_id is required.', 'missing_node_id');
        }

        if (
            ! array_key_exists('domain', $payload) ||
            ! is_string($payload['domain']) ||
            trim($payload['domain']) === ''
        ) {
            return $this->validationError($request, $response, 'domain is required.', 'missing_domain');
        }

        // TODO: Real enroll token validation and token persistence will be implemented next.
        $data = (new NodeEnrollmentService())->buildStubEnrollment(
            $payload['node_id'],
            trim($payload['domain'])
        );

        return $this->success($request, $response, $data);
    }

    /**
     * GET /node/api/v1/config
     */
    public function config(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $nodeId = $request->getQueryParam('node_id', 1001);

        if ($nodeId === null || $nodeId === '') {
            $nodeId = 1001;
        }

        if (is_numeric($nodeId)) {
            $nodeId = (int) $nodeId;
        }

        $domain = $request->getQueryParam('domain', 'node1.example.com');

        if (! is_string($domain) || trim($domain) === '') {
            $domain = 'node1.example.com';
        }

        $data = (new NodeProfileService())->buildDefaultConfig($nodeId, trim($domain));

        return $this->success($request, $response, $data);
    }

    /**
     * GET /node/api/v1/users
     */
    public function users(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $data = (new NodeProfileService())->buildMockUsers(time());

        return $this->success($request, $response, $data);
    }

    /**
     * GET /node/api/v1/detect-rules
     */
    public function detectRules(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $data = (new NodeProfileService())->buildMockDetectRules();

        return $this->success($request, $response, $data);
    }

    /**
     * POST /node/api/v1/runtime
     */
    public function runtime(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request) ?? [];
        $data = (new NodeRuntimeService())->acceptRuntime($payload);

        return $this->success($request, $response, $data);
    }

    /**
     * POST /node/api/v1/traffic
     */
    public function traffic(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request) ?? [];
        $data = (new NodeRuntimeService())->acceptTraffic($payload);

        return $this->success($request, $response, $data);
    }

    /**
     * POST /node/api/v1/online
     */
    public function online(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request) ?? [];
        $data = (new NodeRuntimeService())->acceptOnline($payload);

        return $this->success($request, $response, $data);
    }

    /**
     * POST /node/api/v1/detect-log
     */
    public function detectLog(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request) ?? [];
        $data = (new NodeRuntimeService())->acceptDetectLog($payload);

        return $this->success($request, $response, $data);
    }

    /**
     * POST /node/api/v1/heartbeat
     */
    public function heartbeat(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request) ?? [];
        $data = (new NodeRuntimeService())->acceptHeartbeat($payload);

        return $this->success($request, $response, $data);
    }

    private function success(ServerRequest $request, Response $response, array $data): ResponseInterface
    {
        return $response->withJson([
            'ret' => 1,
            'data' => $data,
            'request_id' => $this->getRequestId($request),
        ]);
    }

    private function validationError(
        ServerRequest $request,
        Response $response,
        string $message,
        string $code
    ): ResponseInterface {
        return $response->withJson([
            'ret' => 0,
            'msg' => $message,
            'code' => $code,
            'request_id' => $this->getRequestId($request),
        ]);
    }

    private function getRequestId(ServerRequest $request): string
    {
        $requestId = $request->getHeaderLine('X-Request-Id');

        if ($requestId !== '') {
            return $requestId;
        }

        return uniqid('xn_', true);
    }

    private function readJsonBody(ServerRequest $request): ?array
    {
        $body = trim($request->getBody()->__toString());

        if ($body === '') {
            return [];
        }

        $payload = json_decode($body, true);

        return is_array($payload) ? $payload : null;
    }
}
