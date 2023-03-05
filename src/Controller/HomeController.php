<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sentry\SentrySdk;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HomeController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }

    #[Route('/sentry', name: 'sentry')]
    public function sentry(Request $request, HttpClientInterface $httpClient): Response
    {
        $currentHub = SentrySdk::getCurrentHub();
        $client = $currentHub->getClient();
        $dsn = $client?->getOptions()->getDsn();

        if ($client === null || $dsn === null) {
            throw $this->createNotFoundException('Sentry not configured');
        }


        $envelope = $request->getContent();
        $pieces = explode("\n", $envelope, 2);
        $header = json_decode($pieces[0], true, flags: JSON_THROW_ON_ERROR);

        // It is unclear if we actually need to be checking this.
        if (!isset($header['dsn']) || $header['dsn'] !== (string)$dsn) {
            throw $this->createNotFoundException('Sentry DSN mismatch');
        }

        // Patch the real client IP into the envelope header.
        $header['forwarded_for'] = $request->getClientIp();
        $pieces[0] = json_encode($header, JSON_THROW_ON_ERROR);
        $envelope = implode("\n", $pieces);

        $response = $httpClient->request('POST', $dsn->getEnvelopeApiEndpointUrl(), [
            'body' => $envelope,
            'headers' => [
                'Content-Type' => 'application/x-sentry-envelope',
            ],
        ]);

        return new Response($response->getContent(), Response::HTTP_OK, [
            'Content-Type' => 'application/json',
        ]);
    }

    #[Route('/dashboard', name: 'dashboard')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(): Response
    {
        // TODO
        return $this->render('home/index.html.twig', [
            'controller_name' => 'This is the dashboard...',
        ]);
    }
}
