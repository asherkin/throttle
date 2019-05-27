<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class Home extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(Request $request, Session $session)
    {
        $id = $request->query->get('id');
        if (isset($id)) {
            $crashid = strtolower(str_replace('-', '', $id));

            try {
                $session->getFlashBag()->set('internal', 'true');

                return $this->redirectToRoute('details', ['id' => $crashid]);
            } catch (\Exception $e) {
                try {
                    return $this->redirectToRoute('details_uuid', ['uuid' => $id]);
                } catch (\Exception $e) {
                    $this->addFlash('error_crash', 'Invalid Crash ID.');

                    return $this->redirectToRoute('index');
                }
            }
        }

        return $this->render('index.html.twig', [
            'maintenance_message' => '', //$app['config']['maintenance'],
        ]);
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $returnUrl = $request->get('return', $this->generateUrl('dashboard'));

        // This just catches any logged in users ending up here.
        return $this->redirect($returnUrl);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
        throw new \Exception('Should not be called');
    }
}
