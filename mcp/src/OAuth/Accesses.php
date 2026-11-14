<?php
namespace Projecta\Mcp\OAuth;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
final class Accesses implements \League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface
{
    public function __construct(private readonly Store $store) {}
    public function getNewToken(ClientEntityInterface $clientEntity,array $scopes,?string $userIdentifier=null): AccessTokenEntityInterface
    {
        $token=new Access; $token->setClient($clientEntity);
        if ($userIdentifier!==null) { $token->setUserIdentifier($userIdentifier); }
        foreach ($scopes as $scope) { $token->addScope($scope); }
        return $token;
    }
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void { $this->store->save('access',$accessTokenEntity); }
    public function revokeAccessToken(string $tokenId): void { $this->store->revoke('access',$tokenId); }
    public function isAccessTokenRevoked(string $tokenId): bool { return $this->store->revoked('access',$tokenId); }
}
