<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class ConfigSecretTest extends TestCase
{
    private Capsule $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new Capsule();
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'default');
        $this->db->setAsGlobal();
        $this->db->bootEloquent();
        Capsule::schema()->create('config', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('item');
            $table->text('value')->nullable();
            $table->string('class');
            $table->string('type')->default('string');
        });
        Capsule::table('config')->insert([
            ['item' => 'smtp_password', 'value' => 'secret-value', 'class' => 'email', 'type' => 'string'],
            ['item' => 'smtp_host', 'value' => 'smtp.example.com', 'class' => 'email', 'type' => 'string'],
        ]);
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();
        parent::tearDown();
    }

    public function testAdminConfigMasksAndPreservesSecrets(): void
    {
        $settings = Config::getAdminClass('email');
        $this->assertSame(Config::SECRET_MASK, $settings['smtp_password']);
        $this->assertSame('smtp.example.com', $settings['smtp_host']);

        $this->assertTrue(Config::setFromAdmin('smtp_password', Config::SECRET_MASK));
        $this->assertSame('secret-value', Config::obtain('smtp_password'));
        $this->assertTrue(Config::setFromAdmin('smtp_password', 'replacement'));
        $this->assertSame('replacement', Config::obtain('smtp_password'));
    }

    public function testSecretClassifierDoesNotMaskPublicKeys(): void
    {
        $this->assertTrue(Config::isSecretItem('stripe_api_key'));
        $this->assertTrue(Config::isSecretItem('telegram_token'));
        $this->assertFalse(Config::isSecretItem('turnstile_sitekey'));
        $this->assertFalse(Config::isSecretItem('f2f_pay_public_key'));
        $this->assertFalse(Config::isSecretItem('enable_reset_password_captcha'));
    }
}
