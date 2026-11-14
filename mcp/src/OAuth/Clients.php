<?php
namespace Projecta\Mcp\OAuth;
final class Clients implements \League\OAuth2\Server\Repositories\ClientRepositoryInterface
{
    public function __construct(private readonly \Projecta\Mcp\Config $config) {}
    public function getClientEntity(string $clientIdentifier): ?\League\OAuth2\Server\Entities\ClientEntityInterface
    {
        return hash_equals($this->config->clientId(),$clientIdentifier) ? new Client($clientIdentifier,$this->config->redirects()) : null;
    }
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        return hash_equals($this->config->clientId(),$clientIdentifier) && in_array($grantType,['authorization_code','refresh_token'],true);
    }
}
