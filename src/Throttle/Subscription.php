<?php

namespace Throttle;

use Silex\Application;

class Subscription
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

    private static function validateWebhook(Application $app)
    {
        $params = $app['request']->request->all();

        if (!isset($params['p_signature'])) {
            $app->abort(403, 'Missing signature');
        }

        $signature = base64_decode($params['p_signature']);
        unset($params['p_signature']);

        ksort($params);
        foreach ($params as $k => $v) {
            if(!in_array(gettype($v), array('object', 'array'))) {
                $params[$k] = (string)$v;
            }
        }
        $params = serialize($params);

        if (!openssl_verify($params, $signature, self::PUBLIC_KEY, OPENSSL_ALGO_SHA1)) {
            $app->abort(403, 'Invalid signature');
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

    public function webhook(Application $app)
    {
        self::validateWebhook($app);

        $log = new \Monolog\Logger('throttle.paddle');
        $log->pushHandler(new \Monolog\Handler\StreamHandler($app['root'].'/logs/paddle.log'));

        $params = $app['request']->request;

        $paramArray = $app['request']->request->all();
        unset($paramArray['p_signature']);
        $log->info('Webhook received!', ['request' => $paramArray]);
        unset($paramArray);

        switch ($params->get('alert_name')) {
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

        return '';
    }

    public function subscribe(Application $app)
    {
        if (!$app['feature']['subscriptions']) {
            $app->abort(404);
        }

        if (!$app['user']) {
            $app->abort(401);
        }

        return $app['twig']->render('subscribe.html.twig', [
            'price_table' => self::getPriceAdjustmentTable(),
        ]);
    }
}

