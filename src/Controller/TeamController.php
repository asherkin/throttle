<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\User;
use App\Form\TeamType;
use App\Security\Voter\TeamVoter;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

#[Route('/teams')]
#[IsGranted('ROLE_USER')]
class TeamController extends AbstractController
{
    #[Route('/', name: 'team_index', methods: ['GET'])]
    public function index(Security $security): Response
    {
        /** @var User $user */
        $user = $security->getUser();

        return $this->render('team/index.html.twig', [
            'teams' => $user->getTeams(),
        ]);
    }

    #[Route('/new', name: 'team_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Security $security, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $security->getUser();

        $team = new Team();
        $team->setOwner($user);

        $form = $this->createForm(TeamType::class, $team);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($team);
            $entityManager->flush();

            return $this->redirectToRoute('team_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('team/new.html.twig', [
            'team' => $team,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'team_show', methods: ['GET'])]
    public function show(Team $team): Response
    {
        $this->denyAccessUnlessGranted(TeamVoter::VIEW, $team);

        return $this->render('team/show.html.twig', [
            'team' => $team,
        ]);
    }

    #[Route('/{id}/edit', name: 'team_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Team $team, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(TeamVoter::EDIT, $team);

        $form = $this->createForm(TeamType::class, $team);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('team_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('team/edit.html.twig', [
            'team' => $team,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'team_delete', methods: ['POST'])]
    public function delete(Request $request, Team $team, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(TeamVoter::EDIT, $team);

        /** @var ?string $token */
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete'.$team->getId(), $token)) {
            $entityManager->remove($team);
            $entityManager->flush();
        }

        return $this->redirectToRoute('team_index', [], Response::HTTP_SEE_OTHER);
    }
}
