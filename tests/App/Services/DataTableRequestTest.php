<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Slim\Http\ServerRequest;

final class DataTableRequestTest extends TestCase
{
    public function testDefaultsAreBoundedAndDeterministic(): void
    {
        $params = DataTableRequest::from($this->request(), ['id', 'ip'], 'id');

        self::assertSame(10, $params->length);
        self::assertSame(1, $params->page);
        self::assertSame(0, $params->draw);
        self::assertSame('', $params->search);
        self::assertSame('id', $params->orderBy);
        self::assertSame('desc', $params->orderDirection);
    }

    public function testLengthAndStartAreNormalized(): void
    {
        $zeroLength = DataTableRequest::from(
            $this->request(['length' => 0, 'start' => -10]),
            ['id'],
            'id'
        );
        $largeLength = DataTableRequest::from(
            $this->request(['length' => 500, 'start' => 250]),
            ['id'],
            'id'
        );

        self::assertSame(10, $zeroLength->length);
        self::assertSame(1, $zeroLength->page);
        self::assertSame(100, $largeLength->length);
        self::assertSame(3, $largeLength->page);
    }

    public function testAllowsOnlyWhitelistedOrderColumnsAndDirections(): void
    {
        $allowed = DataTableRequest::from(
            $this->request([
                'columns' => [
                    ['data' => 'id'],
                    ['data' => 'ip'],
                ],
                'order' => [
                    ['column' => 1, 'dir' => 'asc'],
                ],
            ]),
            ['id', 'ip'],
            'id'
        );
        $rejected = DataTableRequest::from(
            $this->request([
                'columns' => [
                    ['data' => 'untrusted_expression'],
                ],
                'order' => [
                    ['column' => 0, 'dir' => 'sideways'],
                ],
            ]),
            ['id', 'ip'],
            'id'
        );

        self::assertSame('ip', $allowed->orderBy);
        self::assertSame('asc', $allowed->orderDirection);
        self::assertSame('id', $rejected->orderBy);
        self::assertSame('desc', $rejected->orderDirection);
    }

    public function testSearchAndDrawAreNormalized(): void
    {
        $params = DataTableRequest::from(
            $this->request([
                'draw' => -5,
                'search' => ['value' => '  needle  '],
            ]),
            ['id'],
            'id'
        );

        self::assertSame(0, $params->draw);
        self::assertSame('needle', $params->search);
    }

    private function request(array $body = []): ServerRequest
    {
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'https://panel.example.com/admin/log')
            ->withParsedBody($body);

        return new ServerRequest($request);
    }
}
