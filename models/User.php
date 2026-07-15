<?php
/**
 * User model – authentication and account management.
 */
class User extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = [
        'username', 'password_hash', 'display_name', 'role',
    ];

    public static function findByUsername(string $username): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT * FROM `users` WHERE username = ? LIMIT 1"
        );
        $stmt->execute([trim($username)]);
        return $stmt->fetch() ?: null;
    }

    public static function ensureTable(): void
    {
        static::db()->exec("
            CREATE TABLE IF NOT EXISTS `users` (
              `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
              `username`      VARCHAR(50)   NOT NULL,
              `password_hash` VARCHAR(255)  NOT NULL,
              `display_name`  VARCHAR(100)  DEFAULT NULL,
              `role`          ENUM('admin','user') NOT NULL DEFAULT 'user',
              `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
              `updated_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public static function isEmpty(): bool
    {
        return (int) static::db()->query("SELECT COUNT(*) FROM `users`")->fetchColumn() === 0;
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function createAdmin(
        string $username,
        string $password,
        string $displayName = ''
    ): int {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = static::db()->prepare(
            "INSERT INTO `users` (username, password_hash, display_name, role)
             VALUES (?, ?, ?, 'admin')"
        );
        $stmt->execute([$username, $hash, $displayName ?: $username]);
        return (int) static::db()->lastInsertId();
    }

    /** Self-service registration (role = user). */
    public static function register(
        string $username,
        string $password,
        string $displayName = ''
    ): int {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = static::db()->prepare(
            "INSERT INTO `users` (username, password_hash, display_name, role)
             VALUES (?, ?, ?, 'user')"
        );
        $stmt->execute([$username, $hash, $displayName ?: $username]);
        return (int) static::db()->lastInsertId();
    }

    public static function createUser(
        string $username,
        string $password,
        string $displayName = '',
        string $role = 'user'
    ): int {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = static::db()->prepare(
            "INSERT INTO `users` (username, password_hash, display_name, role)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$username, $hash, $displayName ?: $username, $role]);
        return (int) static::db()->lastInsertId();
    }
}
