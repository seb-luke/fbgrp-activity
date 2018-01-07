<?php
/**
 * User: Seb
 * Date: 06-Jan-18
 * Time: 19:43
 */

namespace App\Security\User;


use App\Entity\FacebookUser;
use App\Repository\FacebookUserRepository;
use App\Services\FacebookApiService;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class FacebookUserProvider implements UserProviderInterface
{

    /**
     * @var FacebookUserRepository
     */
    protected $userRepository;
    /**
     * @var EntityManager
     */
    protected $em;
    /**
     * @var FacebookApiService
     */
    protected $fbService;

    public function __construct(EntityManager $em, FacebookApiService $fbService)
    {
        $this->em = $em;
        $this->userRepository = $em->getRepository(FacebookUser::class);
        $this->fbService = $fbService;
    }

    /**
     * @param $fbToken
     * @return null|object
     * @throws \Doctrine\ORM\ORMException
     */
    public function findUserIdForFacebookToken($fbToken)
    {
        if ($fbToken == null)
        {
            return null;
        }

        $facebookId = $this->fbService->getFacebookIdFromToken($fbToken);
        $user = $this->userRepository->findUserIdByFacebookId($facebookId);

        if ($user == null)
        {
            // The user does not exist and should be created
            $user = $this->fbService->generateUserFromFbToken($fbToken);
            $this->em->persist($user);
            $this->em->flush();

            $userId = $user->getId();
        } else {
            // update the Token if it differs
            if ($user->getFacebookAuthToken() != $fbToken) {
                $user->setFacebookAuthToken($fbToken);
                $this->em->flush();
            }

            $userId = $user->getId();
        }

        return $userId;
    }

    /**
     * Loads the user for the given internal user id
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $userId The internal user id
     *
     * @return FacebookUser|object
     *
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($userId)
    {
        if ($userId == null)
        {
            throw new UsernameNotFoundException("Could not find user for the id: " . $userId);
        }

        $user = $this->userRepository->find($userId);
        if ($user == null)
        {
            throw new UsernameNotFoundException("Could not find user for the id: " . $userId);
        }

        return $user;
    }

    /**
     * Refreshes the user.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @param UserInterface $user
     * @return UserInterface
     *
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof FacebookUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByUsername($user->getId());
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        return FacebookUser::class === $class;
    }
}