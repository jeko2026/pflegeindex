<?php

declare(strict_types=1);

namespace Tests\Unit\DirectoryCore;

use App\Platform\DirectoryCore\Domain\LocationScope;
use App\Platform\DirectoryCore\Domain\LocationScopeType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocationScopeTest extends TestCase
{
    public function test_it_creates_a_typed_city_scope_and_normalizes_its_identifier(): void
    {
        $scope = LocationScope::city('  potsdam  ');

        $this->assertSame(LocationScopeType::City, $scope->type);
        $this->assertSame('potsdam', $scope->identifier);
    }

    public function test_it_expresses_all_supported_scope_types_without_string_discriminators(): void
    {
        $this->assertSame(LocationScopeType::District, LocationScope::district('prignitz')->type);
        $this->assertSame(LocationScopeType::State, LocationScope::state('brandenburg')->type);
    }

    #[DataProvider('blankIdentifierProvider')]
    public function test_it_rejects_blank_identifiers(string $identifier): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocationScope::city($identifier);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blankIdentifierProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
        ];
    }
}
