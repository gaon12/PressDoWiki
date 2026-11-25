# PressDoWiki modernization plan

This document describes the current implementation, the August 2026 baseline,
and the planned architecture.

## Current implementation

- **Rendering.** Bundled pages, partials, the frame, and the default skin use
  Illuminate View for Blade. Existing PHP skin layouts remain supported. The
  bundled NamuMark renderer and its editor highlighting are maintained in this
  repository. MediaWiki and BBCode output passes through the shared HTML
  sanitizer. The Monaco bundle and worker are served from the site. CSP
  restricts workers to the same origin and keeps CAPTCHA and the pinned KaTeX
  assets on their documented endpoints. The editor lockfile overrides Monaco's
  DOMPurify version for security fixes. Review the override when upgrading Monaco.
- **Database access.** Models and persistence services use PDO and prepared
  SQL. `SqlDialect` handles differences such as identifier quoting and
  PostgreSQL's binary UUID binding. SQLite uses its transaction write lock
  where server databases use row locks. Migration 1 adds login quotas, and
  migration 2 adds the member session registry. Administrators apply them with
  [the manual deployment procedure](update-protocol.md#manual-deployment).
- **Authentication.** The front controller validates registered member
  sessions before checking browser POST CSRF tokens. Remember-me and trusted
  device tokens are stored as digests and rechecked inside the login
  transaction. The profile supports individual and all-other-device logout.
  Revocation removes the associated cookie digests, and password reset
  removes every registered session for the account. Account and IP quotas
  apply to password checks, second-factor checks, and login email delivery
  across application workers that share the same database.
- **Document writes.** Edit-request approvals use the same document lock as
  ordinary edits and check the proposal's base revision under that lock.
  Conflicting updates return HTTP 409. Concurrent approvals can append only
  one revision for a given base.
- **Verification.** [CI](../.github/workflows/ci.yml) runs `composer check` on
  PHP 8.2, 8.3, and 8.4. PHPStan runs at level `max` on the paths listed in
  [its configuration](../phpstan.neon.dist). The SQLite, MariaDB 10.11, and
  PostgreSQL 16 flows cover data retention during upgrades, session and cookie
  revocation, permission grants, and edit-request races. The MariaDB CI image
  exercises the InnoDB fallback search table. Mroonga search needs its own
  deployment verification.

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
- The CSP permits inline scripts, `unsafe-eval`, broad third-party hosts, and
  wildcard child/media sources. It offers much less protection than its presence
  suggests.
- Error handling suppresses notices and warnings in the front controller. That
  hides defects and risks returning partial responses instead of recording a
  controlled failure.
- Authentication state is copied between a controller property and `$_SESSION`.
  There is no single place that guarantees session-ID rotation after login,
  cookie policy, or logout invalidation.

### Performance work has no reliable measurement point

- Static database access makes query counting, tracing, caching, and transaction
  ownership difficult.
- A controller can perform parsing, database work, remote CAPTCHA/GeoIP calls,
  and rendering in one request without timeouts or observable boundaries.
- Copied parser implementations and a custom diff library increase maintenance
  cost and prevent normal dependency updates.
- Supporting many nominal database drivers through handwritten SQL branches
  creates complexity without a compatibility test matrix for those drivers.

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

## Framework component direction

PHP 8.2 remains the minimum runtime. The application delegates rendering,
mail, and HTML sanitization to maintained components. The next architecture
steps build on those boundaries.

- Keep Blade behind the existing `TemplateRenderer` contract.
- Consolidate HTTP request, response, routing, configuration, and error
  handling behind a Symfony-based adapter. The current application uses
  PressDoWiki's own HTTP helpers.
- Move standalone PHP tests to PHPUnit as slices gain reusable fixtures. Keep
  PHPStan at level `max` and expand the configured paths as code is migrated.
- Inject PDO connections and define transaction boundaries within each
  migrated use case. Validate SQL portability with engine-specific CI jobs.

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

CI, formatting, dependency audits, Blade rendering, and shared session and
CSRF checks are in place. The remaining steps are:

1. Add the application bootstrap and Symfony request/response boundary while
   keeping a legacy controller adapter for existing routes.
2. Introduce PHPUnit fixtures and expand PHPStan coverage as use cases move
   across the new boundary.
3. Migrate feature by feature across wiki reads, edits/history, discussions, members,
   ACL/admin, and uploads.
4. Remove the legacy controller/model base classes after routes have migrated.
5. Profile representative read, edit, history, search, and discussion requests,
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

