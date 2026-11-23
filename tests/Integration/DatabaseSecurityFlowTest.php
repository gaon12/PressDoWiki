<?php

declare(strict_types=1);

use PressDo\App\Core\Model;
use PressDo\App\Helpers\Config;
use PressDo\App\Helpers\Database;
use PressDo\App\Helpers\DefaultConfig;
use PressDo\App\Helpers\SqlDialect;
use PressDo\App\Http\Security\EmailVerificationPurpose as Purpose;
use PressDo\App\Http\Security\PermissionGrantPolicy;
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
(new ReflectionProperty(DefaultConfig::class, 'DefConfig'))->setValue(null, $config);

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

echo "Database security flow test passed ($driver)." . PHP_EOL;
