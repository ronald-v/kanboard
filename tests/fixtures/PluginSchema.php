<?php
namespace Kanboard\Plugin\HarnessSchema\Schema;

const VERSION = 2;

function version_1(\PDO $pdo)
{
    $pdo->exec('CREATE TABLE harness_schema_probe (id INTEGER NOT NULL PRIMARY KEY, value INTEGER NOT NULL)');
    $pdo->exec('INSERT INTO harness_schema_probe (id,value) VALUES (1,1)');
}

function version_2(\PDO $pdo)
{
    $pdo->exec('UPDATE harness_schema_probe SET value=2 WHERE id=1');
}

namespace Kanboard\Plugin\HarnessFailure\Schema;

const VERSION = 2;

function version_1(\PDO $pdo)
{
    $pdo->exec('INSERT INTO harness_schema_failure (id) VALUES (1)');
}

function version_2(\PDO $pdo)
{
    throw new \PDOException('Synthetic migration failure');
}
