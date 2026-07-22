<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use App\Models\XNodeAuditRule;
use App\Models\XNodeAuditRulePattern;
use InvalidArgumentException;
use function array_map;
use function array_values;
use function hash;
use function in_array;
use function is_numeric;
use function json_encode;
use function preg_match;
use function preg_split;
use function sort;
use function strtolower;
use function time;
use function trim;

final class XNodeAuditService
{
    public const MATCH_TYPES = ['protocol', 'domain_suffix', 'domain_regex', 'ip_cidr', 'port'];
    public const NETWORKS = ['any', 'tcp', 'udp'];
    public const ACTIONS = ['block', 'log_only'];
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    public const SCOPE_TYPES = ['all', 'node', 'group'];

    public function buildBundleForNode(int $nodeId): array
    {
        $node = (new Node())->find($nodeId);

        if ($node === null) {
            return $this->bundle([]);
        }

        $rules = (new XNodeAuditRule())
            ->where('enabled', 1)
            ->where(static function ($query) use ($node): void {
                $query->where('scope_type', 'all')
                    ->orWhere(static function ($nodeScope) use ($node): void {
                        $nodeScope->where('scope_type', 'node')->where('scope_value', (int) $node->id);
                    })
                    ->orWhere(static function ($groupScope) use ($node): void {
                        $groupScope->where('scope_type', 'group')->where('scope_value', (int) $node->node_group);
                    });
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $items = [];
        foreach ($rules as $rule) {
            $patterns = (new XNodeAuditRulePattern())
                ->where('rule_id', (int) $rule->id)
                ->orderBy('pattern')
                ->pluck('pattern')
                ->map(static fn ($pattern): string => (string) $pattern)
                ->values()
                ->toArray();

            if ($patterns === []) {
                continue;
            }

            $items[] = [
                'id' => (int) $rule->id,
                'name' => (string) $rule->name,
                'match_type' => (string) $rule->match_type,
                'patterns' => $patterns,
                'network' => (string) $rule->network,
                'action' => (string) $rule->action,
                'severity' => (string) $rule->severity,
                'priority' => (int) $rule->priority,
                'revision' => (int) $rule->revision,
            ];
        }

        return $this->bundle($items);
    }

    public function appliesToNode(XNodeAuditRule $rule, Node $node): bool
    {
        return match ((string) $rule->scope_type) {
            'all' => true,
            'node' => (int) $rule->scope_value === (int) $node->id,
            'group' => (int) $rule->scope_value === (int) $node->node_group,
            default => false,
        };
    }

    public function createRule(array $input): XNodeAuditRule
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $matchType = strtolower(trim((string) ($input['match_type'] ?? '')));
        $network = strtolower(trim((string) ($input['network'] ?? 'any')));
        $action = strtolower(trim((string) ($input['action'] ?? 'block')));
        $severity = strtolower(trim((string) ($input['severity'] ?? 'medium')));
        $scopeType = strtolower(trim((string) ($input['scope_type'] ?? 'all')));
        $scopeValue = $scopeType === 'all' ? null : $this->positiveInt($input['scope_value'] ?? null);
        $priority = is_numeric($input['priority'] ?? null) ? (int) $input['priority'] : 100;
        $patterns = $this->normalizePatterns($input['patterns'] ?? '', $matchType);

        if ($name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('规则名称不能为空且不能超过 255 个字符。');
        }
        if (! in_array($matchType, self::MATCH_TYPES, true)) {
            throw new InvalidArgumentException('不支持的匹配类型。');
        }
        if (! in_array($network, self::NETWORKS, true)) {
            throw new InvalidArgumentException('不支持的网络类型。');
        }
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('不支持的处理动作。');
        }
        if (! in_array($severity, self::SEVERITIES, true)) {
            throw new InvalidArgumentException('不支持的风险级别。');
        }
        if (! in_array($scopeType, self::SCOPE_TYPES, true) || ($scopeType !== 'all' && $scopeValue === null)) {
            throw new InvalidArgumentException('规则作用范围无效。');
        }
        if ($patterns === []) {
            throw new InvalidArgumentException('至少需要一个有效匹配项。');
        }

        return DB::connection()->transaction(function () use (
            $name,
            $description,
            $matchType,
            $network,
            $action,
            $severity,
            $scopeType,
            $scopeValue,
            $priority,
            $patterns,
            $input
        ): XNodeAuditRule {
            $now = time();
            $rule = new XNodeAuditRule();
            $rule->name = $name;
            $rule->description = $description;
            $rule->match_type = $matchType;
            $rule->network = $network;
            $rule->action = $action;
            $rule->severity = $severity;
            $rule->enabled = $this->boolValue($input['enabled'] ?? true) ? 1 : 0;
            $rule->source = 'admin';
            $rule->scope_type = $scopeType;
            $rule->scope_value = $scopeValue;
            $rule->priority = max(0, min(10000, $priority));
            $rule->revision = 1;
            $rule->managed = 0;
            $rule->created_at = $now;
            $rule->updated_at = $now;
            $rule->save();

            foreach ($patterns as $pattern) {
                (new XNodeAuditRulePattern())->insert([
                    'rule_id' => (int) $rule->id,
                    'pattern' => $pattern,
                    'created_at' => $now,
                ]);
            }

            return $rule;
        });
    }

    public function normalizePatterns(mixed $input, string $matchType): array
    {
        $raw = is_array($input) ? $input : (preg_split('/[\r\n,]+/', (string) $input) ?: []);
        $patterns = [];

        foreach ($raw as $candidate) {
            $pattern = trim((string) $candidate);
            if ($pattern === '') {
                continue;
            }

            if ($matchType === 'domain_suffix') {
                $pattern = strtolower(rtrim($pattern, '.'));
                if (str_starts_with($pattern, 'www.')) {
                    $pattern = substr($pattern, 4);
                }
                if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,62}$/', $pattern)) {
                    continue;
                }
            }

            if ($matchType === 'port' && ! preg_match('/^(?:[1-9][0-9]{0,4})(?:-(?:[1-9][0-9]{0,4}))?$/', $pattern)) {
                continue;
            }
            if ($matchType === 'port') {
                $ports = array_map('intval', explode('-', $pattern));
                if (max($ports) > 65535 || (count($ports) === 2 && $ports[0] > $ports[1])) {
                    continue;
                }
            }
            if ($matchType === 'protocol' && ! preg_match('/^[a-zA-Z0-9_.:+-]+$/', $pattern)) {
                continue;
            }
            if ($matchType === 'ip_cidr' && ! $this->validIPPattern($pattern)) {
                continue;
            }

            if (strlen($pattern) <= 255) {
                $patterns[$pattern] = $pattern;
            }
        }

        $patterns = array_values($patterns);
        sort($patterns, SORT_STRING);

        return $patterns;
    }

    private function bundle(array $rules): array
    {
        $canonical = json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $rulesHash = hash('sha256', $canonical === false ? '[]' : $canonical);
        $revision = 'sha256:' . $rulesHash;

        return [
            'schema_version' => 2,
            'revision' => $revision,
            'rules_hash' => $rulesHash,
            'rules' => $rules,
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function validIPPattern(string $pattern): bool
    {
        if (str_starts_with(strtolower($pattern), 'geoip:')) {
            return preg_match('/^geoip:[a-z0-9_-]+$/i', $pattern) === 1;
        }

        [$ip, $prefix] = array_pad(explode('/', $pattern, 2), 2, null);
        $version = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? 4 : 6;
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return $prefix === null || (ctype_digit($prefix) && (int) $prefix <= ($version === 4 ? 32 : 128));
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
