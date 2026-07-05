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
use function count;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_last_error;
use function max;
use function time;
use function trim;
use function uniqid;
use const JSON_ERROR_NONE;

final class ProbeApiV1Controller extends BaseController
{
    /**
     * POST /probe/api/v1/report
     */
    public function report(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        [$payload, $invalidJson] = $this->readJsonBody($request);

        if ($invalidJson) {
            return $this->validationError($request, $response, 'Invalid JSON body.', 'invalid_json');
        }

        if ($payload === null || ! is_array($payload['results'] ?? null)) {
            return $this->validationError(
                $request,
                $response,
                'Invalid probe report payload.',
                'invalid_probe_report_payload'
            );
        }

        $probeRegion = $this->requiredString($payload['probe_region'] ?? null);

        if ($probeRegion === null) {
            return $this->validationError(
                $request,
                $response,
                'Invalid probe report payload.',
                'invalid_probe_report_payload'
            );
        }

        $probeProvider = $this->optionalString($payload['probe_provider'] ?? null);
        $probeLocation = $this->optionalString($payload['probe_location'] ?? null);
        $accepted = 0;
        $skipped = 0;
        $total = count($payload['results']);
        $now = time();

        foreach ($payload['results'] as $result) {
            if (! is_array($result)) {
                $skipped++;
                continue;
            }

            $resultPayload = $this->normalizeResultPayload($result, $probeRegion, $probeProvider, $probeLocation, $now);

            if ($resultPayload === null) {
                $skipped++;
                continue;
            }

            try {
                NodeProbeService::recordResult($resultPayload, true);
                $accepted++;
            } catch (InvalidArgumentException) {
                $skipped++;
            } catch (Throwable) {
                return $this->validationError($request, $response, 'Probe report failed.', 'probe_report_failed', 500);
            }
        }

        return $this->success($request, $response, [
            'accepted' => $accepted,
            'skipped' => $skipped,
            'total' => $total,
        ]);
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

    private function normalizeResultPayload(
        array $result,
        string $probeRegion,
        ?string $probeProvider,
        ?string $probeLocation,
        int $now
    ): ?array {
        $nodeId = $this->positiveInt($result['node_id'] ?? null);
        $targetHost = $this->requiredString($result['target_host'] ?? null);
        $status = $this->requiredString($result['status'] ?? null);

        if ($nodeId === null || $targetHost === null || $status === null) {
            return null;
        }

        if (! NodeProbeService::isAllowedStatus($status)) {
            return null;
        }

        $probeType = $this->optionalString($result['probe_type'] ?? null) ?? 'external_tcp';

        return [
            'node_id' => $nodeId,
            'probe_region' => $probeRegion,
            'probe_provider' => $probeProvider,
            'probe_location' => $probeLocation,
            'probe_type' => $probeType,
            'target_host' => $targetHost,
            'target_port' => $this->targetPort($result['target_port'] ?? null),
            'status' => $status,
            'latency_ms' => $result['latency_ms'] ?? null,
            'error' => $result['error'] ?? null,
            'checked_at' => $this->checkedAt($result['checked_at'] ?? null, $now),
        ];
    }

    /**
     * @return array{0:?array,1:bool}
     */
    private function readJsonBody(ServerRequest $request): array
    {
        $body = trim($request->getBody()->__toString());

        if ($body === '') {
            return [[], false];
        }

        $payload = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [null, true];
        }

        return is_array($payload) ? [$payload, false] : [null, false];
    }

    private function requiredString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

    private function targetPort(mixed $value): int
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return 443;
        }

        return (int) $value;
    }

    private function checkedAt(mixed $value, int $default): int
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return $default;
        }

        return max(1, (int) $value);
    }

    private function getRequestId(ServerRequest $request): string
    {
        $requestId = $request->getHeaderLine('X-Request-Id');

        if ($requestId !== '') {
            return $requestId;
        }

        return uniqid('xn_', true);
    }
}
