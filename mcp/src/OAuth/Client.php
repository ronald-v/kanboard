<?php
namespace Projecta\Mcp\OAuth;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
final class Client implements \League\OAuth2\Server\Entities\ClientEntityInterface
{
    use ClientTrait,EntityTrait;
    public function __construct(string $id, array $redirects) { $this->identifier=$id; $this->name='ChatGPT · Projecta'; $this->redirectUri=$redirects; $this->isConfidential=false; }
}
