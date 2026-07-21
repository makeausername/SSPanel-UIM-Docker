<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use App\Models\NodeToken;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use function bin2hex;
use function hash;
use function is_numeric;
use function is_string;
use function preg_match;
use function random_bytes;
use function strcasecmp;
use function time;
use function trim;

final class NodeEnrollmentService
{
    public function extractBearerToken(ServerRequestInterface $request): ?string
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) !== 1) {
            return null;
        }

        $token = trim($matches[1]);

        return $token === '' ? null : $token;
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function generateNodeToken(): string
    {
        return 'xn_' . bin2hex(random_bytes(32));
    }

    public function enroll(array $payload, ?string $enrollToken): array
    {
        if ($enrollToken === null || trim($enrollToken) === '') {
            throw new RuntimeException('Missing enroll token.');
        }

        $nodeId = $this->parseNodeId($payload['node_id'] ?? null);
        $domain = $this->parseDomain($payload['domain'] ?? null);
        $now = time();

        return DB::connection()->transaction(function () use ($enrollToken, $nodeId, $domain, $now): array {
            $enrollTokenRecord = (new NodeToken())
                ->where('token_hash', $this->hashToken($enrollToken))
                ->where('token_type', 'enroll')
                ->where('node_id', $nodeId)
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->where(static function ($query) use ($now): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                })
                ->lockForUpdate()
                ->first();

            if ($enrollTokenRecord === null) {
                throw new RuntimeException('Invalid enroll token.');
            }

            $node = (new Node())->where('id', $nodeId)->lockForUpdate()->first();

            if ($node === null) {
                throw new InvalidArgumentException('node_id does not exist.');
            }

            if ((int) $node->type === 0) {
                throw new InvalidArgumentException('node is disabled.');
            }

            $this->assertDomainMatchesNode($node, $domain);

            (new NodeToken())
                ->where('node_id', $nodeId)
                ->where('token_type', 'node')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $now]);

            $nodeToken = $this->generateNodeToken();
            $nodeTokenRecord = new NodeToken();
            $nodeTokenRecord->node_id = $nodeId;
            $nodeTokenRecord->token_hash = $this->hashToken($nodeToken);
            $nodeTokenRecord->token_type = 'node';
            $nodeTokenRecord->name = 'xnode-agent';
            $nodeTokenRecord->created_at = $now;
            $nodeTokenRecord->save();

            $enrollTokenRecord->used_at = $now;
            $enrollTokenRecord->save();

            return [
                'node_token' => $nodeToken,
                'panel_url' => $this->getPanelUrl(),
                'node_id' => $nodeId,
                'domain' => $domain,
                'report_interval_sec' => 60,
                'config_interval_sec' => 60,
            ];
        });
    }

    /**
     * Temporary developer helper used by the CLI until an admin action exists.
     *
     * Show the returned token once to the operator, then discard it.
     */
    public static function createEnrollTokenForNode(int $nodeId, ?int $ttlSeconds = 600): string
    {
        if ($nodeId <= 0) {
            throw new InvalidArgumentException('node_id must be a positive integer.');
        }

        $service = new self();
        $now = time();
        $enrollToken = 'xne_' . bin2hex(random_bytes(32));
        $tokenRecord = new NodeToken();
        $tokenRecord->node_id = $nodeId;
        $tokenRecord->token_hash = $service->hashToken($enrollToken);
        $tokenRecord->token_type = 'enroll';
        $tokenRecord->name = 'xnode-enroll';
        $tokenRecord->expires_at = $ttlSeconds === null ? null : $now + $ttlSeconds;
        $tokenRecord->created_at = $now;
        $tokenRecord->save();

        return $enrollToken;
    }

    /**
     * Show the returned token once to the operator, then discard it.
     */
    public static function createProbeToken(?int $ttlSeconds = 2592000): string
    {
        if ($ttlSeconds !== null && $ttlSeconds <= 0) {
            throw new InvalidArgumentException('ttl_seconds must be a positive integer.');
        }

        $service = new self();
        $now = time();
        $probeToken = 'xnp_' . bin2hex(random_bytes(32));
        $tokenRecord = new NodeToken();
        $tokenRecord->node_id = 0;
        $tokenRecord->token_hash = $service->hashToken($probeToken);
        $tokenRecord->token_type = 'probe';
        $tokenRecord->name = 'xnode-probe';
        $tokenRecord->expires_at = $ttlSeconds === null ? null : $now + $ttlSeconds;
        $tokenRecord->created_at = $now;
        $tokenRecord->save();

        return $probeToken;
    }

    /**
     * @param mixed $nodeId
     */
    private function parseNodeId($nodeId): int
    {
        if (! is_numeric($nodeId) || (int) $nodeId <= 0) {
            throw new InvalidArgumentException('node_id is required.');
        }

        return (int) $nodeId;
    }

    /**
     * @param mixed $domain
     */
    private function parseDomain($domain): string
    {
        if (! is_string($domain) || trim($domain) === '') {
            throw new InvalidArgumentException('domain is required.');
        }

        return trim($domain);
    }

    private function assertDomainMatchesNode(Node $node, string $domain): void
    {
        foreach (['server', 'domain'] as $field) {
            $value = $node->getAttribute($field);

            if (is_string($value) && trim($value) !== '' && strcasecmp(trim($value), $domain) !== 0) {
                throw new InvalidArgumentException('domain does not match node.');
            }
        }
    }

    private function getPanelUrl(): string
    {
        $panelUrl = $_ENV['baseUrl'] ?? '';

        return is_string($panelUrl) ? $panelUrl : '';
    }
}
