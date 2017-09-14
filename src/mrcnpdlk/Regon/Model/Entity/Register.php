<?php

namespace mrcnpdlk\Regon\Model\Entity;


class Register
{
    /**
     * @var string
     */
    public $nr;
    /**
     * @var string
     */
    public $typeId;
    /**
     * @var string
     */
    public $typeName;
    /**
     * @var string
     */
    public $dateAdd;

    public function __construct(string $nr = null, string $typeId, string $typeName, string $dateAdd = null)
    {
        $this->nr       = $nr;
        $this->typeId   = $typeId;
        $this->typeName = $typeName;
        $this->dateAdd  = $dateAdd;
    }
}
