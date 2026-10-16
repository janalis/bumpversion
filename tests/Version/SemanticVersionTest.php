<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Tests\Version;

use Janalis\Bumpversion\Version\SemanticVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SemanticVersionTest extends TestCase
{
    public static function transitions(): iterable
    {
        yield ['1.2.3', null, null, '1.2.4'];
        yield ['1.2.3', 'minor', null, '1.3.0'];
        yield ['1.2.3', 'major', 'alpha', '2.0.0-alpha.1'];
        yield ['1.2.3', null, 'alpha', '1.2.4-alpha.1'];
        yield ['1.2.3-alpha.1', null, 'alpha', '1.2.3-alpha.2'];
        yield ['1.2.3-alphabet.9', null, 'alpha', '1.2.3-alpha.1'];
        yield ['1.2.3-alpha.9', null, 'beta', '1.2.3-beta.1'];
        yield ['1.2.3-beta.2', null, null, '1.2.3'];
        yield ['1.2.3+build.9', 'patch', null, '1.2.4'];
    }

    #[DataProvider('transitions')]
    public function testTransitions(string $current, ?string $type, ?string $pre, string $next): void
    {
        self::assertSame($next, (string) SemanticVersion::parse($current)->next($type, $pre));
    }

    public static function invalidVersions(): iterable
    {
        foreach (['1', '1.2', '01.2.3', '1.2.3-01', '1.2.3-alpha..1', '1.2.3_bad', '-1.2.3', '1.2.3+', "1.2.3\n"] as $version) {
            yield [$version];
        }
    }

    #[DataProvider('invalidVersions')]
    public function testRejectsInvalidVersions(string $version): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SemanticVersion::parse($version);
    }

    public function testBuildMetadataRoundTrip(): void
    {
        self::assertSame('1.2.3-rc.1+build.01', (string) SemanticVersion::parse('1.2.3-rc.1+build.01'));
    }

    public function testUnboundedNumericComponents(): void
    {
        self::assertSame('1000000000000000000000000000000.0.0', (string) SemanticVersion::parse('999999999999999999999999999999.9.9')->next('major'));
        self::assertSame('1.0.0-alpha.10000000000000000000000000', (string) SemanticVersion::parse('1.0.0-alpha.9999999999999999999999999')->next(null, 'alpha'));
    }

    public function testSpecificationPrecedenceAndBuildMetadata(): void
    {
        $ordered = ['1.0.0-alpha', '1.0.0-alpha.1', '1.0.0-alpha.beta', '1.0.0-beta', '1.0.0-beta.2', '1.0.0-beta.11', '1.0.0-rc.1', '1.0.0'];
        foreach ($ordered as $i => $version) {
            foreach ($ordered as $j => $other) {
                self::assertSame($i <=> $j, SemanticVersion::parse($version)->compare(SemanticVersion::parse($other)));
            }
        }
        self::assertSame(0, SemanticVersion::parse('1.0.0+a')->compare(SemanticVersion::parse('1.0.0+b')));
        self::assertSame(-1, SemanticVersion::parse('1.0.0-A')->compare(SemanticVersion::parse('1.0.0-a')));
        self::assertSame(-1, SemanticVersion::parse('1.0.0-99999999999999999999')->compare(SemanticVersion::parse('1.0.0-100000000000000000000')));
        foreach (['0.0.0', '1.0.0-0.3.7', '1.0.0-x.7.z.92', '1.0.0-x-y-z.--', '1.0.0+21AF26D3----117B344092BD'] as $version) {
            self::assertSame($version, (string) SemanticVersion::parse($version));
        }
    }

    public function testInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SemanticVersion::parse('1.0.0')->next('invalid');
    }

    public function testInvalidPrerelease(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SemanticVersion::parse('1.0.0')->next(null, 'alpha.1');
    }
}
