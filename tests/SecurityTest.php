<?php

namespace PressDo\App\Helpers\Captcha {
    class TestCaptcha
    {
        public const API_ENDPOINT = '';
        public const CLASS_NAME = 'test-captcha';
        public const TOKEN_NAME = 'captcha-response';

        public static function verify(string $token): bool
        {
            return $token === 'valid-token';
        }
    }
}

namespace {
    require __DIR__.'/../App/Helpers/Config.php';
    require __DIR__.'/../App/Helpers/DefaultConfig.php';
    require __DIR__.'/../App/Helpers/Languages.php';
    require __DIR__.'/../App/Core/Controller.php';

    use PressDo\App\Core\Controller;
    use PressDo\App\Helpers\Config;
    use PressDo\App\Helpers\DefaultConfig;
    use PressDo\App\Helpers\Languages;

    final class TestController extends Controller
    {
        public function __construct()
        {
        }

        public static function captcha(?string $token): bool
        {
            return self::validateCaptcha($token);
        }

        public static function randomString(int $len = 16, bool $uppercase = false, string $additional = ''): string
        {
            return self::rand($len, $uppercase, $additional);
        }

        public static function processEdit(TestController $page, string $tokenName): bool
        {
            return self::editFormProcess($page, $tokenName);
        }
    }

    function setPrivateStatic(string $class, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($class, $property);
        $ref->setAccessible(true);
        $ref->setValue(null, $value);
    }

    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            fwrite(STDERR, $message.PHP_EOL);
            fwrite(STDERR, 'Expected: '.var_export($expected, true).PHP_EOL);
            fwrite(STDERR, 'Actual: '.var_export($actual, true).PHP_EOL);
            exit(1);
        }
    }

    setPrivateStatic(DefaultConfig::class, 'DefConfig', [
        'captcha.type' => 'TestCaptcha',
        'wiki.language' => 'ko-kr',
    ]);
    setPrivateStatic(Languages::class, 'Languages', [
        'msg' => [
            'err_csrf_token' => 'CSRF token mismatch.',
            'err_same_contents' => 'Same contents.',
        ],
    ]);

    setPrivateStatic(Config::class, 'Configs', ['wiki.use_captcha' => false]);
    assertSameValue(true, TestController::captcha(null), 'Disabled captcha should pass without a token.');

    setPrivateStatic(Config::class, 'Configs', ['wiki.use_captcha' => true]);
    assertSameValue(false, TestController::captcha(null), 'Enabled captcha should fail without a token.');
    assertSameValue(false, TestController::captcha('bad-token'), 'Enabled captcha should fail with an invalid token.');
    assertSameValue(true, TestController::captcha('valid-token'), 'Enabled captcha should verify a valid token.');

    $random = TestController::randomString(128, true, '_-');
    assertSameValue(128, strlen($random), 'Random string should honor requested length.');
    assertSameValue(1, preg_match('/^[0-9A-Za-z_-]+$/', $random), 'Random string should use only configured characters.');

    $page = new TestController();
    $page->session = ['edittoken' => 'known-token', 'raw' => 'old'];
    $_POST = ['token' => 'known-token', 'content' => 'new'];
    assertSameValue(true, TestController::processEdit($page, 'edittoken'), 'Matching CSRF token should allow edit processing.');
    assertSameValue('new', $page->content, 'Edit processing should store decoded content.');

    $page = new TestController();
    $page->session = ['edittoken' => 'known-token', 'raw' => 'old'];
    $_POST = ['token' => 'wrong-token', 'content' => 'new'];
    assertSameValue(false, TestController::processEdit($page, 'edittoken'), 'Wrong CSRF token should reject edit processing.');
    assertSameValue('err_csrf_token', $page->error['code'], 'Wrong CSRF token should set the CSRF error.');

    $_POST = [];
    echo "Security tests passed.".PHP_EOL;
}
