<?php

/**
 * Each test process gets its own throwaway Postgres database, so several worktrees
 * (and Pest --parallel workers) can run the suite at the same time without sharing tables.
 */

require __DIR__.'/../vendor/autoload.php';

$database = 'pm_test_'.getmypid();
$server = new PDO(sprintf('pgsql:host=%s;port=%s;dbname=postgres', $_ENV['DB_HOST'], $_ENV['DB_PORT']), $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
$server->exec("DROP DATABASE IF EXISTS {$database}");
$server->exec("CREATE DATABASE {$database}");

$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $database;
putenv("DB_DATABASE={$database}");

register_shutdown_function(fn () => $server->exec("DROP DATABASE IF EXISTS {$database} WITH (FORCE)"));
