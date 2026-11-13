<?php

namespace Kanboard\Plugin\RvSoftware\Schema;

function createTimeSchema($pdo)
{
    $mysql = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
    $identity = $mysql ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $engine = $mysql ? ' ENGINE=InnoDB CHARSET=utf8mb4' : '';
    $pdo->exec("CREATE TABLE rv_time_legacy (entity_type VARCHAR(16) NOT NULL, entity_id INT NOT NULL,
        hours DECIMAL(20,6) NOT NULL, captured_at BIGINT NOT NULL,
        PRIMARY KEY(entity_type,entity_id))$engine");
    $statement = $pdo->prepare("INSERT INTO rv_time_legacy SELECT 'task',id,COALESCE(time_spent,0),? FROM tasks");
    $statement->execute(array(time()));
    $statement = $pdo->prepare("INSERT INTO rv_time_legacy SELECT 'subtask',id,COALESCE(time_spent,0),? FROM subtasks");
    $statement->execute(array(time()));
    $pdo->exec("CREATE TABLE rv_time_locks (user_id INT PRIMARY KEY, revision INT NOT NULL DEFAULT 0,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)$engine");
    $pdo->exec("CREATE TABLE rv_time_meta (session_id INT PRIMARY KEY, duration_seconds INT NULL,
        source VARCHAR(24) NOT NULL, note TEXT NOT NULL, operation_key VARCHAR(128) NOT NULL UNIQUE,
        request_hash VARCHAR(64) NOT NULL, version INT NOT NULL DEFAULT 1,
        FOREIGN KEY(session_id) REFERENCES subtask_time_tracking(id) ON DELETE RESTRICT)$engine");
    $pdo->exec("CREATE TABLE rv_time_audit (id $identity, session_id INT NOT NULL,
        actor_id INT NOT NULL, action VARCHAR(24) NOT NULL, reason TEXT NOT NULL,
        before_json TEXT NOT NULL, after_json TEXT NOT NULL, created_at BIGINT NOT NULL,
        operation_key VARCHAR(128) NOT NULL UNIQUE, request_hash VARCHAR(64) NOT NULL,
        FOREIGN KEY(session_id) REFERENCES subtask_time_tracking(id) ON DELETE RESTRICT)$engine");
    $pdo->exec('CREATE INDEX rv_time_audit_session ON rv_time_audit(session_id)');
}
