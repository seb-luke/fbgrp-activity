<?php

namespace App\Repository;

use App\Entity\FacebookGroupUsers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\RegistryInterface;

class FacebookGroupUsersRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, FacebookGroupUsers::class);
    }

    /**
     * @param $fbUserId int
     * @param $fbGroupId int
     * @return FacebookGroupUsers|null
     */
    public function findUser($fbUserId, $fbGroupId): ?FacebookGroupUsers
    {
        $result = $this->findBy([
            'fbUserId' => $fbUserId,
            'fbGroupId' => $fbGroupId
        ]);

        if ($result) {
            return $result[0];
        }

        return null;
    }

    /**
     * @param $groupId int
     * @return FacebookGroupUsers[]
     */
    public function findActiveGroupUsers($groupId)
    {
        return $this->findBy([
            'fbGroupId' => $groupId,
            'isActive' => true
        ]);
    }

    /**
     * There users represent an anomaly => it means they exited the group on their own choice
     * @return FacebookGroupUsers[]
     */
    public function getNonMemberActiveUsersAnomaly()
    {
        return $this->findBy([
            'isActive' => true,
            'isMember' => false
        ]);
    }

    /**
     * @param $fbGroupId int
     * @return FacebookGroupUsers[]
     */
    public function getActiveNormalUsers($fbGroupId)
    {
        return $this->findBy([
            'fbGroupId' => $fbGroupId,
            'isActive' => true,
            'isAdmin' => false,
        ]);
    }

    /**
     * @param $fbGroupId
     * @return FacebookGroupUsers[]
     */
    public function getUsersThatQuit($fbGroupId)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->createQueryBuilder('u');
            $qb ->andWhere($qb->expr()->eq('u.fbGroupId', ':fbGroupId'))
                ->andWhere($qb->expr()->eq('u.isActive', ':isActive'))
                ->andWhere($qb->expr()->isNull('u.dateOfRemoval'));

        $qb->setParameters([
            'fbGroupId' => $fbGroupId,
            'isActive' => false
        ]);

        return $qb->getQuery()->getArrayResult();
    }
    /**
     * @param $fbGroupId
     * @return FacebookGroupUsers[]
     */
    public function getRemovedUsers($fbGroupId)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->createQueryBuilder('u');
        $qb ->andWhere($qb->expr()->eq('u.fbGroupId', ':fbGroupId'))
            ->andWhere($qb->expr()->eq('u.isActive', ':isActive'))
            ->andWhere($qb->expr()->isNotNull('u.dateOfRemoval'));

        $qb->setParameters([
            'fbGroupId' => $fbGroupId,
            'isActive' => false
        ]);

        return $qb->getQuery()->getArrayResult();
    }
}
