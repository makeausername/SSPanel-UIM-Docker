<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use function hash;
use function strlen;

class NodeEnrollmentServiceTest extends TestCase
{
    /**
     * @covers App\Services\NodeEnrollmentService::hashToken
     */
    public function testHashTokenUsesSha256(): void
    {
        $service = new NodeEnrollmentService();

        $this->assertSame(hash('sha256', 'xn_example'), $service->hashToken('xn_example'));
        $this->assertNotSame('xn_example', $service->hashToken('xn_example'));
    }

    /**
     * @covers App\Services\NodeEnrollmentService::generateNodeToken
     */
    public function testGenerateNodeTokenUsesExpectedPrefix(): void
    {
        $service = new NodeEnrollmentService();
        $token = $service->generateNodeToken();

        $this->assertStringStartsWith('xn_', $token);
        $this->assertGreaterThan(32, strlen($token));
    }

    /**
     * @covers App\Services\NodeEnrollmentService::createEnrollTokenForNode
     */
    public function testCreateEnrollTokenRejectsInvalidNodeId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('node_id must be a positive integer.');

        NodeEnrollmentService::createEnrollTokenForNode(0);
    }
}
