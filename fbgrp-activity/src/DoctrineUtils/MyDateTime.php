<?php
/**
 * User: Seb
 * Date: 09-Jan-18
 * Time: 13:22
 */

namespace App\DoctrineUtils;


use DateTimeZone;

class MyDateTime extends \DateTime
{
    public function __construct(string $time = 'now', DateTimeZone $timezone = null)
    {
        parent::__construct($time, $timezone);
    }

    public function __toString()
    {
        return $this->format('Y-m-d H:i:s');
    }
}