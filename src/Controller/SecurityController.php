<?php

namespace App\Controller;

use App\Security\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(NotifierInterface $notifier, LoginLinkHandlerInterface $loginLinkHandler, UserManager $userManager, Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $form = $this->createFormBuilder()
            ->add('email', EmailType::class, [
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('save', SubmitType::class, ['label' => 'Login'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email: string} */
            $data = $form->getData();

            $user = $userManager->findOrCreateUserForEmailAddress($data['email']);

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user);

            $notification = new LoginLinkNotification(
                $loginLinkDetails,
                sprintf('Your %s login link', $this->getParameter('app.name'))
            );

            $notifier->send($notification, new Recipient($data['email']));

            $request->getSession()->set('login_link_sent', true);

            return $this->redirectToRoute('login');
        }

        $loginLinkSent = $request->getSession()->remove('login_link_sent') !== null;

        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->renderForm('security/login.html.twig', [
            'login_link_sent' => $loginLinkSent,
            'error' => $error,
            'form' => $form,
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): Response
    {
        throw new \LogicException('unreachable');
    }

    #[Route('/login/link', name: 'login_link')]
    public function link(): Response
    {
        throw new \LogicException('unreachable');
    }

    #[Route('/login/steam', name: 'login_steam')]
    public function steam(): Response
    {
        throw new \LogicException('unreachable');
    }

    #[Route('/login/github', name: 'login_github')]
    public function github(): Response
    {
        throw new \LogicException('unreachable');
    }

    #[Route('/login/discord', name: 'login_discord')]
    public function discord(): Response
    {
        throw new \LogicException('unreachable');
    }
}
