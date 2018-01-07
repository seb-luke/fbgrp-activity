<?php

namespace App\Entity;

use App\Exceptions\UserAlreadyRemovedException;
use App\Exceptions\WarriorException;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FacebookGroupUsersRepository")
 * @UniqueEntity(
 *     fields={"fbUserId", "fbGroupId"}
 * )
 */
class FacebookGroupUsers
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private $fbUserId;

    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private $fbGroupId;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $fullName;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $isActive;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateOfRemoval;

    public function __construct($facebookUserId, $facebookGroupId, $userFullName)
    {
        $this->fbUserId = $facebookUserId;
        $this->fbGroupId = $facebookGroupId;
        $this->fullName = $userFullName;
        $this->isActive = true;
        $this->dateOfRemoval = null;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->getFbUserId();
    }

    /**
     * @return int
     */
    public function getFbUserId(): int
    {
        return $this->fbUserId;
    }

    /**
     * @return int
     */
    public function getFbGroupId(): int
    {
        return $this->fbGroupId;
    }

    /**
     * @return string
     */
    public function getFullName(): string
    {
        return $this->fullName;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @return \DateTime
     */
    public function getDateOfRemoval(): \DateTime
    {
        return $this->dateOfRemoval;
    }

    /**
     * @throws WarriorException
     */
    public function removeFromGroup(): void
    {
        if ($this->isActive && $this->dateOfRemoval == null)
        {
            $this->isActive = false;
            $this->dateOfRemoval = new \DateTime();
        } else {
            throw UserAlreadyRemovedException::Instantiate($this->getFbUserId(), $this->getFbGroupId(),
                                                            $this->isActive, $this->getDateOfRemoval());
        }
    }
}
