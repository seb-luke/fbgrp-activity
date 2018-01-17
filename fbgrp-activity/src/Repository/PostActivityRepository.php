<?php

namespace App\Repository;

use App\Entity\PostActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class PostActivityRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, PostActivity::class);
    }

    /**
     * @param \DateTime $date
     * @return array
     */
    public function getActivityByDate(\DateTime $date) : array
    {
        return $this->findBy(['date' => $date]);
    }
}
