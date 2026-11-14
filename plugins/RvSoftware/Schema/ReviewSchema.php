<?php
namespace Kanboard\Plugin\RvSoftware\Schema;

function createReviewSchema($pdo)
{
    $pdo->exec("ALTER TABLE rv_time_meta ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'pending'");
    $pdo->exec('ALTER TABLE rv_time_meta ADD COLUMN billable INT NULL');
    $pdo->exec('ALTER TABLE rv_time_meta ADD COLUMN approved_by INT NULL');
    $pdo->exec('ALTER TABLE rv_time_meta ADD COLUMN approved_at BIGINT NULL');
    $pdo->exec('ALTER TABLE rv_time_meta ADD COLUMN approved_project_id INT NULL');
    $pdo->exec('ALTER TABLE rv_time_meta ADD COLUMN rectified_at BIGINT NULL');
}
