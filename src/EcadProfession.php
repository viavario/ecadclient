<?php

declare (strict_types = 1);

namespace viavario\ecadclient;

/**
 * Represents a single profession from the eCad database of FPS Health.
 */
class EcadProfession
{
    /** @var int|string */
    public $id;

    /** @var string */
    public $categoryCd;

    /** @var string */
    public $code;

    /** @var array{textFr: string, textNl: string} */
    public $description;

    /**
     * @param int|string $id
     * @param string $categoryCd
     * @param string $code
     * @param array{textFr: string, textNl: string} $description
     */
    public function __construct(
        $id,
        string $categoryCd,
        string $code,
        array $description
    ) {
        $this->id = $id;
        $this->categoryCd = $categoryCd;
        $this->code = $code;
        $this->description = $description;
    }

    /**
     * Returns the profession as an associative array.
     *
     * @return array{id: int|string, categoryCd: string, code: string, description: array{textFr: string, textNl: string}}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'categoryCd' => $this->categoryCd,
            'code' => $this->code,
            'description' => $this->description,
        ];
    }
}
