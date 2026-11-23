# PressDoWiki modernization plan

This document records the current technical baseline and the architecture we are
moving toward. It is intentionally blunt: preserving accidental architecture
would make the rewrite slower and less safe.

## Rendering migration status

Bundled pages, partials, the frame, and the default skin now render with Blade.
The application retains support for installed PHP skin layouts. Latte layouts
need to be converted to Blade.

## The roast (2026-08-10 baseline)

The application currently behaves like an undocumented framework whose public
API is every public property, static method, global variable, relative path, and
array key in the repository.

### Boundaries do not exist yet

- `public/index.php` creates request, routing, controller, session, security
  headers, and rendering concerns in one script.
- `App/Core/Controller.php` handles authentication state, CAPTCHA setup, wiki
  parsing, view data, mail, CSRF, diffs, titles, time formatting, random strings,
  Korean text helpers, SVGs, and cookies. A base controller with this many jobs
  is a service locator disguised as inheritance.
- Models obtain a process-wide PDO singleton and expose static methods. Business
  rules cannot be tested without also invoking global configuration and database
  state.
- The router emits bodies and headers and calls `exit`. The response helper does
  the same. Routing, dispatch, and response delivery therefore cannot be tested
  independently.
- Controllers read `$_GET`, `$_POST`, `$_FILES`, `$_SERVER`, and `$_SESSION`
  directly even though a `Request` object exists. There are more than 100 direct
  superglobal reads outside that object.
- View selection depends on route-name string switches and mutable nested arrays.
  Latte templates, PHP skin templates, extracted variables, controller helpers,
  and relative working-directory paths form one implicit rendering API.

This is why small changes spread: the project has directories, but the runtime
has no enforceable seams between them.

### The quality gates overstate their coverage

- `composer check` passes on the current machine, but the test suite consists of
  standalone PHP scripts rather than a test runner with fixtures, isolation,
  coverage, and useful test selection.
- PHPStan is configured at level `max`, but it scans only five hand-picked files.
  Scanning `App/` and `public/` on 2026-08-10 reports 3,384 file errors. Of those,
  1,012 are in copied Parsedown and MediaWiki parser sources; the remaining
  errors include application code and cannot be dismissed as vendor noise.
- Syntax linting is not formatting. There is currently no committed formatting
  tool or style check.
- CI covers PHP 8.3 and 8.4 even though the modernization target is PHP 8.2.

### Security needs system-wide rules, not scattered fixes

- The lock file has eight known security advisories (Guzzle and PSR-7) at this
  baseline.
- Several state-changing actions use raw superglobals and do not share one CSRF
  middleware or form-request policy. A few edit flows check a token, which is not
  equivalent to complete CSRF coverage.
- Logout accepts an arbitrary redirect target, creating an open-redirect path.
- Upload validation trusts the browser-provided MIME type, accepts SVG, and mixes
  validation, image decoding, storage, and document creation without a
  transaction.
- The CSP restricts scripts to external sources on its allowlist and workers
  to the current origin. The Monaco editor and its worker are built from the
  pinned npm lockfile and served with the release. The bundled NamuMark
  highlighting and toolbar are maintained in this repository. Preview and raw
  editing continue to work in a source checkout without the Monaco bundle.
  The lockfile overrides Monaco's DOMPurify pin with version 3.5.0 to pick up
  security fixes. Review the override when upgrading Monaco.
  CAPTCHA and KaTeX still use their configured or pinned external endpoints.
  The KaTeX allowlist covers the pinned scripts, stylesheet, and font directory.
- Error handling suppresses notices and warnings in the front controller. That
  hides defects and risks returning partial responses instead of recording a
  controlled failure.
- Authentication state is copied between a controller property and `$_SESSION`.
  `SessionLifecycle` now sets cookie policy, rotates the ID after login, and
  clears the session on logout. Before checking CSRF, the front controller
  compares the authenticated session's credential with the current account
  credential. The session stores a SHA-256 digest of the password verifier.
  Password reset revokes sessions and pending login challenges on their next
  request, and deletes persistent cookies in the reset transaction. This
  rollout uses the existing database schema.
- The versioned login-limit table uses atomic per-account and per-IP reservations
  before password checks, second-factor checks, and login email dispatch.
  The counters are shared across application workers using the same database.
  Existing challenge expiry and retry limits continue to apply. Bucket keys are
  hashed and expired counters are cleared opportunistically during login.

### Performance work has no reliable measurement point

- Static database access makes query counting, tracing, caching, and transaction
  ownership difficult.
- A controller can perform parsing, database work, remote CAPTCHA/GeoIP calls,
  and rendering in one request without timeouts or observable boundaries.
- Copied parser implementations and a custom diff library increase maintenance
  cost and prevent normal dependency updates.
- Supporting many nominal database drivers through handwritten SQL branches
  creates complexity without a compatibility test matrix for those drivers.
  CI now imports the shipped schemas and runs an integration flow against
  MariaDB 10.11 and PostgreSQL 16. The same flow runs on SQLite with
  `composer test`. It checks authentication and session credentials, permission
  persistence and audit, and edit-request transitions with revision creation.
  Database integration tests also drive the front controller with real login,
  permission grant, and edit-request approval requests. It checks that denied
  approvals leave the request open and authorized approvals append a revision.
  Concurrent approvals share the same document lock as normal edits. A
  stale proposal cannot publish over a newer revision, and status updates
  check their expected state inside the database write. Conflicting requests
  return HTTP 409. The suite races approvals and closure on all three engines.
  MariaDB requires a character delta when inserting an edit request. The
  creation path now records the initial delta against the source revision.
  The stock MariaDB CI image uses InnoDB for the search table, outside the
  coverage of this flow.
  New schema changes use `php tools/migrate.php`. CI creates an account on
  the installation schema before upgrading, then checks that the account
  survives and re-running the migrator leaves one record per version.
  Migration 2 adds a registry of member sessions with their creation and
  activity timestamps and hashed browser credentials. Its runtime queries
  share the PDO SQL used for all three database engines.
  The front controller requires a registered session before validating CSRF.
  Remember-me restores the existing registry entry, and credential lookups
  are repeated inside the login transaction. Local logout revokes that entry.
  Password reset removes all of the account's entries in the reset transaction.
  The profile displays the current browser and allows revoking individual
  entries or all other entries. Both actions are account-scoped POSTs with
  CSRF validation. Revocation removes the session entry and its persistent
  cookie digests together. The account lock orders bulk revocation against
  new and restored logins.

Performance optimization before these seams exist would mostly be guesswork.

## Decision: a feature-oriented modular monolith

PressDoWiki will remain one deployable PHP application. It will not grow a new
home-made framework, and it will not start with microservices. New code will use
the following dependency direction:

```text
HTTP / CLI adapters -> application use cases -> domain rules
                              |
                              v
                infrastructure implementations
```

The target layout is:

```text
App/
  Bootstrap/                 # container wiring and application startup
  Http/                      # controllers, middleware, request validation
  Wiki/                      # document and revision use cases/domain rules
  Discussion/                # threads and replies
  Member/                    # identity, authentication, profile
  AccessControl/             # grants, ACL rules, blocks
  Admin/                     # administrative use cases
  Shared/                    # deliberately small cross-feature contracts
  Infrastructure/
    Persistence/             # PDO repositories and transactions
    Rendering/               # Blade adapter
    Mail/                    # Symfony Mailer adapter
    Storage/                 # local and S3 implementations
config/
resources/views/             # Blade templates
routes/                      # declarative HTTP routes
tests/{Unit,Integration,Feature}/
```

These names describe ownership, not ceremony. A simple read use case may be one
class. We will introduce interfaces only at real boundaries such as storage,
mail, rendering, clock/randomness, and persistence that needs test doubles.

## Framework components we will use

PHP 8.2 remains a hard runtime target. Laravel 13 requires PHP 8.3, while Laravel
12 supports PHP 8.2 but is already near the end of bug-fix support. A full
Laravel 12 rewrite would therefore begin on an aging framework line.

Instead, solved infrastructure problems will be delegated to maintained
components behind small application-owned interfaces:

- Symfony 7.4 LTS components for HTTP request/response handling, routing,
  dependency injection, configuration, and error handling. This line supports
  PHP 8.2 and gives the application one coherent request lifecycle.
- Illuminate View 12 for Blade compilation and rendering. Application code will
  depend on a `TemplateRenderer` contract, not directly on Illuminate, so the
  adapter can move to Illuminate 13 when PHP 8.3 becomes the minimum.
- PHPUnit for tests and PHPStan level max for static analysis.
- PHP-CS-Fixer for deterministic formatting. Formatting-only changes stay in
  their own commits when they touch legacy code.
- PDO initially, but injected through a connection/transaction boundary. We
  will not add a generic repository for every table or build another query
  builder. Database portability claims must be backed by CI tests.

This is intentionally not “half of Laravel rebuilt locally.” Symfony owns the
HTTP kernel concerns, Illuminate owns Blade, and PressDoWiki owns wiki behavior.

## Migration rules

1. Every migrated route enters through one front controller and returns a
   response object. Domain and application code must not call `header`, `echo`,
   or `exit`.
2. Only the HTTP adapter reads request input. Use cases receive typed command or
   query objects, not superglobals or unshaped arrays.
3. Controllers authorize, validate, call one use case, and map the result to a
   response. They do not contain SQL or formatting helpers.
4. State-changing browser requests use a shared CSRF policy. Redirect targets
   are local paths or explicitly allow-listed origins.
5. Uploaded files are validated from server-observed content, decoded with
   resource limits, stored outside executable paths, and recorded atomically.
   SVG needs a dedicated sanitizer or remains disabled.
6. New SQL uses prepared statements and lives in persistence classes. A use
   case owns its transaction boundary.
7. Application templates are Blade files.
8. Copied third-party sources are replaced with Composer packages or isolated as
   legacy code with an explicit replacement issue and tests.
9. PHPStan stays at level max. Its scanned paths expand as vertical slices are
   migrated; errors are fixed rather than hidden by a baseline or ignore rule.
10. Each change follows: implementation, formatting/linting, PHPStan max,
    tests, then a focused commit with a detailed message.

## Migration sequence

1. Establish honest tooling: PHP 8.2 CI, formatting, dependency audit, PHPUnit,
   and a PHPStan max ratchet that names the code it genuinely covers.
2. Add the application bootstrap and Symfony request/response boundary while
   keeping a legacy controller adapter for unchanged routes.
3. Add the Blade renderer and migrate the frame plus one read-only route as a
   proof of the new boundary.
4. Extract identity/session/CSRF handling before migrating write routes.
5. Migrate feature by feature: wiki reads, edits/history, discussions, members,
   ACL/admin, and uploads.
6. Remove the legacy controller/model base classes after no route reaches them.
7. Profile representative read, edit, history, search, and discussion requests;
   then add query changes or caches with measured before/after results.

## Definition of done for a migrated slice

A slice is migrated only when it has no direct superglobal access, no static
database lookup, no response side effects below the HTTP adapter, Blade views,
typed inputs/outputs, authorization and CSRF coverage where applicable, unit or
integration tests, PHPStan max coverage, and passing formatting/lint checks.

## References

- [Laravel support policy](https://laravel.com/docs/12.x/releases)
- [Laravel 13 PHP requirement](https://github.com/laravel/framework/blob/13.x/composer.json)
- [Rhymix repository](https://github.com/rhymix/rhymix)

