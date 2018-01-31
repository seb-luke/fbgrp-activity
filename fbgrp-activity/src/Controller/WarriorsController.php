<?php

namespace App\Controller;

use App\Entity\FacebookGroups;
use App\Entity\FacebookGroupUsers;
use App\Entity\FacebookUser;
use App\Entity\UsersAwaitingRemoval;
use App\Form\FacebookGroupType;
use App\Form\UserRemovalType;
use App\Repository\FacebookGroupsRepository;
use App\Repository\FacebookGroupUsersRepository;
use App\Repository\FacebookUserRepository;
use App\Repository\UsersAwaitingRemovalRepository;
use App\Services\FacebookApiService;
use Doctrine\Common\Collections\ArrayCollection;
use Facebook\GraphNodes\GraphEdge;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class WarriorsController
 * @package App\Controller
 * @Route("/warriors")
 */
class WarriorsController extends Controller
{
    /**
     * @Route("/", name="warriorsIndex")
     * @param FacebookApiService $fbService
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function warriorsIndex(FacebookApiService $fbService)
    {
        $loggedUser = $this->getUser();
        $groups = $fbService->getGroupsWhereUserIsAdmin($loggedUser);

        $groupRepo = $this->getDoctrine()->getManager()->getRepository(FacebookGroups::class);
        foreach ($groups as &$grp) {

            $isManaged = false;
            $fbGroup = $groupRepo->find($grp["id"]);
            if ($fbGroup != null) {
                $isManaged = true;
            }

            $grp['isWarriorManaged'] = $isManaged;
        }

        dump($groups);
        return $this->render('App/Warriors/index.html.twig', [
            'user' => ['name' => $loggedUser->getName(), 'surname' => $loggedUser->getSurname()],
            'groups' => $groups
        ]);
    }

    /**
     * @Route("/group/add/{grpId}", name="warriorsAddGroup")
     * @param FacebookApiService $fbService
     * @param Request $request
     * @param $grpId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function warriorsAddGroup(FacebookApiService $fbService, Request $request, $grpId)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var FacebookUser $user */
        $user = $this->getUser();

        $managedPages = $fbService->getManagedPagesOfUser($user);
        $groups = $fbService->getGroupsWhereUserIsAdmin($user);
        $currentGroupData = $fbService->getDataForGroup($grpId, $user);

        $fbGroup = new FacebookGroups($grpId, $user->getFacebookId(), $currentGroupData->getName());

        $form = $this->createForm(FacebookGroupType::class, $fbGroup, [
            'managed_pages' => $managedPages,
            'groups' => $groups,
            'current_group_id' => $grpId
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($fbGroup);
            $this->importAllUsersOfGroup($fbService, $fbGroup->getId());

            if ($fbGroup->getSecondaryGroupId() != null) {
                // it means that a secondary group exists and it should be persisted

                $secondaryGroupName = '';
                foreach ($groups as $grp) {
                    if ($grp['id'] === $fbGroup->getSecondaryGroupId()) {
                        $secondaryGroupName = $grp['name'];
                        break;
                    }
                }

                $fbSecondaryGrp = new FacebookGroups($fbGroup->getSecondaryGroupId(), $user->getFacebookId(), $secondaryGroupName);
                $fbSecondaryGrp->setIsPrimaryGroup(false);

                $em->persist($fbSecondaryGrp);
            }

            $em->flush();

            return $this->redirectToRoute('warriorsManageGroup', ["grpId" => $fbGroup->getId()]);
        }

        return $this->render('App/Warriors/addGroup.html.twig', [
            'form' => $form->createView(),
            'GroupName' => $currentGroupData->getName()
        ]);
    }

    /**
     * @Route("/group/manage/{grpId}", name="warriorsManageGroup")
     * @param $grpId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function warriorsManageGroup($grpId)
    {
        $statistics = $this->getGroupStatistics($grpId);

        return $this->render('App/Warriors/manageGroup.html.twig', [
            'statistics'    => $statistics,
            'groupId'       => $grpId
        ]);
    }

    /**
     * @Route("/group/manage/{grpId}/removeUsers", name="warriorsGroupRemoval")
     * @param Request $request
     * @param $grpId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function warriorsGroupRemoval(Request $request, $grpId)
    {
        $inactiveUsers = $this->getDoctrine()->getRepository(UsersAwaitingRemoval::class)->findAll();
        /** @var FacebookGroupsRepository $groupRepo */
        $groupRepo = $this->getDoctrine()->getRepository(FacebookGroups::class);
        $group = $groupRepo->findGroup($grpId);

        $form = $this->createForm(UserRemovalType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var UsersAwaitingRemoval[] $usersAwaitingRemoval */
            $usersAwaitingRemoval = $form->getData()["usersRemoval"];

            foreach ($usersAwaitingRemoval as $user) {

                $user->getGroupUser()->removeFromGroup();
                $this->getDoctrine()->getManager()->remove($user);
            }

            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('warriorsManageGroup', ["grpId" => $grpId]);
        }

        return $this->render('App/Warriors/removeUsersFromGroup.html.twig', [
            'inactiveUsers' => $inactiveUsers,
            'group'         => $group,
            'form'          => $form->createView(),
        ]);
    }


    /**
     * @Route("/group/{grpId}", name="warriorsGroupDetail")
     * @param $grpId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function warriorsGroup($grpId)
    {
        /** @var FacebookGroups $group */
        $group = $this->getDoctrine()->getManager()->getRepository(FacebookGroups::class)->find($grpId);
        dump($group);

        return $this->render('App/Warriors/viewGroup.html.twig', ['group_name' => $group->getName()]);
    }

    /**
     * @param FacebookApiService $fbService
     * @param $groupId
     */
    private function importAllUsersOfGroup(FacebookApiService $fbService, $groupId)
    {
        $em = $this->getDoctrine()->getManager();
        $usersFeedEdge = $fbService->getUsersOfGroup($groupId, $this->getUser());

        while ($usersFeedEdge) {

            /** @var GraphEdge $userEdge */
            foreach ($usersFeedEdge as $userEdge) {
                $userData = $userEdge->asArray();

                $groupUser = new FacebookGroupUsers($userData['id'], $groupId, $userData['name'], $userData['administrator']);
                $em->persist($groupUser);
            }

            $usersFeedEdge = $fbService->getNextPage($usersFeedEdge);
        }

        $em->flush();
    }

    /**
     * @param $grpId
     * @return array
     */
    private function getGroupStatistics($grpId)
    {
        $groupStatistics = [];

        /** @var FacebookGroupsRepository $groupRepo */
        $groupRepo = $this->getDoctrine()->getRepository(FacebookGroups::class);
        /** @var FacebookUserRepository $userRepo */
        $userRepo = $this->getDoctrine()->getRepository(FacebookUser::class);
        /** @var FacebookGroupUsersRepository $groupUsersRepo */
        $groupUsersRepo = $this->getDoctrine()->getRepository(FacebookGroupUsers::class);
        /** @var UsersAwaitingRemovalRepository $userAwaitingRemovalRepo */
        $userAwaitingRemovalRepo = $this->getDoctrine()->getRepository(UsersAwaitingRemoval::class);

        $group = $groupRepo->findGroup($grpId);

        if ($grpId) {
            $groupStatistics['name'] = $group->getName();
            $groupStatistics['isPrimary'] = $group->isPrimaryGroup();
            $groupStatistics['isManagedByPage'] = $group->getFbPageId() != null;
            $groupStatistics['isCheckedForActivity'] = $group->isCheckForActivity();

            /** @var FacebookUser $mainAdmin */
            $mainAdmin = $userRepo->findUserIdByFacebookId($group->getMainAdminId());
            $groupStatistics['mainAdminName'] = $mainAdmin->getFullName();
            $groupStatistics['mainAdminId'] = $mainAdmin->getFacebookId();

            if ($group->isPrimaryGroup()) {
                if ($group->getSecondaryGroupId()) {
                    $secondaryGroup = $groupRepo->findGroup($group->getSecondaryGroupId());
                    $groupStatistics['secondaryGroupName'] = $secondaryGroup->getName();
                    $groupStatistics['secondaryGroupId'] = $secondaryGroup->getId();
                }
            } else {
                $primaryGroup = $groupRepo->findPrimaryGroup($group->getId());
                $groupStatistics['primaryGroupName'] = $primaryGroup->getName();
                $groupStatistics['primaryGroupId'] = $primaryGroup->getId();
            }

            $groupStatistics['countActiveUsers'] = count($groupUsersRepo->getActiveNormalUsers($grpId));
            $groupStatistics['countUsersThatQuit'] = count($groupUsersRepo->getUsersThatQuit($grpId));
            $groupStatistics['countRemovedUsers'] = count($groupUsersRepo->getRemovedUsers($grpId));
            $groupStatistics['countNeedRemoval'] = count($userAwaitingRemovalRepo->findAll());
        }

        return $groupStatistics;
    }
}














