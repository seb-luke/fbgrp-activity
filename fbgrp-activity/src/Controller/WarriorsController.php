<?php

namespace App\Controller;

use App\Services\FacebookApiService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class WarriorsController extends Controller
{
    /**
     * @Route("/warriors", name="warriorsIndex")
     * @param FacebookApiService $fbService
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function warriorsIndex(FacebookApiService $fbService)
    {
        $loggedUser = $this->getUser();
        $groups = $fbService->getGroupsWhereUserIsAdmin($loggedUser);

        return $this->render('App/Warriors/warriorsIndex.html.twig', [
            'user' => ['name' => $loggedUser->getName(), 'surname' => $loggedUser->getSurname()],
            'groups' => $groups
        ]);
    }
}
