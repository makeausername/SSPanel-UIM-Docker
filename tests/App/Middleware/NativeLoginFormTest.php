<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\LoginFormFallback;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;

final class NativeLoginFormTest extends TestCase
{
    protected function setUp(): void
    {
        AppFactory::setResponseFactory(new HttpFactory());
        LoginFormFallback::pullError();
    }

    public function testConvertsSuccessfulNativeLoginToBrowserRedirect(): void
    {
        $request = $this->request();
        $source = new Response(200, ['HX-Redirect' => '/user']);

        $response = (new NativeLoginForm())->process($request, $this->handler($source));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/user', $response->getHeaderLine('Location'));
        $this->assertSame('', $response->getHeaderLine('HX-Redirect'));
    }

    public function testRedirectsNativeLoginErrorWithLocalizedFlashMessage(): void
    {
        $request = $this->request();
        $source = new Response(
            429,
            ['Content-Type' => 'application/json', 'Retry-After' => '60'],
            '{"ret":0,"msg":"Try again"}'
        );

        $response = (new NativeLoginForm())->process($request, $this->handler($source));

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/auth/login', $response->getHeaderLine('Location'));
        $this->assertSame('60', $response->getHeaderLine('Retry-After'));
        $this->assertSame('Try again', LoginFormFallback::pullError());
        $this->assertNull(LoginFormFallback::pullError());
    }

    public function testLeavesHtmxLoginResponseUnchanged(): void
    {
        $request = $this->request()->withHeader('HX-Request', 'true');
        $source = new Response(200, ['HX-Redirect' => '/user']);

        $response = (new NativeLoginForm())->process($request, $this->handler($source));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/user', $response->getHeaderLine('HX-Redirect'));
        $this->assertSame('', $response->getHeaderLine('Location'));
    }

    public function testMiddlewareIsRegisteredInPublicEntryPoint(): void
    {
        $entryPoint = file_get_contents(__DIR__ . '/../../../public/index.php');

        $this->assertIsString($entryPoint);
        $this->assertStringContainsString('$app->add(new NativeLoginForm());', $entryPoint);
    }

    private function request(): ServerRequestInterface
    {
        return (new HttpFactory())
            ->createServerRequest('POST', 'https://panel.example.com/auth/login')
            ->withParsedBody(['login_form' => '1']);
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new class($response) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}
