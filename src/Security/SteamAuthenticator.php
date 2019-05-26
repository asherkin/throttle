<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SteamAuthenticator extends AbstractGuardAuthenticator
{
    private const OPENID_URL = 'https://steamcommunity.com/openid/login';
    private const OPENID_CLAIM_REGEX = '#^https?://steamcommunity.com/openid/id/(\d+)$#';

    private $router;
    private $httpClient;

    public function __construct(RouterInterface $router, HttpClientInterface $httpClient)
    {
        $this->router = $router;
        $this->httpClient = $httpClient;
    }

    public function supports(Request $request)
    {
        return $request->attributes->get('_route') === 'login' && $request->query->get('openid_mode');
    }

    public function getCredentials(Request $request)
    {
        $mode = $request->query->get('openid_mode');
        if ($mode !== 'id_res') {
            throw new CustomUserMessageAuthenticationException('OpenID transaction in unexpected state');
        }

        $endpoint = $request->query->get('openid_op_endpoint');
        if ($endpoint !== self::OPENID_URL) {
            throw new CustomUserMessageAuthenticationException('Response from unexpected OpenID OP');
        }

        $returnTo = $request->query->get('openid_return_to');
        $currentUrl = $request->getUriForPath($request->getPathInfo());
        if (preg_match('/^'.preg_quote($currentUrl, '/').'(?:\?|$)/', $returnTo) !== 1) {
            throw new CustomUserMessageAuthenticationException('OpenID return URL does not match');
        }

        $returnToQuery = substr($returnTo, strlen($currentUrl) + 1);
        parse_str($returnToQuery, $returnToQuery);
        foreach ($returnToQuery as $k => $v) {
            if ($request->query->get($k) !== $v) {
                throw new CustomUserMessageAuthenticationException('OpenID return URL param does not match');
            }
        }

        $claimedId = $request->query->get('openid_claimed_id');
        $claimedIdMatches = null;
        if (preg_match(self::OPENID_CLAIM_REGEX, $claimedId, $claimedIdMatches, PREG_UNMATCHED_AS_NULL) !== 1) {
            throw new CustomUserMessageAuthenticationException('OpenID claimed ID invalid');
        }
        $steamId = $claimedIdMatches[1];

        $signed = explode(',', $request->query->get('openid_signed'));
        $required = ['op_endpoint', 'return_to', 'response_nonce', 'assoc_handle', 'claimed_id', 'identity'];
        if (!empty(array_diff($required, $signed))) {
            throw new CustomUserMessageAuthenticationException('Not all required OpenID params are signed');
        }

        $params = array_filter($request->query->all(), function ($k) {
            return substr($k, 0, 7) === 'openid_';
        }, ARRAY_FILTER_USE_KEY);

        foreach ($params as $k => $v) {
            unset($params[$k]);
            $k[6] = '.';
            $params[$k] = $v;
        }

        $params['openid.mode'] = 'check_authentication';

        $response = $this->httpClient->request('POST', self::OPENID_URL, [
            'body' => $params,
        ])->getContent();

        if (preg_match('/^is_valid:true$/m', $response) !== 1) {
            throw new CustomUserMessageAuthenticationException('OpenID response could not be validated');
        }

        return $steamId;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        return $userProvider->loadUserByUsername($credentials);
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $request->getSession()->getFlashBag()->add('error_auth', strtr($exception->getMessageKey(), $exception->getMessageData()));

        $returnUrl = $request->get('return', $this->router->generate('index'));

        return new RedirectResponse($returnUrl);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $returnUrl = $request->get('return', $this->router->generate('dashboard'));

        return new RedirectResponse($returnUrl);
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        $currentReturn = $request->getPathInfo();
        $currentQueryString = $request->getQueryString();
        if (strlen($currentQueryString)) {
            $currentReturn .= '?'.$currentQueryString;
        }

        $returnTo = $this->router->generate('login', [
            'return' => $request->get('return', $currentReturn),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $params = [
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',
            'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.return_to' => $returnTo,
            'openid.realm' => $this->router->generate('index', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        $url = self::OPENID_URL.'?'.http_build_query($params, '', '&');

        return new RedirectResponse($url);
    }

    public function supportsRememberMe()
    {
        return true;
    }
}
