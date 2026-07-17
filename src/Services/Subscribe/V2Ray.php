<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Models\Config;
use App\Services\NodeProfileService;
use App\Services\Subscribe;
use App\Services\XNodeRealityMetadataService;
use function base64_encode;
use function http_build_query;
use function is_array;
use function is_numeric;
use function is_scalar;
use function json_decode;
use function json_encode;
use function rawurlencode;
use function strtolower;
use function trim;
use const PHP_EOL;
use const PHP_QUERY_RFC3986;

final class V2Ray extends Base
{
    public function getContent($user): string
    {
        $links = '';
        //判断是否开启V2Ray订阅
        if (! Config::obtain('enable_v2_sub')) {
            return $links;
        }

        $nodes_raw = Subscribe::getUserNodes($user);

        foreach ($nodes_raw as $node_raw) {
            $node_custom_config = json_decode($node_raw->custom_config, true);
            $node_custom_config = is_array($node_custom_config) ? $node_custom_config : null;

            if ((int) $node_raw->sort === 11) {
                $links .= $this->buildLegacyVmessUrl($user, $node_raw, $node_custom_config) . PHP_EOL;
            }

            $xnodeUrl = $this->buildXNodeVlessRealityUrl($user, $node_raw);
            if ($xnodeUrl !== null) {
                $links .= $xnodeUrl . PHP_EOL;
            }
        }

        return $links;
    }

    private function buildLegacyVmessUrl($user, $node_raw, ?array $node_custom_config): string
    {
        $v2_port = $node_custom_config['offset_port_user'] ?? ($node_custom_config['offset_port_node'] ?? 443);
        $security = $node_custom_config['security'] ?? 'none';
        $network = $node_custom_config['network'] ?? '';
        $header = $node_custom_config['header'] ?? ['type' => 'none'];
        $header_type = $header['type'] ?? '';
        $host = $node_custom_config['header']['request']['headers']['Host'][0] ?? $node_custom_config['host'] ?? '';
        $path = $node_custom_config['header']['request']['path'][0] ?? $node_custom_config['path'] ?? '/';

        $v2rayn_array = [
            'v' => '2',
            'ps' => $node_raw->name,
            'add' => $node_raw->server,
            'port' => $v2_port,
            'id' => $user->uuid,
            'aid' => 0,
            'net' => $network,
            'type' => $header_type,
            'host' => $host,
            'path' => $path,
            'tls' => $security,
        ];

        return 'vmess://' . base64_encode(json_encode($v2rayn_array));
    }

    private function buildXNodeVlessRealityUrl($user, $node): ?string
    {
        $nodeId = (int) ($node->id ?? 0);
        $metadata = new XNodeRealityMetadataService();
        $runtime = $metadata->selectUsableRuntimeForNode($nodeId);
        if ($runtime === null) {
            return null;
        }

        $uuid = isset($user->uuid) ? trim((string) $user->uuid) : '';
        $server = isset($node->server) ? trim((string) $node->server) : '';
        $publicKey = $metadata->normalizePublicKey($runtime->getAttribute('public_key'));
        $shortIds = $metadata->normalizeShortIds($runtime->getAttribute('short_ids_json'));
        $profileConfig = (new NodeProfileService())->buildDefaultConfig($nodeId, $server);
        $profile = $profileConfig['profile'] ?? null;
        $reality = $profileConfig['reality'] ?? null;

        if (
            $uuid === ''
            || $server === ''
            || $publicKey === null
            || $shortIds === null
            || ! is_array($profile)
            || ! is_array($reality)
        ) {
            return null;
        }

        $port = $profile['port'] ?? null;
        $flow = $this->profileString($profile['flow'] ?? null);
        $security = $this->profileString($profile['security'] ?? null);
        $networkType = $this->mapProfileNetworkToUriType($profile['network'] ?? null);
        $serverNames = $reality['server_names'] ?? null;
        $sni = is_array($serverNames) ? $this->profileString($serverNames[0] ?? null) : null;
        $fingerprint = $this->profileString($reality['fingerprint'] ?? null);

        if (
            ! is_numeric($port)
            || (int) $port <= 0
            || $flow === null
            || $security === null
            || $networkType === null
            || $sni === null
            || $fingerprint === null
        ) {
            return null;
        }

        $query = [
            'encryption' => 'none',
            'security' => $security,
            'sni' => $sni,
            'fp' => $fingerprint,
            'pbk' => $publicKey,
            'sid' => $shortIds[0],
            'type' => $networkType,
            'flow' => $flow,
        ];

        $name = isset($node->name) ? (string) $node->name : '';

        return 'vless://' . $uuid . '@' . $server . ':' . (int) $port . '?'
            . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            . '#' . rawurlencode($name);
    }

    private function mapProfileNetworkToUriType(mixed $network): ?string
    {
        $network = $this->profileString($network);

        return match ($network === null ? null : strtolower($network)) {
            'raw' => 'tcp',
            'tcp' => 'tcp',
            default => null,
        };
    }

    private function profileString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
