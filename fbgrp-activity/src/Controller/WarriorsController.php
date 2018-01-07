<?php

namespace App\Controller;

use App\Entity\FacebookGroups;
use App\Entity\FacebookUser;
use App\Form\FacebookGroupType;
use App\Services\FacebookApiService;
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

            return $this->redirectToRoute('warriorsGroupDetail', ["grpId" => $fbGroup->getId()]);
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
        return $this->render('App/Warriors/manageGroup.html.twig');
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

        return $this->render('App/Warriors/manageGroup.html.twig', ['group_name' => $group->getName()]);
    }
}
