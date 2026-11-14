<?php
namespace Projecta\Mcp\OAuth;
final class User implements \League\OAuth2\Server\Entities\UserEntityInterface
{
    use \League\OAuth2\Server\Entities\Traits\EntityTrait;
    public function __construct(int $id) { $this->identifier=(string)$id; }
}
