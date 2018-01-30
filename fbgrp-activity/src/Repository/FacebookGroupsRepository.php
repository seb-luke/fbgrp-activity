<?php

namespace App\Repository;

use App\Entity\FacebookGroups;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class FacebookGroupsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, FacebookGroups::class);
    }

    /**
     * @return FacebookGroups[]
     */
    public function findGroupsThatNeedActivityChecking(): array
    {
        return $this->findBy(['checkForActivity' => true]);
    }

    /**
     * @param $grpId
     * @return FacebookGroups|null
     */
    public function findGroup($grpId): FacebookGroups
    {
        $group = $this->findBy(['fbGroupId' => $grpId]);

        if ($group) {
            return $group[0];
        }

        return null;
    }

    /**
     * @param $secondaryGroupId
     * @return FacebookGroups|null
     */
    public function findPrimaryGroup($secondaryGroupId): FacebookGroups
    {
        $group = $this->findBy(['secondaryGroupId' => $secondaryGroupId]);

        if ($group) {
            return $group[0];
        }

        return null;
    }
}
