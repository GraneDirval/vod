<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 11.01.19
 * Time: 12:17
 */

namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ErrorController extends AbstractController
{

    /**
     * @Route("/whoops",name="mobile_identification")
     * @param Request $request
     * @return Response
     */
    public function wrongCarrierAction(Request $request)
    {
        return new Response('WIFI');
    }
}