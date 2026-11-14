<?php
namespace Kanboard\Plugin\RvSoftware\Schema;

function createApiSchema($pdo)
{
    $engine=DB_DRIVER==='mysql' ? ' ENGINE=InnoDB CHARSET=utf8mb4' : '';
    $pdo->exec("CREATE TABLE rv_api_tokens (token_hash VARCHAR(64) PRIMARY KEY, user_id INT NOT NULL, label VARCHAR(100) NOT NULL, scopes VARCHAR(200) NOT NULL, factor_hash VARCHAR(64) NOT NULL, password_hash VARCHAR(64) NOT NULL, expires_at BIGINT NOT NULL, created_at BIGINT NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)$engine");
    $pdo->exec('CREATE INDEX rv_api_user ON rv_api_tokens(user_id)');
    $pdo->exec("CREATE TABLE rv_api_operations (operation_key VARCHAR(64) PRIMARY KEY, user_id INT NOT NULL, request_hash VARCHAR(64) NOT NULL, result TEXT NULL, created_at BIGINT NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)$engine");
}
