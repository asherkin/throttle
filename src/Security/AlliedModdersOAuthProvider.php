<?php

namespace App\Security;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class AlliedModdersOAuthProvider extends AbstractProvider
{
    use BearerAuthorizationTrait;

    public const ACCESS_TOKEN_RESOURCE_OWNER_ID = 'id';

    public function getBaseAuthorizationUrl(): string
    {
        return 'https://forums.alliedmods.net/oauth/auth.php';
    }

    /**
     * @param mixed[] $params
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        return 'https://forums.alliedmods.net/oauth/token.php';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return 'https://forums.alliedmods.net/oauth/userinfo.php';
    }

    /**
     * @return string[]
     */
    protected function getDefaultScopes(): array
    {
        return [];
    }

    /**
     * @param mixed[]|string $data
     *
     * @throws IdentityProviderException
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (!\is_array($data)) {
            throw new IdentityProviderException('Malformed response', 0, $data);
        }

        if (isset($data['error']) || $response->getStatusCode() !== 200) {
            $error = $data['error_description'] ?? $data['error'] ?? 'Unknown error';

            throw new IdentityProviderException($error, 0, $data);
        }
    }

    /**
     * @param mixed[] $response
     */
    protected function createResourceOwner(array $response, AccessToken $token): ResourceOwnerInterface
    {
        return new GenericResourceOwner($response, self::ACCESS_TOKEN_RESOURCE_OWNER_ID);
    }
}
