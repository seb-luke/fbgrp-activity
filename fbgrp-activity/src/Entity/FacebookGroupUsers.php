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
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    private $fbUserId;

    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string")
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

    /**
     * @var boolean
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $isAdmin;

    public function __construct($facebookUserId, $facebookGroupId, $userFullName, $isAdmin = false)
    {
        $this->fbUserId = $facebookUserId;
        $this->fbGroupId = $facebookGroupId;
        $this->fullName = $userFullName;
        $this->isActive = true;
        $this->dateOfRemoval = null;
        $this->isAdmin = $isAdmin;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->getFbUserId();
    }

    /**
     * @return string
     */
    public function getFbUserId(): string
    {
        return $this->fbUserId;
    }

    /**
     * @return string
     */
    public function getFbGroupId(): string
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
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    /**
     * @param bool $isAdmin
     */
    public function setIsAdmin(bool $isAdmin): void
    {
        $this->isAdmin = $isAdmin;
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
