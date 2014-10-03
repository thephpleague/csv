<?php

namespace League\Csv\test;

/**
 * Class ObjectWithToArray
 * @package League\Csv\test
 */
class ObjectWithToArray
{

    /**
     * @var string
     */
    protected $firstName;

    /**
     * @var string
     */
    protected $secondName;

    /**
     * @var string
     */
    protected $email;


    /**
     * @param string $firstName  firstname
     * @param string $secondName surname
     * @param string $email      email
     */
    public function __construct($firstName, $secondName, $email)
    {
        $this->firstName  = $firstName;
        $this->secondName = $secondName;
        $this->email      = $email;

    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            $this->firstName,
            $this->secondName,
            $this->email
        ];

    }


}