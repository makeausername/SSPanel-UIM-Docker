<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\Node;
use App\Services\RateLimit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Throwable;
use function array_unique;
use function count;
use function hash;
use function hash_equals;
use function is_numeric;
use function json_encode;
use function parse_url;
use function preg_match;
use function strtolower;
use function trim;
use const PHP_URL_HOST;

final class NodeToken implements MiddlewareInterface
{
    public function __construct(private readonly mixed $rateChecker = null)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $nodeId = $this->nodeId($request);
        $token = $this->token($request);

        if ($nodeId === null || $token === null) {
            return $this->unauthorized('Invalid request.');
        }

        if (! $this->allowed('webapi_ip', $this->clientIp($request))
            || ! $this->allowed('webapi_key', hash('sha256', $token))) {
            return $this->unauthorized('Invalid request.');
        }

        if (! ($_ENV['webAPI'] ?? false) || ! $this->hostMatchesConfiguredApi($request)) {
            return $this->unauthorized('Invalid request.');
        }

        $node = (new Node())->find($nodeId);

        if ($node === null) {
            return $this->unauthorized('Invalid node.');
        }

        if ((int) $node->type === 0) {
            return $this->unauthorized('Node is disabled.', 403);
        }

        $ipMatchesNode = $this->ipMatchesNode($this->clientIp($request), $node);
        if (($_ENV['checkNodeIp'] ?? true) && ! $ipMatchesNode) {
            return $this->unauthorized('Invalid request IP.');
        }

        $nodePassword = trim((string) $node->password);
        $perNodeTokenValid = $nodePassword !== '' && hash_equals($nodePassword, $token);
        $legacyKey = trim((string) ($_ENV['muKey'] ?? ''));
        $legacyKeyValid = $legacyKey !== ''
            && $ipMatchesNode
            && hash_equals($legacyKey, $token);

        if (! $perNodeTokenValid && ! $legacyKeyValid) {
            return $this->unauthorized('Invalid request.');
        }

        return $handler->handle($request->withAttribute('legacy_node_id', (int) $node->id));
    }

    private function nodeId(ServerRequestInterface $request): ?int
    {
        $candidates = [];
        $queryNodeId = $request->getQueryParams()['node_id'] ?? null;
        $headerNodeId = trim($request->getHeaderLine('X-Node-Id'));

        foreach ([$queryNodeId, $headerNodeId] as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate) && (int) $candidate > 0) {
                $candidates[] = (int) $candidate;
            }
        }

        if (preg_match('#^/mod_mu/nodes/([0-9]+)/#', $request->getUri()->getPath(), $matches) === 1) {
            $candidates[] = (int) $matches[1];
        }

        $candidates = array_unique($candidates);

        return count($candidates) === 1 ? (int) $candidates[0] : null;
    }

    private function token(ServerRequestInterface $request): ?string
    {
        $authorization = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            $token = trim($matches[1]);

            return $token === '' ? null : $token;
        }

        $token = trim((string) ($request->getQueryParams()['key'] ?? ''));

        return $token === '' ? null : $token;
    }

    private function hostMatchesConfiguredApi(ServerRequestInterface $request): bool
    {
        $configuredHost = parse_url((string) ($_ENV['webAPIUrl'] ?? ''), PHP_URL_HOST);

        return $configuredHost !== null
            && $configuredHost !== false
            && hash_equals(strtolower($configuredHost), strtolower($request->getUri()->getHost()));
    }

    private function ipMatchesNode(string $ip, Node $node): bool
    {
        return ($node->ipv4 !== '' && hash_equals((string) $node->ipv4, $ip))
            || ($node->ipv6 !== '' && hash_equals((string) $node->ipv6, $ip));
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        return (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');
    }

    private function allowed(string $type, string $value): bool
    {
        if (! ($_ENV['enable_rate_limit'] ?? false)) {
            return true;
        }

        if (is_callable($this->rateChecker)) {
            return (bool) ($this->rateChecker)($type, $value);
        }

        try {
            return (new RateLimit())->checkRateLimit($type, $value);
        } catch (Throwable) {
            return false;
        }
    }

    private function unauthorized(string $message, int $status = 401): ResponseInterface
    {
        $response = AppFactory::determineResponseFactory()->createResponse($status);
        $response->getBody()->write((string) json_encode([
            'ret' => 0,
            'msg' => $message,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
