<?php
namespace Projecta\Mcp;
final class Http
{
    public static function request(): \Psr\Http\Message\ServerRequestInterface
    {
        $config=new Config;
        $request=new \Nyholm\Psr7\ServerRequest($_SERVER['REQUEST_METHOD'] ?? 'GET',$config->base().($_SERVER['REQUEST_URI'] ?? '/'),getallheaders(),file_get_contents('php://input'));
        return $request->withQueryParams($_GET)->withParsedBody($_POST);
    }
    public static function emit(\Psr\Http\Message\ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name=>$values) { foreach ($values as $value) { header($name.': '.$value,false); } }
        header('Cache-Control: no-store');
        echo (string)$response->getBody();
    }
    public static function json(array $data,int $status=200): void
    {
        self::emit(new \Nyholm\Psr7\Response($status,['Content-Type'=>'application/json'],json_encode($data)));
    }
    public static function bounded(): bool
    {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>262144 || strlen(file_get_contents('php://input',false,null,0,262145))>262144) {
            http_response_code(413); header('Content-Type: application/json'); echo '{"error":"Request too large"}'; return false;
        }
        return true;
    }
}
