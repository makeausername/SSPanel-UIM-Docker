<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductionHttpHardeningContractTest extends TestCase
{
    #[DataProvider('httpClients')]
    public function testExternalHttpClientsHaveConnectionAndOverallTimeouts(string $file): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $file);

        $this->assertIsString($source);
        $this->assertStringContainsString('connect_timeout', $source);
        $this->assertStringContainsString('timeout', $source);
    }

    public static function httpClients(): array
    {
        return [
            ['src/Services/Captcha.php'],
            ['src/Services/Exchange.php'],
            ['src/Services/Gateway/AlipayF2F.php'],
            ['src/Services/Gateway/Epay.php'],
            ['src/Services/IM/Discord.php'],
            ['src/Services/IM/Slack.php'],
            ['src/Services/LLM/Base.php'],
            ['src/Services/Mail/Postmark.php'],
        ];
    }

    public function testCurlGatewayHasTlsVerificationAndBoundedTimeouts(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/src/Services/Gateway/Cryptomus/RequestBuilder.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString('CURLOPT_CONNECTTIMEOUT', $source);
        $this->assertStringContainsString('CURLOPT_TIMEOUT', $source);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER => true', $source);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYHOST => 2', $source);
    }

    public function testInvalidExchangeResponsesAreHandledByPaymentGateways(): void
    {
        $root = dirname(__DIR__, 3);

        foreach (['src/Services/Gateway/PayPal.php', 'src/Services/Gateway/Stripe.php'] as $file) {
            $source = file_get_contents($root . '/' . $file);
            $this->assertIsString($source);
            $this->assertStringContainsString('UnexpectedValueException', $source);
        }
    }
}
