<?php
namespace Kanboard\Plugin\RvSoftware\Schema;

function createBagSchema($pdo)
{
    $mysql = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
    $identity = $mysql ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $engine = $mysql ? ' ENGINE=InnoDB CHARSET=utf8mb4' : '';
    $pdo->exec("CREATE TABLE rv_project_locks (project_id INT PRIMARY KEY, revision INT NOT NULL DEFAULT 0,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE)$engine");
    $pdo->exec("CREATE TABLE rv_clients (id $identity, name VARCHAR(200) NOT NULL, created_at BIGINT NOT NULL)$engine");
    $pdo->exec("CREATE TABLE rv_project_clients (project_id INT PRIMARY KEY, client_id INT NOT NULL,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE RESTRICT,
        FOREIGN KEY(client_id) REFERENCES rv_clients(id) ON DELETE RESTRICT)$engine");
    $pdo->exec("CREATE TABLE rv_bags (id $identity, project_id INT NOT NULL, name VARCHAR(200) NOT NULL,
        valid_from BIGINT NULL, valid_until BIGINT NULL, archived INT NOT NULL DEFAULT 0,
        created_at BIGINT NOT NULL, FOREIGN KEY(project_id) REFERENCES rv_project_clients(project_id) ON DELETE RESTRICT)$engine");
    $pdo->exec("CREATE TABLE rv_bag_entries (id $identity, bag_id INT NOT NULL, seconds BIGINT NOT NULL,
        kind VARCHAR(24) NOT NULL, session_id INT NULL, session_version INT NULL,
        actor_id INT NOT NULL, reason TEXT NOT NULL, created_at BIGINT NOT NULL,
        operation_key VARCHAR(128) NOT NULL UNIQUE, request_hash VARCHAR(64) NOT NULL,
        FOREIGN KEY(bag_id) REFERENCES rv_bags(id) ON DELETE RESTRICT,
        FOREIGN KEY(session_id) REFERENCES subtask_time_tracking(id) ON DELETE RESTRICT)$engine");
    $pdo->exec('CREATE INDEX rv_bag_entries_bag ON rv_bag_entries(bag_id)');
    $pdo->exec('CREATE UNIQUE INDEX rv_bag_entries_origin ON rv_bag_entries(session_id,session_version)');
    $pdo->exec("CREATE TABLE rv_commercial_audit (id $identity, project_id INT NOT NULL,
        actor_id INT NOT NULL, action VARCHAR(24) NOT NULL, reason TEXT NOT NULL,
        before_json TEXT NOT NULL, after_json TEXT NOT NULL, created_at BIGINT NOT NULL,
        operation_key VARCHAR(128) NOT NULL UNIQUE, request_hash VARCHAR(64) NOT NULL,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE RESTRICT)$engine");
}
