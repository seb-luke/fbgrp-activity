<?php

namespace App\Controller;

use App\Entity\FacebookGroups;
use App\Services\FacebookApiService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

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
     * @param $grpId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function warriorsAddGroup($grpId)
    {
        return $this->render('App/Warriors/addGroup.html.twig');
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
}
