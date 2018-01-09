<?php

namespace App\Command;

use App\DoctrineUtils\MyDateTime;
use App\Entity\FacebookGroups;
use App\Entity\FacebookGroupUsers;
use App\Entity\FacebookUser;
use App\Entity\PostActivity;
use App\Repository\FacebookGroupsRepository;
use App\Repository\FacebookUserRepository;
use App\Services\FacebookApiService;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\GraphNodes\GraphUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Exception\RuntimeException;

class AppManageGroupActivityCommand extends Command
{
    protected static $defaultName = 'app:manage-group-activity';

    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var FacebookApiService
     */
    private $fbService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(?string $name = null, EntityManagerInterface $em, FacebookApiService $fbService, LoggerInterface $logger)
    {
        parent::__construct($name);

        $this->em = $em;
        $this->fbService = $fbService;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setDescription('Command checks the likes from today\'s posts and removes inactive users, adding them ' .
                'to a secondary group (if existent)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);


        $groups = $this->getGroupsToCheck();
        foreach ($groups as $group) {
            $this->handleGroupActivity($group);
        }

        $io->success('User activity was successfully added to the database');
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
     * @param $group FacebookGroups
     */
    private function handleGroupActivity(FacebookGroups $group)
    {
        /** @var FacebookUserRepository $userRepo */
        $userRepo = $this->em->getRepository(FacebookUser::class);
        /** @var FacebookUser $mainAdmin */
        $mainAdmin = $userRepo->findUserIdByFacebookId($group->getMainAdminId());

        date_default_timezone_set('UTC');

        $yesterday = new \DateTime('yesterday');
        $today = new \DateTime('today');

        $groupTodayFeed = $this->fbService->getOneDayFeedOfGroup($mainAdmin, $group, $yesterday->getTimestamp());

        $yesterdayPostIDs = [];
        while ($groupTodayFeed) {

            foreach ($groupTodayFeed->asArray() as $post) {
                $realPostId = $post['id'];
                $realPost = $this->fbService->getPostFromId($mainAdmin, $realPostId);

                $creationTime = $realPost->asArray()['created_time'];

                if ($creationTime < $today && $creationTime >= $yesterday) {
                    array_push($yesterdayPostIDs, $realPostId);
                }
            }

            $groupTodayFeed = $this->fbService->getNextPage($groupTodayFeed);
        }

        if ($group->getFbPageId()) {
            // it means a page exists and we can search for reactions
            $usersLikesReactionsArray = $this->handleActivityForPostsAsPage($yesterdayPostIDs, $group, $mainAdmin);

        } else {
            // we need to use the post id after _ and using our token we can only search for likes
            $usersLikesReactionsArray = $this->handleActivityForPostsAsAdmin($yesterdayPostIDs, $mainAdmin);
        }

        $this->insertActiveUsers($usersLikesReactionsArray, $group, $mainAdmin);
    }

    /**
     * @param $yesterdayPostIDs int[]
     * @param $group FacebookGroups
     * @param $mainAdmin FacebookUser
     * @return array
     */
    private function handleActivityForPostsAsPage($yesterdayPostIDs, $group, $mainAdmin)
    {
        $pageId = $group->getFbPageId();
        $pageToken = $this->fbService->getPageToken($mainAdmin, $pageId)->getAccessToken();
        $usersThatReacted = [];

        foreach ($yesterdayPostIDs as $postID) {
            $reactionFeed = $this->fbService->getReactionsForPost($pageToken, $postID);

            while ($reactionFeed) {
                foreach ($reactionFeed->asArray() as $reaction) {
                    array_push($usersThatReacted, $reaction);
                }

                $reactionFeed = $this->fbService->getNextPage($reactionFeed);
            }
        }

        return $usersThatReacted;
    }

    /**
     * @param $yesterdayPostIDs array
     * @param $mainAdmin FacebookUser
     * @return array
     */
    private function handleActivityForPostsAsAdmin($yesterdayPostIDs, $mainAdmin)
    {
        $usersThatLiked = [];

        foreach ($yesterdayPostIDs as $postID) {

            $postID = explode('_', $postID)[1];
            $likeFeed = $this->fbService->getLikesForPost($mainAdmin, $postID);

            while ($likeFeed) {
                foreach ($likeFeed->asArray() as $like) {
                    array_push($usersThatLiked, $like);
                }

                $likeFeed = $this->fbService->getNextPage($likeFeed);
            }
        }

        return $usersThatLiked;
    }

    /**
     * @param $usersReactionArray array
     * @param $group FacebookGroups
     * @param $mainAdmin FacebookUser
     */
    private function insertActiveUsers($usersReactionArray, $group, $mainAdmin)
    {
        $date = new MyDateTime('yesterday');
        $date->setTimezone(new \DateTimeZone('UTC'));

        foreach ($usersReactionArray as $userReactionArray) {
            /** @var FacebookGroupUsers $user */
            $user = $this->em->getRepository(FacebookGroupUsers::class)->findOneBy([
                'fbUserId' => $userReactionArray['id'],
                'fbGroupId' => $group->getId()
            ]);

            if ($user == null) {
                $user = $this->createNewGroupUser($userReactionArray, $group, $mainAdmin);
            }

            $userPostActivity = new PostActivity($user->getId(), $user->getFbGroupId(), clone $date);
            $this->em->merge($userPostActivity);
        }

        $this->em->flush();
    }

    /**
     * @param $userReactionArray array
     * @param $group FacebookGroups
     * @param $mainAdmin FacebookUser
     * @return FacebookGroupUsers
     */
    private function createNewGroupUser($userReactionArray, $group, $mainAdmin)
    {
        $userName = null;
        $userId = $userReactionArray['id'];

        if (array_key_exists('name', $userReactionArray)) {
            $userName = $userReactionArray['name'];
        }

        if ($userName == null) {
            /** @var GraphUser $graphUser */
            try {
                $graphUser = $this->fbService->getUserProfile($userId, $mainAdmin->getFacebookAuthToken());
                $userName = $graphUser->getName();
            } catch (RuntimeException $e) {
                $userName = "UnknownName";
                $this->logger->error("Could not get the facebook profile for fbid: ".$userReactionArray['id'],
                    [
                        'exception' => $e->getTraceAsString()
                    ]);
            }
        }

        $user = new FacebookGroupUsers($userId, $group->getId(), $userName, false);
        $this->em->persist($user);

        return $user;
    }


}


















