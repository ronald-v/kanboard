<?php

namespace Kanboard\Plugin\RvSoftware\Schema;

const VERSION = 4;

function version_1($pdo)
{
    // Baseline only, no DDL. Business schemas are introduced by their features.
}

function version_2($pdo)
{
    require_once __DIR__.'/TimeSchema.php';
    createTimeSchema($pdo);
}

function version_3($pdo)
{
    $pdo->exec('CREATE TABLE rv_task_time_locks (task_id INT PRIMARY KEY, revision INT NOT NULL DEFAULT 0, FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE) ENGINE=InnoDB CHARSET=utf8mb4');
}

function version_4($pdo)
{
    require_once __DIR__.'/ReviewSchema.php';
    createReviewSchema($pdo);
}
