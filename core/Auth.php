<?php
/**
 * Auth – session tenant helpers.
 * Every catalog query must be scoped with Auth::userId().
 */
class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public static function requireUser(): int
    {
        $id = self::userId();
        if ($id <= 0) {
            header('Location: ?c=auth&a=login');
            exit;
        }
        return $id;
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    public static function username(): string
    {
        return (string) ($_SESSION['username'] ?? '');
    }

    public static function displayName(): string
    {
        return (string) ($_SESSION['display_name'] ?? self::username());
    }
}
