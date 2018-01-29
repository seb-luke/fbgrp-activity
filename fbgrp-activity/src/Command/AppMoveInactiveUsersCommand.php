<?php

namespace App\Command;

use App\DoctrineUtils\MyDateTime;
use App\Entity\FacebookGroups;
use App\Entity\FacebookGroupUsers;
use App\Entity\FacebookUser;
use App\Entity\InactivityLog;
use App\Entity\PostActivity;
use App\Repository\FacebookGroupsRepository;
use App\Repository\FacebookGroupUsersRepository;
use App\Repository\FacebookUserRepository;
use App\Repository\PostActivityRepository;
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

    /**
     * @var MyDateTime
     */
    private $yesterday;

    private $protectedFbUsers = [
        "Simona Ienea",
    ];

    public function __construct(?string $name = null, EntityManagerInterface $em, FacebookApiService $fb, LoggerInterface $log)
    {
        parent::__construct($name);

        $this->em = $em;
        $this->fbService = $fb;
        $this->logger = $log;
        $this->yesterday = new MyDateTime('yesterday');
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

        $removedUsersPerGroup = [];
        $groups = $this->getGroupsToCheck();
        foreach ($groups as $group) {
            $removedUsers = $this->handleGroup($group);

            array_push($removedUsersPerGroup, [
                'group' => $group->getName(),
                'removedUsers' => $removedUsers
            ]);
        }

        $msg = "Users removed from groups:\n";
        foreach ($removedUsersPerGroup as $removed) {
            $msg .= sprintf("    - %d users await removal from group '%s'\n", $removed['removedUsers'], $removed['group']);
        }

        $io->success($msg);
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
     * @return int
     */
    private function handleGroup(FacebookGroups $group)
    {
        date_default_timezone_set('UTC');

        /** @var FacebookUserRepository $fbUserRepository */
        $fbUserRepository = $this->em->getRepository(FacebookUser::class);
        /** @var FacebookUser $mainAdmin */
        $mainAdmin = $fbUserRepository->findUserIdByFacebookId($group->getMainAdminId());

        $this->checkUpdateUsersOfGroup($mainAdmin, $group);

        // at this point we know that every user with is_active=true needs to be checked for activity
        $this->updateInactivityLog($group, $this->yesterday);
        // and now we can remove users that have 3 inactive days
        $removedUsers = $this->removeInactiveUsers($mainAdmin, $group,3);

        $this->em->flush();
//        return 0;
        return $removedUsers;
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

    /**
     * @param $group FacebookGroups
     * @param MyDateTime $date
     */
    private function updateInactivityLog($group, MyDateTime $date)
    {
        /** @var FacebookGroupUsersRepository $groupUserRepo */
        $groupUserRepo = $this->em->getRepository(FacebookGroupUsers::class);
        /** @var PostActivityRepository $postActivityRepo */
        $postActivityRepo = $this->em->getRepository(PostActivity::class);

        $users = $groupUserRepo->getActiveNormalUsers($group->getId());
        foreach ($users as $user) {

            $activity = $postActivityRepo->getActivity($user->getId(), $group->getId(), $date);
            if ($activity == null) {
                // it means that this $date day this user was not active
                $inactivity = new InactivityLog($user->getId(), $group->getId(), $date, $user);
                $this->em->persist($inactivity);
            }
        }
    }

    /**
     * @param $mainAdmin FacebookUser
     * @param $group FacebookGroups
     * @param $inactiveDays int
     * @return int
     */
    private function removeInactiveUsers($mainAdmin, $group, $inactiveDays)
    {
        /** @var FacebookGroupUsersRepository $groupUserRepo */
        $groupUserRepo = $this->em->getRepository(FacebookGroupUsers::class);

        $fbPageId = $group->getFbPageId();
        if ($fbPageId == null) {
            $fbToken = $mainAdmin->getFacebookAuthToken();
        } else {
            $fbToken = $this->fbService->getPageToken($mainAdmin, $fbPageId)->getAccessToken();
        }

        $removedUsers = 0;

        $users = $groupUserRepo->getActiveNormalUsers($group->getId());
        foreach ($users as $user) {

            $inactivity = $user->getInactivityLog();
            $currentInactivity = $this->getCurrentInactivity($inactivity);

            if (sizeof($currentInactivity) > $inactiveDays) {
                //TODO remove user from group

                if (strpos($user->getFullName(), "Noemi Luca") !== false) {

                    if ($group->getSecondaryGroupId() != null) {
//                        $this->fbService->addUserToGroup($user->getId(), $group->getSecondaryGroupId(), $fbToken);
                    }
//                    $this->fbService->removeUserFromGroup($user, $group->getId(), $fbToken);
                }

                $removedUsers++;
            }



        }

        return $removedUsers;
    }

    /**
     * @return array
     */
    private function generateBeginEndMonth()
    {
        $actualMonth = $this->yesterday->format('n');

        if ($actualMonth <= 3) {
            $begin = 1;
            $end = 3;
        } elseif ($actualMonth > 3 && $actualMonth <= 6) {
            $begin = 3;
            $end = 6;
        } elseif ($actualMonth > 6 && $actualMonth <= 9) {
            $begin = 6;
            $end = 9;
        } else {
            $begin = 9;
            $end = 12;
        }

        return [
            'beginMonth' => $begin,
            'endMonth' => $end
        ];
    }

    /**
     * @param $inactivityArray InactivityLog[]
     * @return InactivityLog[]
     */
    private function getCurrentInactivity($inactivityArray)
    {
        $currentInactivityLog = [];
        $dateComparison = $this->generateBeginEndMonth();

        foreach ($inactivityArray as $inactivity) {

            $inactivityMonth = $inactivity->getDate()->format('n');

            if ($inactivityMonth >= $dateComparison['beginMonth'] && $inactivityMonth < $dateComparison['endMonth']) {
                array_push($currentInactivityLog, $inactivity);
            }
        }

        return $currentInactivityLog;
    }


}



















