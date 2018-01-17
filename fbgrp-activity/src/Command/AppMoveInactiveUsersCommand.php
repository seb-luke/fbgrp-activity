<?php

namespace App\Command;

use App\Entity\FacebookGroups;
use App\Entity\FacebookGroupUsers;
use App\Entity\FacebookUser;
use App\Repository\FacebookGroupsRepository;
use App\Repository\FacebookGroupUsersRepository;
use App\Repository\FacebookUserRepository;
use App\Services\FacebookApiService;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\GraphNodes\GraphEdge;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AppMoveInactiveUsersCommand extends Command
{
    protected static $defaultName = 'app:move-inactive-users';
    /**
     * @var FacebookApiService
     */
    private $fbService;
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var LoggerInterface
     */
    private $logger;

    private $protectedFbUsers = [
        "Simona Ienea",
    ];

    public function __construct(?string $name = null, EntityManagerInterface $em, FacebookApiService $fb, LoggerInterface $log)
    {
        parent::__construct($name);

        $this->em = $em;
        $this->fbService = $fb;
        $this->logger = $log;
    }

    protected function configure()
    {
        $this
            ->setDescription('This command looks through the users of each group that were inactive 3 times in the last' .
                'three months and removes them. IF there is a secondary group, it moves them to it')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);


        $groups = $this->getGroupsToCheck();
        foreach ($groups as $group) {
            $this->handleGroup($group);
        }

        $io->success('Users were moved.');
    }


    /**
     * @return FacebookGroups[]|array
     */
    private function getGroupsToCheck(): array
    {
        /** @var FacebookGroupsRepository $repo */
        $repo = $this->em->getRepository(FacebookGroups::class);
        $groupsThatNeedChecking = $repo->findGroupsThatNeedActivityChecking();

        return $groupsThatNeedChecking;
    }


    /**
     * @param FacebookGroups $group
     */
    private function handleGroup(FacebookGroups $group)
    {
        date_default_timezone_set('UTC');
        $yesterday = new \DateTime('yesterday');

        /** @var FacebookUserRepository $fbUserRepository */
        $fbUserRepository = $this->em->getRepository(FacebookUser::class);
        /** @var FacebookUser $mainAdmin */
        $mainAdmin = $fbUserRepository->findUserIdByFacebookId($group->getMainAdminId());

        $this->checkUpdateUsersOfGroup($mainAdmin, $group);

        // at this point we know that every user with is_active=true needs to be checked for activity

    }

    /**
     * @param $mainAdmin FacebookUser
     * @param $group FacebookGroups
     */
    private function checkUpdateUsersOfGroup($mainAdmin, $group)
    {

        $this->clearMembershipStatus($group);

        /** @var FacebookGroupUsersRepository $fbGroupUserRepository */
        $fbGroupUserRepository = $this->em->getRepository(FacebookGroupUsers::class);

        $usersFeedEdge = $this->fbService->getUsersOfGroup($group->getId(), $mainAdmin);

        $u=0;
        while ($usersFeedEdge) {

            /** @var GraphEdge $userEdge */
            foreach ($usersFeedEdge as $userEdge) {

                $userData = $userEdge->asArray();
                $this->addOrUpdateFbGroupUser($userData, $group->getId(), $fbGroupUserRepository);

                $u++;
            }

            $usersFeedEdge = $this->fbService->getNextPage($usersFeedEdge);
        }

        $this->logger->info(sprintf("%d users found in group '%s'", $u, $group->getName()));

        // now if there are users with is_member=false => they exited the group on their own so we update that status
        /** @var FacebookGroupUsers $exitedUser */
        foreach ($fbGroupUserRepository->getNonMemberActiveUsers() as $exitedUser) {

            if (in_array($exitedUser->getFullName(), $this->protectedFbUsers)) {
                // these are the users that have disallowed apps to see their accounts
                continue;
            }

            $exitedUser->updateAfterUserExited();
            $this->logger->info(sprintf("User '%s' exited the group '%s' on their own",
                $exitedUser->getFullName(),
                $group->getName()
            ));
        }

        $this->em->flush();
    }

    /**
     * @param $group FacebookGroups
     */
    private function clearMembershipStatus($group)
    {
        /** @var FacebookGroupUsersRepository $fbUserRepo */
        $fbUserRepo = $this->em->getRepository(FacebookGroupUsers::class);
        /** @var FacebookGroupUsers $user */
        foreach ($fbUserRepo->findActiveGroupUsers($group->getId()) as $user) {
            $user->setIsMember(false);
        }
    }

    /**
     * @param $fbUserArray array
     * @param $fbGroupId int
     * @param $fbGroupsUserRepo FacebookGroupUsersRepository
     */
    public function addOrUpdateFbGroupUser($fbUserArray, $fbGroupId, $fbGroupsUserRepo)
    {
        $fbGroupsUser = $fbGroupsUserRepo->findUser($fbUserArray['id'], $fbGroupId);

        if ($fbGroupsUser) {
            $fbGroupsUser->setIsMember(true);
        } else {
            $fbGroupsUser = new FacebookGroupUsers($fbUserArray['id'], $fbGroupId, $fbUserArray['name'], $fbUserArray['administrator']);
            $fbGroupsUser->setIsMember(true);
            $this->em->persist($fbGroupsUser);

            $this->logger->info("New user added to group with id: ".$fbGroupId, ['$user' => $fbGroupsUser] );
        }
    }



































}
