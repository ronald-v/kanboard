<?php
namespace Projecta\Mcp\OAuth;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
final class Refreshes implements \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface
{
    public function __construct(private readonly Store $store) {}
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface { return new Refresh; }
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void { $this->store->save('refresh',$refreshTokenEntity); }
    public function revokeRefreshToken(string $tokenId): void { $this->store->revoke('refresh',$tokenId); }
    public function isRefreshTokenRevoked(string $tokenId): bool { return $this->store->revoked('refresh',$tokenId); }
}
