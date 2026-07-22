<?php

declare(strict_types=1);

namespace App\Controllers;

use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    public function testHealthEndpointReturnsPlainTextSuccess(): void
    {
        $factory = new HttpFactory();
        $request = $factory->createServerRequest('GET', '/healthz');
        $response = $factory->createResponse();

        $result = (new HealthController())->index($request, $response, []);

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('text/plain; charset=utf-8', $result->getHeaderLine('Content-Type'));
        $this->assertSame('ok', (string) $result->getBody());
    }
}
