<?php
namespace Projecta\Mcp\OAuth;
final class Scope implements \League\OAuth2\Server\Entities\ScopeEntityInterface
{
    use \League\OAuth2\Server\Entities\Traits\EntityTrait;
    public function __construct(string $id) { $this->identifier=$id; }
    public function jsonSerialize(): string { return $this->identifier; }
}
