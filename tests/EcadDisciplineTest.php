<?php

declare(strict_types=1);

namespace viavario\ecadclient\tests;

use PHPUnit\Framework\TestCase;
use viavario\ecadclient\EcadDiscipline;

/**
 * Tests for the EcadDiscipline value object.
 */
class EcadDisciplineTest extends TestCase
{
    /**
     * Builds an EcadDiscipline instance with default values that can be overridden per test.
     *
     * @param int|string $id
     * @param string $typeC
     * @param string $professionCategoryCd
     * @param string $businessTypeCode
     * @param array<string, string> $description
     */
    private function makeDiscipline(
        $id = 10,
        string $typeC = 'TYPE_A',
        string $professionCategoryCd = 'CAT1',
        string $businessTypeCode = 'BTC1',
        array $description = ['textFr' => 'Chirurgie', 'textNl' => 'Chirurgie']
    ): EcadDiscipline {
        return new EcadDiscipline($id, $typeC, $professionCategoryCd, $businessTypeCode, $description);
    }

    // -------------------------------------------------------------------------
    // Constructor / properties
    // -------------------------------------------------------------------------

    /**
     * Verifies all constructor values are correctly exposed when using an integer id.
     */
    public function testPropertiesAreSetCorrectlyWithIntegerId(): void
    {
        $discipline = $this->makeDiscipline(10);

        $this->assertSame(10, $discipline->id);
        $this->assertSame('TYPE_A', $discipline->typeC);
        $this->assertSame('CAT1', $discipline->professionCategoryCd);
        $this->assertSame('BTC1', $discipline->businessTypeCode);
        $this->assertSame(['textFr' => 'Chirurgie', 'textNl' => 'Chirurgie'], $discipline->description);
    }

    /**
     * Verifies string ids are accepted and preserved.
     */
    public function testPropertiesAreSetCorrectlyWithStringId(): void
    {
        $discipline = $this->makeDiscipline('disc-99');

        $this->assertSame('disc-99', $discipline->id);
    }

    /**
     * Verifies empty localized description values are allowed.
     */
    public function testDescriptionCanContainEmptyStrings(): void
    {
        $discipline = $this->makeDiscipline(1, 'T', 'C', 'B', ['textFr' => '', 'textNl' => '']);

        $this->assertSame('', $discipline->description['textFr']);
        $this->assertSame('', $discipline->description['textNl']);
    }

    // -------------------------------------------------------------------------
    // toArray
    // -------------------------------------------------------------------------

    /**
     * Verifies toArray() returns the expected full payload.
     */
    public function testToArrayReturnsExpectedStructure(): void
    {
        $discipline = $this->makeDiscipline(
            5,
            'TYPE_B',
            'CAT2',
            'BTC2',
            ['textFr' => 'Pédiatrie', 'textNl' => 'Pediatrie']
        );

        $expected = [
            'id'                    => 5,
            'typeC'                 => 'TYPE_B',
            'professionCategoryCd'  => 'CAT2',
            'businessTypeCode'      => 'BTC2',
            'description'           => ['textFr' => 'Pédiatrie', 'textNl' => 'Pediatrie'],
        ];

        $this->assertSame($expected, $discipline->toArray());
    }

    /**
     * Verifies toArray() includes all expected top-level keys.
     */
    public function testToArrayContainsAllRequiredKeys(): void
    {
        $array = $this->makeDiscipline()->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('typeC', $array);
        $this->assertArrayHasKey('professionCategoryCd', $array);
        $this->assertArrayHasKey('businessTypeCode', $array);
        $this->assertArrayHasKey('description', $array);
    }

    /**
     * Verifies toArray() keeps string id values as strings.
     */
    public function testToArrayWithStringIdPreservesType(): void
    {
        $discipline = $this->makeDiscipline('STRING-ID');
        $this->assertSame('STRING-ID', $discipline->toArray()['id']);
    }

    /**
     * Verifies localized description keys are present in serialized output.
     */
    public function testToArrayDescriptionKeys(): void
    {
        $array = $this->makeDiscipline()->toArray();

        $this->assertArrayHasKey('textFr', $array['description']);
        $this->assertArrayHasKey('textNl', $array['description']);
    }
}
