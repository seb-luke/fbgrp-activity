<?php

namespace App\Repository;

use App\Entity\FacebookUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class FacebookUserRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, FacebookUser::class);
    }

    /**
     * @param $fbId
     * @return FacebookUser|null
     */
    public function findUserIdByFacebookId($fbId): FacebookUser
    {
        $user = $this->findBy(['facebookId' => $fbId]);

        if ($user) {
            return $user[0];
        }

        return null;
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
