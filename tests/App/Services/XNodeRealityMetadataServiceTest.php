<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\TestCase;

class XNodeRealityMetadataServiceTest extends TestCase
{
    private const PUBLIC_KEY = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    private const EXPECTED_HASH = '15190bdb7c73c3b9f12a4c483e3205ce851f4bee063e99a02c09e5438473e50f';

    public function testFixedVectorHashMatchesCanonicalRealityFingerprint(): void
    {
        $hash = (new XNodeRealityMetadataService())->calculateRealityHash(self::PUBLIC_KEY, [
            'fedcba9876543210',
            '0123456789abcdef',
        ]);

        $this->assertSame(self::EXPECTED_HASH, $hash);
    }

    public function testShortIdOrderDoesNotChangeHash(): void
    {
        $service = new XNodeRealityMetadataService();

        $this->assertSame(
            $service->calculateRealityHash(self::PUBLIC_KEY, ['fedcba9876543210', '0123456789abcdef']),
            $service->calculateRealityHash(self::PUBLIC_KEY, ['0123456789abcdef', 'fedcba9876543210'])
        );
    }

    public function testDuplicateShortIdsAreRemovedAndSorted(): void
    {
        $shortIds = (new XNodeRealityMetadataService())->normalizeShortIds([
            'fedcba9876543210',
            '0123456789abcdef',
            'fedcba9876543210',
        ]);

        $this->assertSame(['0123456789abcdef', 'fedcba9876543210'], $shortIds);
    }

    public function testUppercaseShortIdsAreNormalizedToLowercase(): void
    {
        $service = new XNodeRealityMetadataService();
        $shortIds = $service->normalizeShortIds(['ABCDEF']);

        $this->assertSame(['abcdef'], $shortIds);
        $this->assertSame(['12'], $service->normalizeShortIds(['12']));
    }

    public function testInvalidPublicKeyIsRejected(): void
    {
        $service = new XNodeRealityMetadataService();

        $this->assertFalse($service->validatePublicKey('short-key'));
        $this->assertFalse($service->validatePublicKey(self::PUBLIC_KEY . '='));
    }

    public function testMalformedShortIdsJsonIsRejected(): void
    {
        $this->assertNull((new XNodeRealityMetadataService())->normalizeShortIds('{malformed-json'));
    }

    public function testInvalidShortIdRejectsEntireMetadataSet(): void
    {
        $service = new XNodeRealityMetadataService();

        $this->assertNull($service->normalizeShortIds(['0123456789abcdef', 'xyz']));
        $this->assertNull($service->calculateRealityHash(self::PUBLIC_KEY, ['xyz']));
    }

    public function testMismatchedRuntimeRealityHashIsRejected(): void
    {
        $runtime = [
            'public_key' => self::PUBLIC_KEY,
            'short_ids_json' => '["0123456789abcdef","fedcba9876543210"]',
            'reality_hash' => str_repeat('0', 64),
        ];

        $this->assertFalse((new XNodeRealityMetadataService())->validateRuntimeMetadata($runtime));
    }
}
