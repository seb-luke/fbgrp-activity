<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FacebookGroupsRepository")
 */
class FacebookGroups
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private $fbGroupId;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=false)
     */
    private $mainAdminId;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true)
     */
    private $fbPageId;

    /**
     * @var string
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private $name;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $isPrimaryGroup;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true)
     */
    private $secondaryGroupId;

    public function __construct($facebookGroupId)
    {
        $this->fbGroupId = $facebookGroupId;
        $this->isPrimaryGroup = true;
    }

    /**
     * @return int
     */
    public function getFbGroupId(): int
    {
        return $this->fbGroupId;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->getFbGroupId();
    }

    /**
     * @return int
     */
    public function getMainAdminId(): int
    {
        return $this->mainAdminId;
    }

    /**
     * @param int $mainAdminId
     */
    public function setMainAdminId(int $mainAdminId): void
    {
        $this->mainAdminId = $mainAdminId;
    }

    /**
     * @return int
     */
    public function getFbPageId(): int
    {
        return $this->fbPageId;
    }

    /**
     * @param int $fbPageId
     */
    public function setFbPageId(int $fbPageId): void
    {
        $this->fbPageId = $fbPageId;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return bool
     */
    public function isPrimaryGroup(): bool
    {
        return $this->isPrimaryGroup;
    }

    /**
     * @param bool $isPrimaryGroup
     */
    public function setIsPrimaryGroup(bool $isPrimaryGroup): void
    {
        $this->isPrimaryGroup = $isPrimaryGroup;
    }

    /**
     * @return int
     */
    public function getSecondaryGroupId(): int
    {
        return $this->secondaryGroupId;
    }

    /**
     * @param int $secondaryGroupId
     */
    public function setSecondaryGroupId(int $secondaryGroupId): void
    {
        $this->secondaryGroupId = $secondaryGroupId;
    }
}
