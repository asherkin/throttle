<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class Subscription extends AbstractController
{
    const VENDOR_ID = 21783;

    const PUBLIC_KEY = <<<EOT
-----BEGIN PUBLIC KEY-----
-----END PUBLIC KEY-----
EOT;

    const MONTHLY_PRODUCT_ID = 519792;
    const MONTHLY_SECRET_KEY = '';

    const QUARTERLY_PRODUCT_ID = 519818;
    const QUARTERLY_SECRET_KEY = '';

    const YEARLY_PRODUCT_ID = 519794;
    const YEARLY_SECRET_KEY = '';

    /**
     * @Route("/paddle_webhook", defaults={"_format": "json"}, methods={"POST"}, name="paddle_webhook")
     */
    public function webhook(Request $request, LoggerInterface $logger)
    {
        $this->validateWebhook($request);

        $logger->info('Paddle webhook received', [
            'request' => $request->request->all(),
        ]);

        switch ($request->request->get('alert_name')) {
            case 'subscription_created';
                break;
            case 'subscription_updated';
                break;
            case 'subscription_cancelled';
                break;
            case 'subscription_payment_succeeded';
                break;
            case 'subscription_payment_failed';
                break;
            case 'subscription_payment_refunded';
                break;
        }

        return $this->json([]);
    }

    /**
     * @Route("/subscribe", name="subscribe")
     */
    public function subscribe(\App\Twig\AppVariable $appVar)
    {
        // TODO: This is a hack, feature-flags need implementing properly.
        if (!$appVar->getFeature()['subscriptions']) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('subscribe.html.twig', [
            'price_table' => self::getPriceAdjustmentTable(),
        ]);
    }

    private function validateWebhook(Request $request)
    {
        // TODO: Re-write this function as a custom firewall/guard/authenticator/voter/whatever.
        $params = $request->request->all();

        if (!isset($params['p_signature'])) {
            throw new AccessDeniedHttpException('Missing signature');
        }

        $signature = base64_decode($params['p_signature']);
        unset($params['p_signature']);
        if ($signature === false) {
            throw new AccessDeniedHttpException('Failed to decode signature');
        }

        ksort($params);
        foreach ($params as $k => $v) {
            if(!in_array(gettype($v), array('object', 'array'))) {
                $params[$k] = (string)$v;
            }
        }
        $params = serialize($params);

        if (!openssl_verify($params, $signature, self::PUBLIC_KEY, OPENSSL_ALGO_SHA1)) {
            throw new AccessDeniedHttpException('Invalid signature');
        }

        return true;
    }

    private static function getPriceAdjustmentTable()
    {
        $output = [];

        for ($i = 0; $i <= 120; ++$i) {
            $price = number_format($i, 2, '.', '');
            $output[] = [
                'price'     => $price,
                'monthly'   => md5($price . self::MONTHLY_SECRET_KEY),
                'quarterly' => md5($price . self::QUARTERLY_SECRET_KEY),
                'yearly'    => md5($price . self::YEARLY_SECRET_KEY),
            ];
        }

        return $output;
    }
}

