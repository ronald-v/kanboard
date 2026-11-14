<?php
namespace Projecta\Mcp\OAuth;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
final class Scopes implements \League\OAuth2\Server\Repositories\ScopeRepositoryInterface
{
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return in_array($identifier,['read','write','delete'],true) ? new Scope($identifier) : null;
    }
    public function finalizeScopes(array $scopes,string $grantType,ClientEntityInterface $clientEntity,?string $userIdentifier=null,?string $authCodeId=null): array { return $scopes; }
}
