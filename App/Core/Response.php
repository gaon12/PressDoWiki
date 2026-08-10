<?php

namespace PressDo\App\Core;

use InvalidArgumentException;
use PressDo\App\Http\Security\LocalRedirect;

final class Response
{
    public static function redirect(string $location, int $status = 302): never
    {
        $location = LocalRedirect::sanitize($location);
        header('Location: ' . $location, true, $status);
        exit;
    }

    public static function notFound(string $message = 'Not Found'): never
    {
        http_response_code(404);
        echo $message;
        exit;
    }

    public static function html(string $content, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo $content;
        exit;
    }

    public static function text(string $content, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $content;
        exit;
    }

    public static function methodNotAllowed(string $allowedMethod): never
    {
        $allowedMethod = strtoupper($allowedMethod);
        if (preg_match('/^[A-Z]+$/D', $allowedMethod) !== 1) {
            throw new InvalidArgumentException('An allowed HTTP method must contain only ASCII letters.');
        }

        header('Allow: ' . $allowedMethod);
        self::text('Method Not Allowed', 405);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
