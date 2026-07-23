<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Node;
use App\Models\User;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class AdminSecurityRegressionTest extends TestCase
{
    private Capsule $db;
    private HttpFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new HttpFactory();
        $this->db = new Capsule();
        $this->db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ], 'default');
        $this->db->setAsGlobal();
        $this->db->bootEloquent();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        global $user;
        $user = null;
        Capsule::connection()->disconnect();
        parent::tearDown();
    }

    public function testUserAjaxReturnsOnlyTheTableProjection(): void
    {
        $this->seedActor('support');
        Capsule::table('user')->insert($this->userRow(2, 'target@example.com', false));

        $response = (new UserController())->ajax($this->request('POST'), $this->response(), []);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $row = $payload['users'][0];

        $this->assertSame([
            'op', 'id', 'user_name', 'email', 'money', 'ref_by', 'transfer_enable',
            'transfer_used', 'class', 'is_admin', 'is_banned', 'is_inactive',
            'reg_date', 'class_expire',
        ], array_keys($row));
        $this->assertSame('', $row['op']);
        $this->assertArrayNotHasKey('pass', $row);
        $this->assertArrayNotHasKey('passwd', $row);
        $this->assertArrayNotHasKey('uuid', $row);
        $this->assertArrayNotHasKey('api_token', $row);
    }

    public function testAdministratorCannotChangeAnotherAdminPassword(): void
    {
        $this->seedActor('administrator');
        $target = $this->userRow(2, 'owner@example.com', true);
        $target['admin_role'] = 'owner';
        $target['pass'] = 'original-hash';
        Capsule::table('user')->insert($target);

        $request = $this->request('PUT', [
            'pass' => 'replacement-password',
            'is_admin' => 'true',
            'admin_role' => 'owner',
        ]);
        $response = (new UserController())->update($request, $this->response(), ['id' => 2]);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $payload['ret']);
        $this->assertSame('original-hash', Capsule::table('user')->find(2)->pass);
    }

    public function testReadOnlyGiftCardsAndCouponsAreMaskedAndProjected(): void
    {
        $this->seedActor('read_only');
        Capsule::table('gift_card')->insert([
            'id' => 1,
            'card' => 'GIFT-SECRET',
            'balance' => 100,
            'create_time' => 1,
            'status' => 0,
            'use_time' => 0,
            'use_user' => 0,
        ]);
        Capsule::table('user_coupon')->insert([
            'id' => 1,
            'code' => 'COUPON-SECRET',
            'content' => json_encode(['type' => 'fixed', 'value' => '10.00'], JSON_THROW_ON_ERROR),
            'limit' => json_encode([
                'product_id' => '',
                'use_time' => -1,
                'total_use_time' => -1,
                'new_user' => 0,
                'disabled' => 0,
            ], JSON_THROW_ON_ERROR),
            'use_count' => 0,
            'create_time' => 1,
            'expire_time' => 0,
        ]);

        $giftResponse = (new GiftCardController())->ajax($this->request('POST'), $this->response(), []);
        $gift = json_decode((string) $giftResponse->getBody(), true, 512, JSON_THROW_ON_ERROR)['giftcards'][0];
        $couponResponse = (new CouponController())->ajax($this->request('POST'), $this->response(), []);
        $coupon = json_decode((string) $couponResponse->getBody(), true, 512, JSON_THROW_ON_ERROR)['coupons'][0];

        $this->assertSame('••••••••', $gift['card']);
        $this->assertSame('', $gift['op']);
        $this->assertSame('••••••••', $coupon['code']);
        $this->assertSame('', $coupon['op']);
        $this->assertArrayNotHasKey('content', $coupon);
        $this->assertArrayNotHasKey('limit', $coupon);
    }

    public function testSensitiveModelsHideCredentialsByDefault(): void
    {
        $user = new User();
        $user->setRawAttributes([
            'id' => 1,
            'email' => 'safe@example.com',
            'pass' => 'login-hash',
            'passwd' => 'node-secret',
            'uuid' => 'uuid-secret',
            'api_token' => 'api-secret',
            'ga_token' => 'mfa-secret',
        ]);
        $node = new Node();
        $node->setRawAttributes(['id' => 1, 'name' => 'node', 'password' => 'backend-secret']);

        $this->assertSame(['id' => 1, 'email' => 'safe@example.com'], $user->toArray());
        $this->assertSame(['id' => 1, 'name' => 'node'], $node->toArray());
    }

    private function seedActor(string $role): void
    {
        global $user;
        $row = $this->userRow(1, 'actor@example.com', true);
        $row['admin_role'] = $role;
        Capsule::table('user')->insert($row);
        $user = (new User())->find(1);
        $user->isLogin = true;
    }

    private function request(string $method, array $body = []): ServerRequest
    {
        $request = $this->factory->createServerRequest($method, 'https://panel.example.com/admin');
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        return new ServerRequest($request);
    }

    private function response(): Response
    {
        return new Response($this->factory->createResponse(), $this->factory);
    }

    private function userRow(int $id, string $email, bool $isAdmin): array
    {
        return [
            'id' => $id,
            'user_name' => 'user-' . $id,
            'email' => $email,
            'pass' => 'login-hash',
            'passwd' => 'node-secret',
            'uuid' => 'uuid-' . $id,
            'api_token' => 'api-' . $id,
            'ga_token' => 'ga-' . $id,
            'u' => 0,
            'd' => 0,
            'transfer_enable' => 1024,
            'money' => '0.00',
            'ref_by' => 0,
            'class' => 0,
            'is_admin' => $isAdmin ? 1 : 0,
            'admin_role' => $isAdmin ? 'administrator' : null,
            'is_banned' => 0,
            'is_inactive' => 0,
            'reg_date' => '2026-01-01 00:00:00',
            'class_expire' => '2026-01-01 00:00:00',
        ];
    }

    private function createSchema(): void
    {
        Capsule::schema()->create('user', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('user_name');
            $table->string('email');
            $table->string('pass');
            $table->string('passwd');
            $table->string('uuid');
            $table->string('api_token');
            $table->string('ga_token');
            $table->bigInteger('u');
            $table->bigInteger('d');
            $table->bigInteger('transfer_enable');
            $table->decimal('money', 12, 2);
            $table->integer('ref_by');
            $table->integer('class');
            $table->boolean('is_admin');
            $table->string('admin_role')->nullable();
            $table->boolean('is_banned');
            $table->boolean('is_inactive');
            $table->string('reg_date');
            $table->string('class_expire');
        });
        Capsule::schema()->create('gift_card', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('card');
            $table->integer('balance');
            $table->integer('create_time');
            $table->boolean('status');
            $table->integer('use_time');
            $table->integer('use_user');
        });
        Capsule::schema()->create('user_coupon', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('code');
            $table->text('content');
            $table->text('limit');
            $table->integer('use_count');
            $table->integer('create_time');
            $table->integer('expire_time');
        });
    }
}
