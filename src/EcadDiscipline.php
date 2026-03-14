<?php

declare (strict_types = 1);

namespace viavario\ecadclient;

/**
 * Represents a discipline from the eCad database of FPS Health.
 */
class EcadDiscipline
{
    /** @var int|string */
    public $id;

    /** @var string */
    public $typeC;

    /** @var string */
    public $professionCategoryCd;

    /** @var string */
    public $businessTypeCode;

    /** @var array{textFr: string, textNl: string} */
    public $description;

    /**
     * @param int|string $id
     * @param string $typeC
     * @param string $professionCategoryCd
     * @param string $businessTypeCode
     * @param array{textFr: string, textNl: string} $description
     */
    public function __construct(
        $id,
        string $typeC,
        string $professionCategoryCd,
        string $businessTypeCode,
        array $description
    ) {
        $this->id = $id;
        $this->typeC = $typeC;
        $this->professionCategoryCd = $professionCategoryCd;
        $this->businessTypeCode = $businessTypeCode;
        $this->description = $description;
    }

    /**
     * Returns the discipline as an associative array.
     *
     * @return array{
     *   id: int|string,
     *   typeC: string,
     *   professionCategoryCd: string,
     *   businessTypeCode: string,
     *   description: array{textFr: string, textNl: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'typeC' => $this->typeC,
            'professionCategoryCd' => $this->professionCategoryCd,
            'businessTypeCode' => $this->businessTypeCode,
            'description' => $this->description,
        ];
    }
}
