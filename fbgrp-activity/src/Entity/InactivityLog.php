<?php

namespace App\Entity;

use App\DoctrineUtils\MyDateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InactivityLogRepository")
 * @ORM\Table(indexes={@ORM\Index(name="idxDateGroup", columns={"date", "fb_group_id"})})
 * @UniqueEntity(
 *     fields={"fbUserId", "fbGroupId", "date"}
 * )
 */
class InactivityLog
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
     * @var MyDateTime
     * @ORM\Id()
     * @ORM\Column(type="mydatetime", nullable=false)
     */
    private $date;

    /**
     * @var FacebookGroupUsers
     * @ORM\ManyToOne(targetEntity="App\Entity\FacebookGroupUsers", inversedBy="inactivityLog")
     * @ORM\JoinColumns({
     *     @ORM\JoinColumn(name="fb_user_id", referencedColumnName="fb_user_id"),
     *     @ORM\JoinColumn(name="fb_group_id", referencedColumnName="fb_group_id")
     * })
     */
    private $fbGroupUser;


    /**
     * PostActivity constructor.
     * @param $facebookUserId string
     * @param $facebookGroupId string
     * @param MyDateTime $date
     */
    public function __construct($facebookUserId, $facebookGroupId, MyDateTime $date, FacebookGroupUsers $user)
    {
        $this->fbUserId = $facebookUserId;
        $this->fbGroupId = $facebookGroupId;
        $this->date = $date;
        $this->fbGroupUser = $user;
    }

    /**
     * @return string
     */
    public function getFbUserId(): string
    {
        return $this->fbUserId;
    }

    /**
     * @return string
     */
    public function getFbGroupId(): string
    {
        return $this->fbGroupId;
    }

    /**
     * @return MyDateTime
     */
    public function getDate(): MyDateTime
    {
        return $this->date;
    }

    /**
     * @return FacebookGroupUsers
     */
    public function getFbGroupUser(): FacebookGroupUsers
    {
        return $this->fbGroupUser;
    }
}
