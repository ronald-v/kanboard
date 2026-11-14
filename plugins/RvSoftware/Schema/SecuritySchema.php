<?php
namespace Kanboard\Plugin\RvSoftware\Schema;

function createSecuritySchema($pdo)
{
    $engine = DB_DRIVER === 'mysql' ? ' ENGINE=InnoDB CHARSET=utf8mb4' : '';
    $pdo->exec("CREATE TABLE rv_auth_factors (user_id INT PRIMARY KEY, secret TEXT NOT NULL, last_counter BIGINT NOT NULL DEFAULT -1, generation INT NOT NULL DEFAULT 1, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)$engine");
    $pdo->exec("CREATE TABLE rv_recovery_codes (user_id INT NOT NULL, code_hash VARCHAR(64) NOT NULL, used_at BIGINT NULL, PRIMARY KEY(user_id,code_hash), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)$engine");
    $pdo->exec("CREATE TABLE rv_auth_limits (bucket VARCHAR(64) PRIMARY KEY, attempts INT NOT NULL, expires_at BIGINT NOT NULL)$engine");
    $pdo->exec("CREATE TABLE rv_auth_audit (event_id VARCHAR(32) PRIMARY KEY, actor_id INT NOT NULL, target_id INT NOT NULL, event VARCHAR(80) NOT NULL, created_at BIGINT NOT NULL)$engine");
}
