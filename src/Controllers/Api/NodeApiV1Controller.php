<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\NodeEnrollmentService;
use App\Services\NodeProfileService;
use App\Services\NodeRuntimeService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
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

        $enrollmentService = new NodeEnrollmentService();

        try {
            $data = $enrollmentService->enroll($payload, $enrollmentService->extractBearerToken($request));
        } catch (InvalidArgumentException $e) {
            return $this->validationError(
                $request,
                $response,
                $e->getMessage(),
                $this->validationCodeForMessage($e->getMessage())
            );
        } catch (RuntimeException $e) {
            $isMissingToken = $e->getMessage() === 'Missing enroll token.';

            return $this->authError(
                $request,
                $response,
                $isMissingToken ? 'Missing enroll token' : 'Invalid enroll token',
                $isMissingToken ? 'AUTH_MISSING_ENROLL_TOKEN' : 'AUTH_INVALID_ENROLL_TOKEN'
            );
        }

        return $this->success($request, $response, $data);
    }

    /**
     * GET /node/api/v1/config
     */
    public function config(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $nodeId = $this->authenticatedNodeId($request) ?? $this->queryNodeId($request, 1001);
        $domain = $request->getQueryParam('domain', '');

        $data = (new NodeProfileService())->buildDefaultConfig($nodeId, is_string($domain) ? trim($domain) : '');

        return $this->success($request, $response, $data);
    }

    /**
     * GET /node/api/v1/users
     */
    public function users(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $data = (new NodeProfileService())->buildMockUsers(0, $this->authenticatedNodeId($request));

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
        $data = (new NodeRuntimeService())->acceptRuntime($payload, $this->authenticatedNodeId($request));

        return $this->success($request, $response, $data);
    }

    /**
     * POST /node/api/v1/traffic
     */
    public function traffic(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request) ?? [];
        $data = (new NodeRuntimeService())->acceptTraffic($payload, $this->authenticatedNodeId($request));

        return $this->success($request, $response, $data);
    }

    /**
     * POST /node/api/v1/online
     */
    public function online(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request) ?? [];
        $data = (new NodeRuntimeService())->acceptOnline($payload, $this->authenticatedNodeId($request));

        return $this->success($request, $response, $data);
    }

    /**
     * POST /node/api/v1/detect-log
     */
    public function detectLog(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request) ?? [];
        $data = (new NodeRuntimeService())->acceptDetectLog($payload, $this->authenticatedNodeId($request));

        return $this->success($request, $response, $data);
    }

    /**
     * POST /node/api/v1/heartbeat
     */
    public function heartbeat(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request) ?? [];
        $data = (new NodeRuntimeService())->acceptHeartbeat($payload, $this->authenticatedNodeId($request));

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
        string $code,
        int $status = 400
    ): ResponseInterface {
        return $response->withStatus($status)->withJson([
            'ret' => 0,
            'msg' => $message,
            'code' => $code,
            'request_id' => $this->getRequestId($request),
        ]);
    }

    private function authError(
        ServerRequest $request,
        Response $response,
        string $message,
        string $code
    ): ResponseInterface {
        return $this->validationError($request, $response, $message, $code, 401);
    }

    private function validationCodeForMessage(string $message): string
    {
        return match ($message) {
            'node_id is required.' => 'missing_node_id',
            'domain is required.' => 'missing_domain',
            'node_id does not exist.' => 'invalid_node_id',
            'domain does not match node.' => 'invalid_domain',
            default => 'invalid_enroll_payload',
        };
    }

    private function authenticatedNodeId(ServerRequest $request): ?int
    {
        $nodeId = $request->getAttribute('xnode_node_id');

        if (is_numeric($nodeId) && (int) $nodeId > 0) {
            return (int) $nodeId;
        }

        return null;
    }

    private function queryNodeId(ServerRequest $request, int $default): int
    {
        $nodeId = $request->getQueryParam('node_id', $default);

        if ($nodeId === null || $nodeId === '' || ! is_numeric($nodeId)) {
            return $default;
        }

        return (int) $nodeId;
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
