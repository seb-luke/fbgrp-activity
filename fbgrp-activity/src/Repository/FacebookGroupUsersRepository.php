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
     * @return array
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
     * @return array
     */
    public function getNonMemberActiveUsers()
    {
        return $this->findBy([
            'isActive' => true,
            'isMember' => false
        ]);
    }
}
