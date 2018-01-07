<?php

namespace App\Repository;

use App\Entity\FacebookGroupUsers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class FacebookGroupUsersRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, FacebookGroupUsers::class);
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
