<?php

namespace App\Repository;

use App\DoctrineUtils\MyDateTime;
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
     * @return PostActivity[]
     */
    public function getActivityByDate(\DateTime $date) : array
    {
        return $this->findBy(['date' => $date]);
    }

    /**
     * @param $fbUserId int
     * @param $fbGroupId int
     * @param MyDateTime $date
     * @return PostActivity|null
     */
    public function getActivity($fbUserId, $fbGroupId, MyDateTime $date)
    {
        $activity = $this->findBy([
            'fbUserId' => $fbUserId,
            'fbGroupId' => $fbGroupId,
            'date' => $date,
        ]);

        if ($activity) {
            return $activity[0];
        }

        return null;
    }


}
