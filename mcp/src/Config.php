<?php
namespace Projecta\Mcp;

final class Config
{
    public function base(): string
    {
        $base=rtrim(getenv('PROJECTA_BASE_URL') ?: 'https://projecta.rvsoftware.site','/');
        $url=parse_url($base);
        $local=in_array($url['host'] ?? '',['127.0.0.1','localhost'],true);
        if (! isset($url['scheme'],$url['host']) || ($url['scheme']!=='https' && ! ($local && $url['scheme']==='http')) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || strlen($base)>280) { throw new \RuntimeException('Invalid Projecta public origin'); }
        return $base;
    }
    public function endpoint(): string { return $this->base().'/mcp/'; }
    public function clientId(): string
    {
        $id=getenv('PROJECTA_OAUTH_CLIENT_ID') ?: 'projecta-chatgpt';
        if (! preg_match('/^[A-Za-z0-9_.:-]{1,100}$/D',$id)) { throw new \RuntimeException('Invalid OAuth client ID'); }
        return $id;
    }
    public function redirects(): array
    {
        $uris=json_decode(getenv('PROJECTA_OAUTH_REDIRECT_URIS') ?: '[]',true);
        if (! is_array($uris) || ! array_is_list($uris) || ! $uris) { throw new \RuntimeException('Configure exact OAuth redirect URIs'); }
        foreach ($uris as $uri) {
            $url=is_string($uri) ? parse_url($uri) : false;
            $local=$url && in_array($url['host'] ?? '',['127.0.0.1','localhost'],true);
            if (! $url || (($url['scheme'] ?? '')!=='https' && ! ($local && ($url['scheme'] ?? '')==='http')) || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || str_contains($uri,'*')) { throw new \RuntimeException('Invalid OAuth redirect URI'); }
        }
        return $uris;
    }
    public function privateDirectory(): string
    {
        $configured=getenv('PROJECTA_PRIVATE_DIR');
        if (! $configured) { throw new \RuntimeException('Configure private MCP directory'); }
        $path=realpath($configured);
        if (! $path || ! is_dir($path) || ! is_writable($path)) { throw new \RuntimeException('Configure private MCP directory'); }
        $root=realpath(! empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__,2));
        if ($root && str_starts_with(strtolower(str_replace('\\','/',$path)).'/',strtolower(str_replace('\\','/',$root)).'/')) { throw new \RuntimeException('MCP keys and sessions must be outside the document root'); }
        return $path;
    }
    public function privateKey(): string { return $this->privateDirectory().'/oauth-private.pem'; }
    public function publicKey(): string { return $this->privateDirectory().'/oauth-public.pem'; }
}
