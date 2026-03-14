<?php

declare(strict_types=1);

namespace viavario\ecadclient\tests;

use PHPUnit\Framework\TestCase;
use viavario\ecadclient\EcadProfession;

/**
 * Tests for the EcadProfession value object.
 */
class EcadProfessionTest extends TestCase
{
    /**
     * Builds an EcadProfession instance with sensible defaults.
     *
     * @param int|string $id
     * @param string $categoryCd
     * @param string $code
     * @param array<string, string> $description
     *
     * @return EcadProfession
     */
    private function makeProfession(
        $id = 1,
        string $categoryCd = 'CAT1',
        string $code = 'P001',
        array $description = ['textFr' => 'Médecin', 'textNl' => 'Arts']
    ): EcadProfession {
        return new EcadProfession($id, $categoryCd, $code, $description);
    }

    // -------------------------------------------------------------------------
    // Constructor / properties
    // -------------------------------------------------------------------------

    /**
     * Ensures scalar properties are set correctly when id is an integer.
     */
    public function testPropertiesAreSetCorrectlyWithIntegerId(): void
    {
        $profession = $this->makeProfession(42);

        $this->assertSame(42, $profession->id);
        $this->assertSame('CAT1', $profession->categoryCd);
        $this->assertSame('P001', $profession->code);
        $this->assertSame(['textFr' => 'Médecin', 'textNl' => 'Arts'], $profession->description);
    }

    /**
     * Ensures id supports string values.
     */
    public function testPropertiesAreSetCorrectlyWithStringId(): void
    {
        $profession = $this->makeProfession('abc-123');

        $this->assertSame('abc-123', $profession->id);
    }

    /**
     * Ensures description values can be empty strings.
     */
    public function testDescriptionCanBeEmpty(): void
    {
        $profession = $this->makeProfession(1, 'CAT', 'CODE', ['textFr' => '', 'textNl' => '']);

        $this->assertSame('', $profession->description['textFr']);
        $this->assertSame('', $profession->description['textNl']);
    }

    // -------------------------------------------------------------------------
    // toArray
    // -------------------------------------------------------------------------

    /**
     * Ensures toArray returns the expected shape and values.
     */
    public function testToArrayReturnsExpectedStructure(): void
    {
        $profession = $this->makeProfession(7, 'CAT2', 'P007', ['textFr' => 'Infirmier', 'textNl' => 'Verpleegkundige']);

        $expected = [
            'id'          => 7,
            'categoryCd'  => 'CAT2',
            'code'        => 'P007',
            'description' => ['textFr' => 'Infirmier', 'textNl' => 'Verpleegkundige'],
        ];

        $this->assertSame($expected, $profession->toArray());
    }

    /**
     * Ensures all required keys are present in toArray output.
     */
    public function testToArrayContainsAllRequiredKeys(): void
    {
        $profession = $this->makeProfession();
        $array      = $profession->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('categoryCd', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('description', $array);
    }

    /**
     * Ensures id type is preserved by toArray when id is a string.
     */
    public function testToArrayWithStringIdPreservesType(): void
    {
        $profession = $this->makeProfession('STR-ID');
        $this->assertSame('STR-ID', $profession->toArray()['id']);
    }
}
