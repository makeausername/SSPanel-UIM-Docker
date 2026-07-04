<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\NodeProbeService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Throwable;
use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function trim;
use function uniqid;

final class ProbeApiV1Controller extends BaseController
{
    /**
     * POST /probe/api/v1/report
     */
    public function report(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $payload = $this->readJsonBody($request);

        if ($payload === null) {
            return $this->error($request, $response, 'Invalid JSON body.', 'invalid_json');
        }

        try {
            $records = $this->normalizeReportPayload($request, $payload);
        } catch (InvalidArgumentException $e) {
            return $this->error($request, $response, $e->getMessage(), 'invalid_probe_payload');
        }

        $allowedNodeIds = $this->allowedNodeIds($request);

        foreach ($records as $record) {
            if ($allowedNodeIds !== [] && ! in_array((int) $record['node_id'], $allowedNodeIds, true)) {
                return $this->error(
                    $request,
                    $response,
                    'Probe token is not allowed to report this node',
                    'PROBE_NODE_NOT_ALLOWED',
                    403
                );
            }
        }

        $results = [];

        try {
            foreach ($records as $record) {
                $results[] = [
                    'node_id' => (int) $record['node_id'],
                    'summary' => NodeProbeService::recordResult($record, true),
                ];
            }
        } catch (InvalidArgumentException $e) {
            return $this->error($request, $response, $e->getMessage(), 'invalid_probe_payload');
        } catch (Throwable $e) {
            return $this->error($request, $response, 'Probe report failed', 'probe_report_failed', 500);
        }

        return $this->success($request, $response, [
            'accepted' => count($results),
            'results' => $results,
        ]);
    }

    private function normalizeReportPayload(ServerRequest $request, array $payload): array
    {
        $rawResults = array_key_exists('results', $payload) ? $payload['results'] : [$payload];

        if (! is_array($rawResults)) {
            throw new InvalidArgumentException('results must be an array.');
        }

        $records = [];

        foreach ($rawResults as $rawResult) {
            if (! is_array($rawResult)) {
                throw new InvalidArgumentException('Each probe result must be an object.');
            }

            $record = $rawResult;
            $record['target_port'] = $this->parseTargetPort($record['target_port'] ?? null);
            $record['probe_region'] = $this->defaultField($record, $payload, $request, 'probe_region');
            $record['probe_provider'] = $this->defaultField($record, $payload, $request, 'probe_provider');
            $record['probe_location'] = $this->defaultField($record, $payload, $request, 'probe_location');

            $this->assertRequiredResultFields($record);
            $records[] = $record;
        }

        if ($records === []) {
            throw new InvalidArgumentException('At least one probe result is required.');
        }

        return $records;
    }

    private function defaultField(array $record, array $payload, ServerRequest $request, string $field): mixed
    {
        if (array_key_exists($field, $record)) {
            return $record[$field];
        }

        if (array_key_exists($field, $payload)) {
            return $payload[$field];
        }

        return $request->getAttribute($field);
    }

    private function assertRequiredResultFields(array $record): void
    {
        if (! is_numeric($record['node_id'] ?? null) || (int) $record['node_id'] <= 0) {
            throw new InvalidArgumentException('node_id is required.');
        }

        foreach (['probe_region', 'probe_type', 'target_host', 'status'] as $field) {
            if (! is_string($record[$field] ?? null) || trim($record[$field]) === '') {
                throw new InvalidArgumentException($field . ' is required.');
            }
        }
    }

    private function parseTargetPort(mixed $targetPort): int
    {
        if ($targetPort === null || $targetPort === '') {
            return 443;
        }

        if (! is_numeric($targetPort) || (int) $targetPort <= 0) {
            throw new InvalidArgumentException('target_port must be a positive integer.');
        }

        return max(1, (int) $targetPort);
    }

    private function allowedNodeIds(ServerRequest $request): array
    {
        $allowedNodeIds = $request->getAttribute('probe_allowed_node_ids');

        return is_array($allowedNodeIds) ? $allowedNodeIds : [];
    }

    private function success(ServerRequest $request, Response $response, array $data): ResponseInterface
    {
        return $response->withJson([
            'ret' => 1,
            'data' => $data,
            'request_id' => $this->getRequestId($request),
        ]);
    }

    private function error(
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

    private function getRequestId(ServerRequest $request): string
    {
        $requestId = $request->getHeaderLine('X-Request-Id');

        if ($requestId !== '') {
            return $requestId;
        }

        return uniqid('xnp_', true);
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
