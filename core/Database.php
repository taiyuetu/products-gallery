<?php
/**
 * Database ?C PDO singleton.
 * All queries go through this class; charset is always utf8mb4.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $cfg = require ROOT . '/config/database.php';
            // connect_timeout=5 prevents hanging when MySQL is not reachable
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s;connect_timeout=5',
                $cfg['host'], $cfg['port'], $cfg['dbname'], $cfg['charset']
            );
            self::$instance = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        }
        return self::$instance;
    }

    // Prevent instantiation
    private function __construct() {}
    private function __clone() {}
}
