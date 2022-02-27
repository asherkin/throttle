<?php

namespace App\Controller;

use App\Entity\Server;
use App\Entity\User;
use App\Form\ServerType;
use App\Repository\ServerRepository;
use App\Security\Voter\ServerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

#[Route('/servers')]
#[IsGranted('ROLE_USER')]
class ServerController extends AbstractController
{
    #[Route('/', name: 'server_index', methods: ['GET'])]
    public function index(Security $security, ServerRepository $serverRepository): Response
    {
        /** @var User $user */
        $user = $security->getUser();

        return $this->render('server/index.html.twig', [
            'owners' => $user->getServerOwners(),
            'servers' => $serverRepository->findAllForUserGroupedByOwner($user),
        ]);
    }

    #[Route('/new', name: 'server_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Security $security, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $security->getUser();

        $ownerChoices = $user->getServerOwners();

        $server = new Server();

        $initialOwnerId = $request->query->getInt('owner', -1);
        if ($initialOwnerId !== -1) {
            // TODO: This is a little gross.
            foreach ($ownerChoices as $owner) {
                if ($owner->getId() === $initialOwnerId) {
                    $server->setOwner($owner);
                    break;
                }
            }
        } else {
            $server->setOwner($user);
        }

        $form = $this->createForm(ServerType::class, $server, [
            'owner_choices' => $ownerChoices,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($server);
            $entityManager->flush();

            return $this->redirectToRoute('server_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('server/new.html.twig', [
            'server' => $server,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'server_show', methods: ['GET'])]
    public function show(Server $server): Response
    {
        $this->denyAccessUnlessGranted(ServerVoter::VIEW, $server);

        return $this->render('server/show.html.twig', [
            'server' => $server,
        ]);
    }

    #[Route('/{id}/edit', name: 'server_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Security $security, Server $server, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ServerVoter::EDIT, $server);

        /** @var User $user */
        $user = $security->getUser();

        $form = $this->createForm(ServerType::class, $server, [
            'owner_choices' => $user->getServerOwners(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('server_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('server/edit.html.twig', [
            'server' => $server,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'server_delete', methods: ['POST'])]
    public function delete(Request $request, Server $server, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ServerVoter::EDIT, $server);

        /** @var ?string $token */
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete'.$server->getId(), $token)) {
            $entityManager->remove($server);
            $entityManager->flush();
        }

        return $this->redirectToRoute('server_index', [], Response::HTTP_SEE_OTHER);
    }
}
