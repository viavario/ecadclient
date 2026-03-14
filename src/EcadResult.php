<?php

declare (strict_types = 1);

namespace viavario\ecadclient;

/**
 * Represents a single healthcare professional result from the eCad database of FPS Health.
 */
class EcadResult
{
    /** @var string */
    public $lastname;

    /** @var string */
    public $firstname;

    /** @var EcadProfession */
    public $profession;

    /** @var EcadDiscipline[] */
    public $disciplines;

    /** @var array{dateFrom: \DateTime, dispensation: bool} */
    public $visa;

    /** @var array<int, array> */
    public $practiceAddress;

    /**
     * @param string $lastname
     * @param string $firstname
     * @param EcadProfession $profession
     * @param EcadDiscipline[] $disciplines
     * @param \DateTime $visaDateFrom
     * @param bool $visaDispensation
     * @param array $practiceAddress
     */
    public function __construct(
        string $lastname,
        string $firstname,
        EcadProfession $profession,
        array $disciplines,
        \DateTime $visaDateFrom,
        bool $visaDispensation,
        array $practiceAddress
    ) {
        $this->lastname = $lastname;
        $this->firstname = $firstname;
        $this->profession = $profession;
        $this->disciplines = $disciplines;
        $this->visa = [
            'dateFrom' => $visaDateFrom,
            'dispensation' => $visaDispensation,
        ];
        $this->practiceAddress = $practiceAddress;
    }

    /**
     * Returns the result as an associative array.
     *
     * @return array{
     *   lastname: string,
     *   firstname: string,
     *   profession: array{id: int|string, categoryCd: string, code: string, description: array{textFr: string, textNl: string}},
     *   disciplines: array<int, array{id: int|string, typeC: string, professionCategoryCd: string, businessTypeCode: string, description: array{textFr: string, textNl: string}}>,
     *   visa: array{dateFrom: \DateTime, dispensation: bool},
     *   practiceAddress: array<int, array>
     * }
     */
    public function toArray(): array
    {
        return [
            'lastname' => $this->lastname,
            'firstname' => $this->firstname,
            'profession' => $this->profession->toArray(),
            'disciplines' => array_map(static function (EcadDiscipline $discipline): array {
                return $discipline->toArray();
            }, $this->disciplines),
            'visa' => $this->visa,
            'practiceAddress' => $this->practiceAddress,
        ];
    }
}


