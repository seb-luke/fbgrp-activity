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

    /*
    public function findBySomething($value)
    {
        return $this->createQueryBuilder('f')
            ->where('f.something = :value')->setParameter('value', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */
}
