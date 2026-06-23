<?php
namespace PressDo\App\Core;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $cookies = [],
        private readonly array $server = []
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            self::stringKeyed($_GET),
            self::stringKeyed($_POST),
            self::stringKeyed($_COOKIE),
            self::stringKeyed($_SERVER)
        );
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

    /**
     * @param array<string, mixed> $source
     */
    private function stringFrom(array $source, string $key, string $default): string
    {
        $value = $source[$key] ?? $default;
        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param array<array-key, mixed> $source
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $source): array
    {
        $result = [];
        foreach ($source as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
