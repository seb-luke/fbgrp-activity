<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FacebookGroupsRepository")
 */
class FacebookGroups
{
    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    private $fbGroupId;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=false)
     */
    private $mainAdminId;

    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
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
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    private $secondaryGroupId;

    public function __construct($facebookGroupId, $adminId, $name)
    {
        $this->fbGroupId = $facebookGroupId;
        $this->mainAdminId = $adminId;
        $this->name = $name;
        $this->isPrimaryGroup = true;
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
    public function getId(): string
    {
        return $this->getFbGroupId();
    }

    /**
     * @return string
     */
    public function getMainAdminId(): string
    {
        return $this->mainAdminId;
    }

    /**
     * @return string|null
     */
    public function getFbPageId(): ?string
    {
        return $this->fbPageId;
    }

    /**
     * @param string $fbPageId
     */
    public function setFbPageId(string $fbPageId): void
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
     * @return string|null
     */
    public function getSecondaryGroupId(): ?string
    {
        return $this->secondaryGroupId;
    }

    /**
     * @param string $secondaryGroupId
     */
    public function setSecondaryGroupId(string $secondaryGroupId): void
    {
        $this->secondaryGroupId = $secondaryGroupId;
    }
}
