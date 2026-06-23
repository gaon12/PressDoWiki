<?php
namespace PressDo\App\Core;

final class Response
{
    public static function redirect(string $location, int $status = 302): never
    {
        header('Location: '.$location, true, $status);
        exit;
    }

    public static function notFound(string $message = 'Not Found'): never
    {
        http_response_code(404);
        echo $message;
        exit;
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
