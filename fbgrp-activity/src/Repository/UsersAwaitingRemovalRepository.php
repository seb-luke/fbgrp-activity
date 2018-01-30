<?php

namespace App\Repository;

use App\Entity\FacebookGroupUsers;
use App\Entity\UsersAwaitingRemoval;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class UsersAwaitingRemovalRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, UsersAwaitingRemoval::class);
    }

    /**
     * @param FacebookGroupUsers $fbGroupUser
     * @return null|UsersAwaitingRemoval
     */
    public function findByGroupUser($fbGroupUser)
    {
        $data = $this->findBy(['groupUser' => $fbGroupUser]);

        if ($data == null) {
            return null;
        }

        return $data[0];
    }
}
