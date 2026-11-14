<?php
namespace Projecta\Mcp\OAuth;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
final class Codes implements \League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface
{
    public function __construct(private readonly Store $store) {}
    public function getNewAuthCode(): AuthCodeEntityInterface { return new Code; }
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void { $this->store->save('code',$authCodeEntity); }
    public function revokeAuthCode(string $codeId): void { $this->store->revoke('code',$codeId); }
    public function isAuthCodeRevoked(string $codeId): bool { return $this->store->revoked('code',$codeId); }
}
