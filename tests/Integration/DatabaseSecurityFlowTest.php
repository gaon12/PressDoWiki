<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Core\Request;
use PressDo\App\Helpers\Config;
use PressDo\App\Helpers\Database;
use PressDo\App\Helpers\DefaultConfig;
use PressDo\App\Helpers\SqlDialect;
use PressDo\App\Http\Security\CsrfTokenManager;
use PressDo\App\Http\Security\EmailVerificationPurpose as Purpose;
use PressDo\App\Http\Security\PermissionGrantPolicy;
use PressDo\App\Http\Security\SessionLifecycle;
use PressDo\App\Infrastructure\Database\SchemaMigrator;
use PressDo\App\Models\EditRequest;
use PressDo\App\Models\Member;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

function expectDatabaseFlow(bool $passed, string $message): void
{
    if (!$passed) {
        throw new RuntimeException($message);
    }
}

$driver = getenv('PRESSDO_TEST_DB_DRIVER') ?: 'sqlite';
expectDatabaseFlow(in_array($driver, ['mysql', 'pgsql', 'sqlite'], true), 'Unsupported integration database driver.');
if ($driver === 'sqlite' && !in_array($driver, PDO::getAvailableDrivers(), true)) {
    echo 'Database security flow test skipped (pdo_sqlite unavailable).' . PHP_EOL;
    exit(0);
}

$config = json_decode(file_get_contents($root . '/templates/config.json'), true, flags: JSON_THROW_ON_ERROR);
$config['database.type'] = $driver;
$config['database.host'] = getenv('PRESSDO_TEST_DB_HOST') ?: '127.0.0.1';
$config['database.port'] = getenv('PRESSDO_TEST_DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
$config['database.name'] = $driver === 'sqlite' ? ':memory:' : (getenv('PRESSDO_TEST_DB_NAME') ?: 'pressdo_test');
$config['database.user'] = getenv('PRESSDO_TEST_DB_USER') ?: 'pressdo_test';
$config['database.password'] = getenv('PRESSDO_TEST_DB_PASSWORD') ?: '';
$config['wiki.mark'] = 'NamuMark';
(new ReflectionProperty(DefaultConfig::class, 'DefConfig'))->setValue(null, $config);

if (($argv[1] ?? null) === '--request') {
    $fixture = json_decode(base64_decode($argv[2], true), true, flags: JSON_THROW_ON_ERROR);
    $_SERVER['REMOTE_ADDR'] = '192.0.2.20';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = $fixture['uri'];
    parse_str(parse_url($fixture['uri'], PHP_URL_QUERY) ?? '', $_GET);
    $_POST = $fixture['post'];
    $_COOKIE = $fixture['cookies'];

    SessionLifecycle::start(Request::fromGlobals(), '');
    $_SESSION = $fixture['session'];
    $_POST['_csrf'] = (new CsrfTokenManager())->issueBrowserToken($_SESSION);
    register_shutdown_function(static function (): void {
        echo PHP_EOL . 'STATUS=' . (http_response_code() ?: 200);
    });
    require $root . '/public/index.php';
    exit;
}

function expectDatabaseResponse(string $uri, int $status, array $post = [], array $session = [], array $cookies = []): void
{
    $fixture = base64_encode(json_encode(compact('uri', 'post', 'session', 'cookies'), JSON_THROW_ON_ERROR));
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--request', $fixture],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    expectDatabaseFlow(is_resource($process), 'Unable to start the request fixture.');
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    expectDatabaseFlow(
        $exit === 0 && $errors === '' && str_ends_with($output, 'STATUS=' . $status),
        "$uri should return $status. Response: " . substr($output, -200) . ' Errors: ' . $errors,
    );
}

$db = Database::getInstance();
if ($driver === 'sqlite') {
    $db->exec(file_get_contents($root . '/templates/database_scheme.sqlite.sql'));
}
expectDatabaseFlow(Config::get('wiki.language') === 'ko-kr', 'The app must be able to load its configuration.');

function createFlowAccount(string $username, string $ip): array
{
    $email = $username . '@example.test';
    $signup = Member::issueEmailCode($email, $ip, Purpose::Signup);
    expectDatabaseFlow(is_string($signup), 'Signup should issue an email token.');
    expectDatabaseFlow(Member::completeSignup($signup, $ip, $username, 'test-start-password'), 'Signup should create an account and its user document.');
    $member = Member::exist($username);
    expectDatabaseFlow(is_array($member), 'The account and its UUID should be loadable after signup.');

    return $member;
}

$suffix = bin2hex(random_bytes(5));
$ip = '192.0.2.20';
$actor = createFlowAccount('actor_' . $suffix, $ip);
$migrations = new SchemaMigrator($db);
$applied = $migrations->migrate();
expectDatabaseFlow($applied === [1] || $applied === [], 'The login-limit schema should be applied to an existing database.');
expectDatabaseFlow($migrations->migrate() === [], 'Re-running migrations must leave the database up to date.');
expectDatabaseFlow((int) $db->query('SELECT COUNT(*) FROM schema_migrations WHERE version = 1')->fetchColumn() === 1, 'The migration version must be recorded exactly once.');
expectDatabaseFlow(Member::exist($actor['username']) === $actor, 'The migration must preserve existing members.');
$target = createFlowAccount('target_' . $suffix, $ip);
$credentials = Member::checkMember($actor['email'], 'test-start-password');
expectDatabaseFlow(is_array($credentials) && $credentials['uuid'] === $actor['uuid'], 'Password authentication must find the correct account.');
expectDatabaseFlow(Member::getUserInfo($actor['uuid'])['email'] === $actor['email'], 'Login must be able to look up the second-factor email address.');
expectDatabaseFlow(Member::getUserWebauthn($actor['uuid']) === [], 'Login must be able to look up available passkeys.');
$login = Member::login($actor['uuid'], $credentials['credential'], $ip, 'ci', ['szczecin' => 600, 'podgorica' => 600]);
expectDatabaseFlow(is_array($login), 'Login should log the attempt and issue persistent cookies.');
expectDatabaseFlow(Member::rememberCookieIdentity($login['cookies']['szczecin']) === $credentials, 'Remember-me should restore the current credential.');
expectDatabaseFlow(Member::checkCookie('podgorica', $login['cookies']['podgorica'], $actor['uuid']) === $actor['uuid'], 'Trusted-device lookup should match the account UUID.');
$reset = Member::issueEmailCode($actor['email'], $ip, Purpose::PasswordReset);
expectDatabaseFlow(is_string($reset) && Member::completePasswordReset($reset, 'test-reset-password'), 'Password reset must update the account.');
expectDatabaseFlow(Member::sessionCredential($actor['uuid']) !== $credentials['credential'], 'Password reset must revoke the old session credential.');
expectDatabaseFlow(Member::rememberCookieIdentity($login['cookies']['szczecin']) === null, 'Password reset must revoke remembered logins.');
expectDatabaseFlow(Member::checkCookie('podgorica', $login['cookies']['podgorica']) === null, 'Password reset must revoke trusted devices.');
expectDatabaseFlow(Member::login($actor['uuid'], $credentials['credential'], $ip, 'stale', ['szczecin' => 600]) === null, 'An in-flight login with the old credential must fail.');

$policy = PermissionGrantPolicy::manageable(['grant', 'mark_troll_revision'], [], ['admin', 'mark_troll_revision'], false);
expectDatabaseFlow($policy === ['mark_troll_revision'], 'Delegated grants must exclude protected permissions.');
expectDatabaseFlow(Member::grantPermissions($actor['uuid'], $target['uuid'], '', $policy, '+mark_troll_revision'), 'The grant should persist.');
expectDatabaseFlow(!Member::grantPermissions($actor['uuid'], $target['uuid'], '', [], '-mark_troll_revision'), 'A stale permission update must fail.');
expectDatabaseFlow(Member::specialPerms($target['uuid']) === $policy, 'The target should retain the granted permission.');
$audit = $db->prepare(SqlDialect::normalizeIdentifiers("SELECT granted FROM `BlockHistory` WHERE target_member = ? AND action = 'grant'"));
SqlDialect::bindBinary($db, $audit, 1, Model::uuid2bin($target['uuid']));
$audit->execute();
expectDatabaseFlow($audit->fetchAll(PDO::FETCH_COLUMN) === ['+mark_troll_revision'], 'Exactly the successful grant should be audited.');

$document = $db->prepare('SELECT ' . SqlDialect::binaryHex('uuid') . ' FROM document WHERE title = ?');
$document->execute([$target['username']]);
$documentId = Model::bin2uuid(hex2bin($document->fetchColumn()));
$document->closeCursor();
$slug = 'edit_' . $suffix;
EditRequest::create($slug, $documentId, '편집 요청 본문', 'request', $target['uuid'], null, 1, 8);
expectDatabaseFlow(EditRequest::getBulk($documentId) === [['urlstr' => $slug]], 'The edit request should be listed as open.');
expectDatabaseFlow((int) EditRequest::get($slug)['count'] === 8, 'Creation should persist its initial character delta.');
EditRequest::modify($slug, '수정한 본문', 'updated', 6);
EditRequest::close($slug, 'closed', $actor['uuid'], null);
expectDatabaseFlow(EditRequest::get($slug)['status'] === 'close', 'The edit request should be closed.');
EditRequest::reopen($slug);
$request = EditRequest::get($slug);
expectDatabaseFlow($request !== null && $request['contributor_m'] === Model::uuid2bin($target['uuid']), 'Reading a reopened request must preserve the contributor ID.');
EditRequest::accept($slug, $request, $actor['uuid'], null, Model::getVersion($documentId) + 1);
expectDatabaseFlow(EditRequest::get($slug)['status'] === 'accepted', 'The edit request should be accepted.');
$revision = $db->prepare('SELECT content, rev FROM history WHERE edit_request_uri = ?');
$revision->execute([$slug]);
$saved = $revision->fetchAll();
expectDatabaseFlow(count($saved) === 1 && $saved[0]['content'] === '수정한 본문' && (int) $saved[0]['rev'] === 2, 'Acceptance must persist one linked revision.');

if ($driver !== 'sqlite') {
    $trust = Member::saveCookies($actor['uuid'], 'podgorica', 600);
    expectDatabaseResponse('/member/login', 302, [
        'username' => $actor['username'],
        'password' => 'test-reset-password',
    ], cookies: ['podgorica' => $trust]);

    $identity = [
        'uuid' => $actor['uuid'],
        'credential' => Member::sessionCredential($actor['uuid']),
        'member' => ['username' => $actor['username']],
    ];
    $perm = $db->prepare("UPDATE member SET perm = 'grant,mark_troll_revision,delete_thread' WHERE uuid = ?");
    SqlDialect::bindBinary($db, $perm, 1, Model::uuid2bin($actor['uuid']));
    $perm->execute();
    $grantUrl = '/admin/grant?username=' . rawurlencode($target['username']);
    expectDatabaseResponse($grantUrl, 403, ['grant_permissions' => '1', 'permissions' => ['admin']], $identity);
    expectDatabaseResponse($grantUrl, 303, [
        'grant_permissions' => '1',
        'permissions' => ['mark_troll_revision', 'delete_thread'],
    ], $identity);
    expectDatabaseFlow(in_array('delete_thread', Member::specialPerms($target['uuid']), true), 'Authorized grant POST must persist the selected permission.');

    $acl = $db->prepare(SqlDialect::normalizeIdentifiers("INSERT INTO acl_document (uuid, access, `condition`, action, `until`) VALUES (?, ?, ?, 'allow', 0)"));
    SqlDialect::bindBinary($db, $acl, 1, Model::uuid2bin($documentId));
    foreach (['read' => 'perm:any', 'edit' => 'perm:admin'] as $access => $condition) {
        $acl->bindValue(2, $access);
        $acl->bindValue(3, $condition);
        $acl->execute();
    }
    $routeSlug = 'route_' . $suffix;
    EditRequest::create($routeSlug, $documentId, '변경된 제안', 'route', $target['uuid'], null, 2, 3);
    $acceptUrl = '/edit_request/' . $routeSlug . '/accept';
    expectDatabaseResponse($acceptUrl, 403, session: $identity);
    expectDatabaseFlow(EditRequest::get($routeSlug)['status'] === 'open', 'Denied approval must leave the request open.');

    $rule = $db->prepare(SqlDialect::normalizeIdentifiers("UPDATE acl_document SET `condition` = 'perm:member' WHERE uuid = ? AND access = 'edit'"));
    SqlDialect::bindBinary($db, $rule, 1, Model::uuid2bin($documentId));
    $rule->execute();
    expectDatabaseResponse($acceptUrl, 303, session: $identity);
    expectDatabaseFlow(EditRequest::get($routeSlug)['status'] === 'accepted' && Model::getVersion($documentId) === 3, 'Authorized approval must persist its revision.');
}

echo "Database security flow test passed ($driver)." . PHP_EOL;
