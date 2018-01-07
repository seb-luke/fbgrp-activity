<?php
/**
 * User: Seb
 * Date: 07-Jan-18
 * Time: 19:25
 */

namespace App\Exceptions;


use Throwable;

class UserAlreadyRemovedException extends WarriorException
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param $userId integer
     * @param $groupId int
     * @param $isActive boolean
     * @param $dateOfRemoval \DateTime
     * @return UserAlreadyRemovedException
     */
    public static final function Instantiate($userId, $groupId, $isActive, $dateOfRemoval): UserAlreadyRemovedException
    {
        $msg = sprintf('Cannot remove userId "%s" from groupId "%s". \nUserStatus isActive=%s\nDateOfRemoval=%s',
            $userId,
            $groupId,
            $isActive,
            $dateOfRemoval
            );

        return new UserAlreadyRemovedException($msg);
    }

}