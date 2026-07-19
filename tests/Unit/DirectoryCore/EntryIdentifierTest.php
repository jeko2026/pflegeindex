<?php

declare(strict_types=1);

namespace Tests\Unit\DirectoryCore;

use App\Platform\DirectoryCore\Domain\EntryIdentifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EntryIdentifierTest extends TestCase
{
    public function test_it_accepts_and_normalizes_a_string_identifier(): void
    {
        $identifier = new EntryIdentifier('  facility-42  ');

        $this->assertSame('facility-42', $identifier->value());
    }

    public function test_it_accepts_a_numeric_identifier(): void
    {
        $identifier = new EntryIdentifier(42);

        $this->assertSame('42', $identifier->value());
    }

    public function test_it_rejects_an_empty_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EntryIdentifier('   ');
    }

    public function test_it_compares_identifiers_by_their_normalized_value(): void
    {
        $identifier = new EntryIdentifier('42');

        $this->assertTrue($identifier->equals(new EntryIdentifier(42)));
        $this->assertFalse($identifier->equals(new EntryIdentifier('43')));
    }
}
