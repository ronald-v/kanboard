<?php
namespace Projecta\Mcp\OAuth;
final class Code implements \League\OAuth2\Server\Entities\AuthCodeEntityInterface
{
    use \League\OAuth2\Server\Entities\Traits\EntityTrait;
    use \League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
    use \League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
}
