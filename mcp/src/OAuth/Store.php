<?php
namespace Projecta\Mcp\OAuth;
final class Store
{
    public function __construct(private readonly \Pimple\Container $container, private readonly \Projecta\Mcp\Config $config) {}
    public function save(string $kind, object $entity): void
    {
        $access=$kind==='refresh' ? $entity->getAccessToken() : $entity;
        $id=(int)$access->getUserIdentifier();
        $factor=$this->container['rvSecurityModel']->factor($id);
        $user=$this->container['userModel']->getById($id);
        if (! $factor || ! $user || ! $user['is_active']) { throw new \RuntimeException('OAuth user unavailable'); }
        $this->container['rvSecurityModel']->sql('INSERT INTO rv_oauth_grants(token_hash,kind,user_id,client_id,resource,scopes,factor_hash,password_hash,expires_at) VALUES (?,?,?,?,?,?,?,?,?)',[
            hash('sha256',$entity->getIdentifier()),$kind,$id,$access->getClient()->getIdentifier(),$this->config->endpoint(),
            implode(' ',array_map(fn($scope)=>$scope->getIdentifier(),$access->getScopes())),hash('sha256',$factor['secret']),hash('sha256',(string)$user['password']),$entity->getExpiryDateTime()->getTimestamp()]);
    }
    public function revoke(string $kind,string $id): void
    {
        $this->container['rvSecurityModel']->sql('UPDATE rv_oauth_grants SET revoked=1 WHERE token_hash=? AND kind=?',[hash('sha256',$id),$kind]);
    }
    public function revoked(string $kind,string $id): bool
    {
        $model=$this->container['rvSecurityModel'];
        $suffix=DB_DRIVER==='mysql' && $this->container['db']->getConnection()->inTransaction() ? ' FOR UPDATE' : '';
        $row=$model->sql('SELECT * FROM rv_oauth_grants WHERE token_hash=? AND kind=?'.$suffix,[hash('sha256',$id),$kind])->fetch(\PDO::FETCH_ASSOC);
        if (! $row || $row['revoked'] || $row['expires_at']<=time() || $row['resource']!==$this->config->endpoint()) { return true; }
        $user=$this->container['userModel']->getById($row['user_id']); $factor=$model->factor($row['user_id']);
        return ! $user || ! $user['is_active'] || ! $factor || ! hash_equals($row['password_hash'],hash('sha256',(string)$user['password'])) || ! hash_equals($row['factor_hash'],hash('sha256',$factor['secret']));
    }
}
