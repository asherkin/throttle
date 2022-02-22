<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SteamOpenIdAuthenticator extends AbstractAuthenticator
{
    private const EXTERNAL_ACCOUNT_KIND = 'steam';
    private const OPENID_URL = 'https://steamcommunity.com/openid/login';
    private const OPENID_CLAIM_REGEX = '#^https?://steamcommunity.com/openid/id/(\d+)$#';
    private const WEB_API_ENDPOINT = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/';

    private HttpClientInterface $httpClient;
    private UserManager $userManager;
    private AuthenticationSuccessHandlerInterface $successHandler;
    private AuthenticationFailureHandlerInterface $failureHandler;
    private string $steamApiKey;

    public function __construct(HttpClientInterface $httpClient, UserManager $userManager, AuthenticationSuccessHandlerInterface $successHandler, AuthenticationFailureHandlerInterface $failureHandler, string $steamApiKey)
    {
        $this->httpClient = $httpClient;
        $this->userManager = $userManager;
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
        $this->steamApiKey = $steamApiKey;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'login_steam';
    }

    /**
     * {@inheritDoc}
     */
    public function authenticate(Request $request): Passport
    {
        if (!$request->query->has('openid_mode')) {
            throw new AuthenticationCredentialsNotFoundException();
        }

        $steamId = $this->validateOpenIdResponse($request);

        $externalAccount = $this->userManager->findOrCreateExternalAccount(
            self::EXTERNAL_ACCOUNT_KIND, $steamId, $this->getSteamDisplayName($steamId));

        $user = $externalAccount->getUser();

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), function () use ($user) {
            return $user;
        }), [
            new RememberMeBadge(),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    /**
     * {@inheritDoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($exception instanceof AuthenticationCredentialsNotFoundException) {
            $params = [
                'openid.ns' => 'http://specs.openid.net/auth/2.0',
                'openid.mode' => 'checkid_setup',
                'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
                'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
                'openid.return_to' => $request->getUri(),
                'openid.realm' => $request->getUriForPath('/'),
            ];

            $url = self::OPENID_URL.'?'.http_build_query($params, '', '&');

            return new RedirectResponse($url);
        }

        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    /**
     * @throws AuthenticationException
     *
     * @return string The claimed SteamID
     */
    private function validateOpenIdResponse(Request $request): string
    {
        $mode = $request->query->get('openid_mode');
        if ($mode !== 'id_res') {
            throw new CustomUserMessageAuthenticationException('OpenID transaction in unexpected state.');
        }

        $endpoint = $request->query->get('openid_op_endpoint');
        if ($endpoint !== self::OPENID_URL) {
            throw new CustomUserMessageAuthenticationException('Response from unexpected OpenID OP.');
        }

        $returnTo = $request->query->get('openid_return_to', '');
        $currentUrl = $request->getUriForPath($request->getPathInfo());
        if (preg_match('/^'.preg_quote($currentUrl, '/').'(?:\?|$)/', $returnTo) !== 1) {
            throw new CustomUserMessageAuthenticationException('OpenID return URL does not match.');
        }

        $returnToQuery = mb_substr($returnTo, mb_strlen($currentUrl) + 1);
        parse_str($returnToQuery, $returnToQuery);
        foreach ($returnToQuery as $k => $v) {
            if ($request->query->get($k) !== $v) {
                throw new CustomUserMessageAuthenticationException('OpenID return URL param does not match.');
            }
        }

        $claimedId = $request->query->get('openid_claimed_id', '');
        $claimedIdMatches = null;
        if (preg_match(self::OPENID_CLAIM_REGEX, $claimedId, $claimedIdMatches, \PREG_UNMATCHED_AS_NULL) !== 1) {
            throw new CustomUserMessageAuthenticationException('OpenID claimed ID invalid.');
        }
        $steamId = $claimedIdMatches[1];

        $signed = explode(',', $request->query->get('openid_signed', ''));
        $required = ['op_endpoint', 'return_to', 'response_nonce', 'assoc_handle', 'claimed_id', 'identity'];
        if (\count(array_diff($required, $signed)) > 0) {
            throw new CustomUserMessageAuthenticationException('Not all required OpenID params are signed.');
        }

        $params = array_filter($request->query->all(), function ($k) {
            return mb_substr($k, 0, 7) === 'openid_';
        }, \ARRAY_FILTER_USE_KEY);

        foreach ($params as $k => $v) {
            unset($params[$k]);
            $k[6] = '.';
            $params[$k] = $v;
        }

        $params['openid.mode'] = 'check_authentication';

        try {
            $response = $this->httpClient->request('POST', self::OPENID_URL, [
                'body' => $params,
            ])->getContent();
        } catch (HttpClientExceptionInterface $e) {
            throw new CustomUserMessageAuthenticationException('Failed to validate OpenID response with Steam.', [], 0, $e);
        }

        if (preg_match('/^is_valid:true$/m', $response) !== 1) {
            throw new CustomUserMessageAuthenticationException('OpenID login expired, please try again.');
        }

        return $steamId;
    }

    private function getSteamDisplayName(string $steamId): string
    {
        $response = $this->httpClient->request('GET', self::WEB_API_ENDPOINT, [
            'query' => [
                'key' => $this->steamApiKey,
                'steamids' => $steamId,
            ],
        ])->toArray();

        return $response['response']['players'][0]['personaname'] ?? $steamId;
    }
}
