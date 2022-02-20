<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(NotifierInterface $notifier, LoginLinkHandlerInterface $loginLinkHandler, UserRepository $userRepository, Request $request): Response
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

            $identifier = $data['email'];
            $user = $userRepository->findOrCreateOneByUserIdentifier($identifier);

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user);

            $notification = new LoginLinkNotification(
                $loginLinkDetails,
                sprintf('Your %s login link', $this->getParameter('app.name'))
            );

            $notifier->send($notification, $user->asRecipient());

            return $this->redirectToRoute('login_link_sent');
        }

        return $this->renderForm('security/login.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): Response
    {
        throw new \LogicException('unreachable');
    }

    #[Route('/login/sent', name: 'login_link_sent')]
    public function sent(): Response
    {
        return $this->render('security/login_link_sent.html.twig');
    }

    #[Route('/login/link', name: 'login_check')]
    public function check(): Response
    {
        throw new \LogicException('unreachable');
    }
}
