<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Models\Config;
use App\Models\NodeRuntime;
use App\Services\Subscribe;
use Throwable;
use function base64_encode;
use function http_build_query;
use function is_array;
use function is_scalar;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function rawurlencode;
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

            $vless_url = $this->buildXNodeVlessRealityUrl($user, $node_raw);
            if ($vless_url !== null) {
                $links .= $vless_url . PHP_EOL;
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

    private function getRuntimeForNode(int $nodeId): ?NodeRuntime
    {
        if ($nodeId <= 0) {
            return null;
        }

        try {
            $runtime = (new NodeRuntime())->where('node_id', $nodeId)->first();
        } catch (Throwable) {
            return null;
        }

        return $runtime instanceof NodeRuntime ? $runtime : null;
    }

    private function parseFirstShortId(?string $shortIdsJson): ?string
    {
        if ($shortIdsJson === null || trim($shortIdsJson) === '') {
            return null;
        }

        $short_ids = json_decode($shortIdsJson, true);
        if (! is_array($short_ids)) {
            return null;
        }

        foreach ($short_ids as $short_id) {
            if (! is_scalar($short_id)) {
                continue;
            }

            $short_id = trim((string) $short_id);
            if ($short_id !== '' && $this->isValidShortId($short_id)) {
                return $short_id;
            }
        }

        return null;
    }

    private function buildXNodeVlessRealityUrl($user, $node): ?string
    {
        $runtime = $this->getRuntimeForNode((int) ($node->id ?? 0));
        if ($runtime === null) {
            return null;
        }

        $uuid = isset($user->uuid) ? trim((string) $user->uuid) : '';
        $server = isset($node->server) ? trim((string) $node->server) : '';
        $public_key = isset($runtime->public_key) ? trim((string) $runtime->public_key) : '';
        $short_id = $this->parseFirstShortId(
            is_string($runtime->short_ids_json ?? null) ? $runtime->short_ids_json : null
        );

        if ($uuid === '' || $server === '' || $public_key === '' || $short_id === null) {
            return null;
        }

        $query = [
            'encryption' => 'none',
            'security' => 'reality',
            'sni' => 'www.microsoft.com',
            'fp' => 'chrome',
            'pbk' => $public_key,
            'sid' => $short_id,
            'type' => 'tcp',
            'flow' => 'xtls-rprx-vision',
        ];

        $name = isset($node->name) ? (string) $node->name : '';

        return 'vless://' . $uuid . '@' . $server . ':443?'
            . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            . '#' . rawurlencode($name);
    }

    private function isValidShortId(string $shortId): bool
    {
        return preg_match('/^(?:[0-9a-fA-F]{2}){1,8}$/', $shortId) === 1;
    }
}
