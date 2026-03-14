<?php

declare(strict_types=1);

namespace viavario\ecadclient\tests;

use DateTime;
use PHPUnit\Framework\TestCase;
use viavario\ecadclient\EcadDiscipline;
use viavario\ecadclient\EcadProfession;
use viavario\ecadclient\EcadResult;

/**
 * Tests for the EcadResult value object.
 */
class EcadResultTest extends TestCase
{
    /**
     * Create a default profession fixture.
     */
    private function makeProfession(): EcadProfession
    {
        return new EcadProfession(
            1,
            'CAT1',
            'P001',
            ['textFr' => 'Médecin', 'textNl' => 'Arts']
        );
    }

    /**
     * Create a default discipline fixture.
     *
     * @param int $id Discipline identifier.
     */
    private function makeDiscipline(int $id = 10): EcadDiscipline
    {
        return new EcadDiscipline(
            $id,
            'TYPE_A',
            'CAT1',
            'BTC1',
            ['textFr' => 'Chirurgie', 'textNl' => 'Chirurgie']
        );
    }

    /**
     * Create a default result fixture.
     *
     * @param string               $lastname          Professional last name.
     * @param string               $firstname         Professional first name.
     * @param EcadProfession|null  $profession        Profession fixture to use.
     * @param array<int, EcadDiscipline> $disciplines Discipline fixtures.
     * @param DateTime|null        $visaDateFrom      Visa start date.
     * @param bool                 $visaDispensation  Visa dispensation flag.
     * @param array<int, array<string, mixed>> $practiceAddress Practice address payload.
     */
    private function makeResult(
        string $lastname = 'Dupont',
        string $firstname = 'Jean',
        ?EcadProfession $profession = null,
        array $disciplines = [],
        ?DateTime $visaDateFrom = null,
        bool $visaDispensation = false,
        array $practiceAddress = []
    ): EcadResult {
        return new EcadResult(
            $lastname,
            $firstname,
            $profession ?? $this->makeProfession(),
            $disciplines,
            $visaDateFrom ?? new DateTime('2020-01-01'),
            $visaDispensation,
            $practiceAddress
        );
    }

    // -------------------------------------------------------------------------
    // Constructor / properties
    // -------------------------------------------------------------------------

    /**
     * Ensure constructor arguments are stored on public properties.
     */
    public function testPropertiesAreSetCorrectly(): void
    {
        $profession   = $this->makeProfession();
        $discipline   = $this->makeDiscipline();
        $visaDateFrom = new DateTime('2021-06-15');
        $address      = [['street' => 'Rue de la Loi', 'city' => 'Brussels']];

        $result = new EcadResult(
            'Martin',
            'Sophie',
            $profession,
            [$discipline],
            $visaDateFrom,
            true,
            $address
        );

        $this->assertSame('Martin', $result->lastname);
        $this->assertSame('Sophie', $result->firstname);
        $this->assertSame($profession, $result->profession);
        $this->assertCount(1, $result->disciplines);
        $this->assertSame($discipline, $result->disciplines[0]);
        $this->assertSame($visaDateFrom, $result->visa['dateFrom']);
        $this->assertTrue($result->visa['dispensation']);
        $this->assertSame($address, $result->practiceAddress);
    }

    /**
     * Ensure the visa structure contains the expected keys and values.
     */
    public function testVisaStructureIsCorrect(): void
    {
        $date   = new DateTime('2019-03-10');
        $result = $this->makeResult('Dupont', 'Jean', null, [], $date, true);

        $this->assertIsArray($result->visa);
        $this->assertArrayHasKey('dateFrom', $result->visa);
        $this->assertArrayHasKey('dispensation', $result->visa);
        $this->assertSame($date, $result->visa['dateFrom']);
        $this->assertTrue($result->visa['dispensation']);
    }

    /**
     * Ensure disciplines can be provided as an empty collection.
     */
    public function testDisciplinesCanBeEmpty(): void
    {
        $result = $this->makeResult('Dupont', 'Jean', null, []);
        $this->assertSame([], $result->disciplines);
    }

    /**
     * Ensure multiple discipline entries are preserved in order.
     */
    public function testDisciplinesCanContainMultipleEntries(): void
    {
        $d1 = $this->makeDiscipline(1);
        $d2 = $this->makeDiscipline(2);
        $d3 = $this->makeDiscipline(3);

        $result = $this->makeResult('Dupont', 'Jean', null, [$d1, $d2, $d3]);

        $this->assertCount(3, $result->disciplines);
        $this->assertSame($d1, $result->disciplines[0]);
        $this->assertSame($d2, $result->disciplines[1]);
        $this->assertSame($d3, $result->disciplines[2]);
    }

    /**
     * Ensure practiceAddress can be provided as an empty array.
     */
    public function testPracticeAddressCanBeEmpty(): void
    {
        $result = $this->makeResult('Dupont', 'Jean', null, [], null, false, []);
        $this->assertSame([], $result->practiceAddress);
    }

    // -------------------------------------------------------------------------
    // toArray
    // -------------------------------------------------------------------------

    /**
     * Ensure toArray includes all top-level required keys.
     */
    public function testToArrayContainsAllRequiredKeys(): void
    {
        $array = $this->makeResult()->toArray();

        $this->assertArrayHasKey('lastname', $array);
        $this->assertArrayHasKey('firstname', $array);
        $this->assertArrayHasKey('profession', $array);
        $this->assertArrayHasKey('disciplines', $array);
        $this->assertArrayHasKey('visa', $array);
        $this->assertArrayHasKey('practiceAddress', $array);
    }

    /**
     * Ensure toArray returns expected scalar name values.
     */
    public function testToArrayReturnsCorrectScalarValues(): void
    {
        $array = $this->makeResult('Dupont', 'Jean')->toArray();

        $this->assertSame('Dupont', $array['lastname']);
        $this->assertSame('Jean', $array['firstname']);
    }

    /**
     * Ensure toArray maps profession to an associative array.
     */
    public function testToArrayProfessionIsAnArray(): void
    {
        $array = $this->makeResult()->toArray();

        $this->assertIsArray($array['profession']);
        $this->assertArrayHasKey('id', $array['profession']);
        $this->assertArrayHasKey('categoryCd', $array['profession']);
        $this->assertArrayHasKey('code', $array['profession']);
        $this->assertArrayHasKey('description', $array['profession']);
    }

    /**
     * Ensure toArray maps each discipline into an associative array.
     */
    public function testToArrayDisciplinesAreMappedToArrays(): void
    {
        $result = $this->makeResult('Dupont', 'Jean', null, [$this->makeDiscipline(10), $this->makeDiscipline(20)]);
        $array  = $result->toArray();

        $this->assertIsArray($array['disciplines']);
        $this->assertCount(2, $array['disciplines']);

        foreach ($array['disciplines'] as $disciplineArray) {
            $this->assertIsArray($disciplineArray);
            $this->assertArrayHasKey('id', $disciplineArray);
            $this->assertArrayHasKey('typeC', $disciplineArray);
            $this->assertArrayHasKey('professionCategoryCd', $disciplineArray);
            $this->assertArrayHasKey('businessTypeCode', $disciplineArray);
            $this->assertArrayHasKey('description', $disciplineArray);
        }
    }

    /**
     * Ensure toArray returns an empty array when no disciplines are present.
     */
    public function testToArrayDisciplinesIsEmptyArrayWhenNoDisciplines(): void
    {
        $array = $this->makeResult('Dupont', 'Jean', null, [])->toArray();
        $this->assertSame([], $array['disciplines']);
    }

    /**
     * Ensure toArray includes visa dateFrom and dispensation values.
     */
    public function testToArrayVisaContainsDateFromAndDispensation(): void
    {
        $date  = new DateTime('2022-11-01');
        $array = $this->makeResult('Dupont', 'Jean', null, [], $date, true)->toArray();

        $this->assertArrayHasKey('dateFrom', $array['visa']);
        $this->assertArrayHasKey('dispensation', $array['visa']);
        $this->assertInstanceOf(DateTime::class, $array['visa']['dateFrom']);
        $this->assertTrue($array['visa']['dispensation']);
    }

    /**
     * Ensure toArray preserves the provided practiceAddress payload.
     */
    public function testToArrayPracticeAddressIsPreserved(): void
    {
        $address = [['street' => 'Avenue Louise', 'city' => 'Brussels', 'zip' => '1050']];
        $array   = $this->makeResult('Dupont', 'Jean', null, [], null, false, $address)->toArray();

        $this->assertSame($address, $array['practiceAddress']);
    }
}
