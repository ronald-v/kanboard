<?php
namespace Projecta\Mcp\OAuth;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
final class Access implements \League\OAuth2\Server\Entities\AccessTokenEntityInterface
{
    use \League\OAuth2\Server\Entities\Traits\EntityTrait;
    use \League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
    private \League\OAuth2\Server\CryptKeyInterface $key;
    public function setPrivateKey(\League\OAuth2\Server\CryptKeyInterface $privateKey): void { $this->key=$privateKey; }
    public function toString(): string
    {
        $config=new \Projecta\Mcp\Config;
        $jwt=Configuration::forAsymmetricSigner(new Sha256,InMemory::plainText($this->key->getKeyContents(),$this->key->getPassPhrase() ?? ''),InMemory::plainText('unused'));
        return $jwt->builder()->issuedBy($config->base())->permittedFor($config->endpoint())->identifiedBy($this->getIdentifier())
            ->issuedAt(new \DateTimeImmutable)->canOnlyBeUsedAfter(new \DateTimeImmutable)->expiresAt($this->getExpiryDateTime())
            ->relatedTo($this->getUserIdentifier())->withClaim('scopes',$this->getScopes())
            ->getToken($jwt->signer(),$jwt->signingKey())->toString();
    }
}
