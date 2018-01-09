<?php
/**
 * User: Seb
 * Date: 09-Jan-18
 * Time: 14:05
 */

namespace App\DoctrineUtils;


use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeType;

class MyDateTimeType extends DateTimeType
{
    /**
     * @param $value
     * @param AbstractPlatform $platform
     * @return MyDateTime|bool|\DateTime|false|mixed
     * @throws \Doctrine\DBAL\Types\ConversionException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        $dateTime = parent::convertToPHPValue($value, $platform);

        if ( ! $dateTime) {
            return $dateTime;
        }

        return new MyDateTime('@' . $dateTime->format('Y-m-d H:i:s'));
    }

    public function getName()
    {
        return 'mydatetime';
    }
}