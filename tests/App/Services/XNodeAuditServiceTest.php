<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class XNodeAuditServiceTest extends TestCase
{
    private Capsule $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Capsule();
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'default');
        $this->db->setAsGlobal();
        $this->db->bootEloquent();

        Capsule::schema()->create('node', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('node_group')->default(0);
        });
        Capsule::schema()->create('xnode_audit_rules', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('match_type');
            $table->string('network');
            $table->string('action');
            $table->string('severity');
            $table->integer('enabled');
            $table->string('scope_type');
            $table->integer('scope_value')->nullable();
            $table->integer('priority');
            $table->integer('revision');
        });
        Capsule::schema()->create('xnode_audit_rule_patterns', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('rule_id');
            $table->string('pattern');
            $table->integer('created_at');
        });
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();
        parent::tearDown();
    }

    public function testBundleIncludesOnlyEnabledApplicableRulesAndHasStableHash(): void
    {
        Capsule::table('node')->insert(['id' => 7, 'node_group' => 3]);
        $this->seedRule(1, 'all', null, 1, 'bittorrent');
        $this->seedRule(2, 'group', 3, 1, 'example.com');
        $this->seedRule(3, 'group', 4, 1, 'wrong.example');
        $this->seedRule(4, 'all', null, 0, 'disabled.example');

        $service = new XNodeAuditService();
        $first = $service->buildBundleForNode(7);
        $second = $service->buildBundleForNode(7);

        $this->assertSame(2, $first['schema_version']);
        $this->assertSame([1, 2], array_column($first['rules'], 'id'));
        $this->assertSame($first['rules_hash'], $second['rules_hash']);
        $this->assertSame('sha256:' . $first['rules_hash'], $first['revision']);
    }

    public function testDomainSuffixNormalizationRemovesWwwAndDuplicates(): void
    {
        $patterns = (new XNodeAuditService())->normalizePatterns(
            "www.Example.com\nexample.com\nsub.example.org.\ninvalid domain",
            'domain_suffix'
        );

        $this->assertSame(['example.com', 'sub.example.org'], $patterns);
    }

    private function seedRule(int $id, string $scopeType, ?int $scopeValue, int $enabled, string $pattern): void
    {
        Capsule::table('xnode_audit_rules')->insert([
            'id' => $id,
            'name' => 'rule-' . $id,
            'match_type' => $id === 1 ? 'protocol' : 'domain_suffix',
            'network' => 'any',
            'action' => 'block',
            'severity' => 'high',
            'enabled' => $enabled,
            'scope_type' => $scopeType,
            'scope_value' => $scopeValue,
            'priority' => $id * 10,
            'revision' => 1,
        ]);
        Capsule::table('xnode_audit_rule_patterns')->insert([
            'rule_id' => $id,
            'pattern' => $pattern,
            'created_at' => 1,
        ]);
    }
}
