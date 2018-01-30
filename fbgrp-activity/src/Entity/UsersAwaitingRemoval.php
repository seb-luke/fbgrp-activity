<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UsersAwaitingRemovalRepository")
 */
class UsersAwaitingRemoval
{

    /**
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", nullable=false)
     */
    private $fbUserId;

    /**
     * @var string
     * @ORM\Id()
     * @ORM\Column(type="string", nullable=false)
     */
    private $fbGroupId;

    /**
     * @var FacebookGroupUsers
     * @ORM\OneToOne(targetEntity="FacebookGroupUsers")
     * @ORM\JoinColumns({
     *     @ORM\JoinColumn(name="fb_user_id", referencedColumnName="fb_user_id"),
     *     @ORM\JoinColumn(name="fb_group_id", referencedColumnName="fb_group_id")
     * })
     */
    private $groupUser;

    /**
     * UsersAwaitingRemoval constructor.
     * @param $facebookGroupUser FacebookGroupUsers
     */
    public function __construct($facebookGroupUser)
    {
        $this->groupUser = $facebookGroupUser;
        $this->fbUserId = $facebookGroupUser->getId();
        $this->fbGroupId = $facebookGroupUser->getFbGroupId();
    }

    /**
     * @return FacebookGroupUsers
     */
    public function getGroupUser(): FacebookGroupUsers
    {
        return $this->groupUser;
    }

}
