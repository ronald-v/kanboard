<?php

namespace Kanboard\Plugin\RvSoftware\Schema;

const VERSION = 6;

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

function version_5($pdo)
{
    require_once __DIR__.'/BagSchema.php';
    createBagSchema($pdo);
}

function version_6($pdo)
{
    $pdo->exec('ALTER TABLE rv_time_meta ADD COLUMN bag_id INT NULL');
    $pdo->exec('ALTER TABLE rv_time_meta ADD CONSTRAINT rv_time_meta_bag FOREIGN KEY(bag_id) REFERENCES rv_bags(id) ON DELETE RESTRICT');
}
