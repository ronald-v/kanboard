<?php
namespace Kanboard\Plugin\RvSoftware\Schema;
function createOAuthSchema($pdo)
{
    $engine=DB_DRIVER==='mysql' ? ' ENGINE=InnoDB CHARSET=utf8mb4' : '';
    $pdo->exec("CREATE TABLE rv_oauth_grants (token_hash VARCHAR(64) PRIMARY KEY, kind VARCHAR(10) NOT NULL, user_id INT NOT NULL, client_id VARCHAR(100) NOT NULL, resource VARCHAR(300) NOT NULL, scopes VARCHAR(200) NOT NULL, factor_hash VARCHAR(64) NOT NULL, password_hash VARCHAR(64) NOT NULL, expires_at BIGINT NOT NULL, revoked INT NOT NULL DEFAULT 0, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)$engine");
    $pdo->exec('CREATE INDEX rv_oauth_user ON rv_oauth_grants(user_id)');
}
