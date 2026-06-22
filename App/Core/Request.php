<?php
namespace PressDo\App\Core;

final class Request
{
    public function __construct(
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $cookies = [],
        private readonly array $server = []
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_COOKIE, $_SERVER);
    }

    public function queryString(string $key, string $default = ''): string
    {
        return $this->stringFrom($this->query, $key, $default);
    }

    public function postString(string $key, string $default = ''): string
    {
        return $this->stringFrom($this->post, $key, $default);
    }

    public function cookieString(string $key, string $default = ''): string
    {
        return $this->stringFrom($this->cookies, $key, $default);
    }

    public function serverString(string $key, string $default = ''): string
    {
        return $this->stringFrom($this->server, $key, $default);
    }

    public function hasPost(string $key): bool
    {
        return array_key_exists($key, $this->post);
    }

    public function hasCookie(string $key): bool
    {
        return array_key_exists($key, $this->cookies);
    }

    public function postJson(string $key): mixed
    {
        $value = $this->postString($key);
        if ($value === '') {
            return null;
        }

        return json_decode($value, true);
    }

    private function stringFrom(array $source, string $key, string $default): string
    {
        $value = $source[$key] ?? $default;
        if (is_scalar($value) || $value === null) {
            return (string) ($value ?? $default);
        }

        return $default;
    }
}
