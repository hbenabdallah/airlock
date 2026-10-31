# AGENTS.md

This is a Symfony project. Check `composer.json` for the exact Symfony/PHP version
in use, and read `symfony.lock` to see which recipes ran. Don't assume Doctrine,
Twig, API Platform, Messenger, or Lock are installed unless one of those says so.

## Ask before generating

If the task doesn't specify, ask rather than guess:

- Persistence: Doctrine ORM, Doctrine ODM, or none?
- Interface: server-rendered (Twig), API (Serializer, maybe API Platform), or both?
- Auth: SecurityBundle, and which authenticator?

If you can't ask (no interactive channel), state the assumption you're making and
pick the smallest option (e.g. no persistence layer) rather than scaffolding a
full stack nobody asked for.

## Adding features: Flex, not hand-wiring

Install new capabilities with `composer require <package>` (e.g. `symfony/lock`,
`symfony/messenger`, `orm-pack`) and let the Flex recipe register the bundle and
generate its config. Don't hand-edit `config/bundles.php` or hand-write a bundle's
base config; that's what the recipe is for. Don't skip a good-fit component just
because it isn't installed yet; installing it is one command.

## Conventions

Follow https://symfony.com/doc/current/best_practices.html to write idiomatic
Symfony:

- Use PHP attributes for framework metadata, and not only on controllers:
  `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` on actions, `#[Assert\...]`
  on properties, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and
  `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or
  XML routing.
- Rely on autowiring and autoconfiguration. Type-hint constructor arguments and
  let the container resolve them. Where a type-hint can't express it, stay in the
  class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one
  of several implementations of an interface). A YAML service definition is the
  last resort, not the first.
- Controllers extend `AbstractController`, stay thin, and delegate to services.
- Use the framework for what it already does: Form for server-rendered forms,
  Validator for validation, Serializer for JSON, Messenger for async work,
  Security (voters, authenticators) for access control, Twig `path()`/`url()`
  instead of hardcoded URLs.
- Before hand-writing infrastructure (locks, queues, caches, HTTP clients,
  mailers, schedulers) or reaching for a third-party library, check whether a
  Symfony component covers it. It usually does.

Three specifics worth spelling out, because they are easy to get wrong:

- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]` on action
  arguments, which wires up Serializer and Validator for you, instead of calling
  `json_decode()` or `SerializerInterface` by hand. If neither package is
  installed yet, `composer require` them rather than falling back to manual
  parsing.
- Use constructor property promotion, and `readonly` for DTOs and value objects.
- Use `symfony/lock` (`LockFactory`) for mutual exclusion. A hand-built flag or
  lock file looks fine in review and is usually wrong under concurrency.

## Everyday workflow

- Run the app with `symfony serve -d`, and commands with `symfony console ...`
  (or `bin/console` when the Symfony CLI isn't available).
- When something fails, read `var/log/dev.log` and the web profiler
  (`/_profiler`) before changing code.
- If `maker-bundle` is installed, prefer `bin/console make:*` with every argument
  passed up front and `--no-interaction` where supported: makers prompt on a
  terminal by default, which hangs a non-interactive shell. If a maker still
  needs interactive input, hand-write the code instead.
- If Doctrine ORM is installed, schema changes go through migrations
  (`bin/console make:migration`, then `doctrine:migrations:migrate`), never
  `doctrine:schema:update` or hand-written SQL.
- `.env` is committed and holds defaults only. Real secrets belong in `.env.local`
  (git-ignored) or the secrets vault (`bin/console secrets:set`), read via
  `%env(...)%`.

## Testing

Install `symfony/test-pack` if it isn't already. Functional/HTTP tests extend
`WebTestCase`; service-level tests extend `KernelTestCase`. Run
`php bin/phpunit` (falls back to `vendor/bin/phpunit`). A feature isn't done
until it has a test that exercises it the way a caller would, an HTTP request for
a controller or a service call for a service, not just "it didn't throw."

## Code style

Symfony's coding standard, the `@Symfony` php-cs-fixer ruleset (a PSR-12-derived
superset). Run `vendor/bin/php-cs-fixer fix` if `friendsofphp/php-cs-fixer` is
installed; it isn't part of the skeleton by default.

## Discover, don't guess

Framework APIs change between versions and your training data may be stale. Look
things up in the project instead of relying on memory:

- `bin/console about`: versions, environment, paths.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <name>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>`: what exists and how
  it is configured.
- `bin/console lint:container`, plus `lint:twig templates/` and
  `lint:yaml config/` where those packages are installed: validate before running.
- Read the installed source and docblocks under `vendor/`.
- Docs: https://symfony.com/doc/current/ (switch to the version matching
  `composer.json` if it differs).

## Airlock: PHP rules

Technical rules for writing PHP: types, classes, control flow, errors, tests, tooling. They say nothing about
architecture (layers, bounded contexts, folders): read it from the code and `docs/design.md`.

### Precedence

1. The Symfony part of this file, above, and the project's tooling configuration (`.php-cs-fixer.dist.php`,
   `phpstan.dist.neon`, `rector.php`) win over this section.
2. Then the code already there: before writing, read two or three neighbouring files of the same kind and do as they
   do. A second way of doing the same thing is worse than a suboptimal but consistent one.
3. Then these rules. When one of them conflicts with the existing code, say so; don't settle it alone, and don't
   rewrite the existing code to match unless asked.

### Standards

- PSR-1 (basic coding standard) and PSR-4 (autoloading: one class per file, namespace = directory).
- PER Coding Style 2.0, the successor of PSR-12, unless the project's formatter says otherwise. Formatting is the
  formatter's job: run it, never format by hand against it.
- Use the PSR interfaces when crossing a boundary: PSR-3 logger, PSR-7/17 HTTP messages and factories, PSR-11
  container, PSR-14 events, PSR-15 middleware, PSR-18 HTTP client, PSR-20 clock. Depend on the interface, not on the
  implementation.
- Target the project's minimum PHP version (`composer.json` `require.php`, `config.platform.php`): no syntax or
  function newer than it.

### Types

- `declare(strict_types=1);` at the top of every PHP file.
- Native types on every parameter, return and property where PHP allows them, `void` and `never` included.
- No `mixed`, no bare `array`, no `array<string, mixed>` when the shape is known: write it in PHPDoc
  (`list<Order>`, `array<string, int>`, `array{id: string, line: int, service?: string}`, `class-string<T>`,
  `non-empty-string`, `positive-int`, generics with `@template`). Open shapes only at the boundaries (decoded JSON,
  request input, CLI arguments), narrowed right there into typed values.
- A structure passed between classes is an object (`final readonly class`), not an array of keys.
- PHPDoc only adds what the native type cannot say. Never repeat a native type in a `@param`/`@return`/`@var`.
- Enums (backed when stored or sent) instead of string or int constants for a closed set of values.
- `null` means "absent", and only where absence is possible: no nullable parameter used as an optional flag.
- Strict comparisons (`===`, `!==`, `in_array(..., true)`); no `==`, no truthiness tests on strings or numbers
  (`if ($count)`, `empty($name)`): compare with the value meant.
- Casts only at the boundary and explicit (`(int)`), never to silence the type checker.

### Classes and objects

- `final` by default. A class is open only when it is designed for extension (abstract base) or a test must extend it.
- Value objects and results are `final readonly class`, built complete and valid by their constructor or a named
  constructor (`Money::fromCents()`), never by setters.
- Every property and method `private` until something outside needs it; `protected` only in a class built for
  inheritance.
- Composition over inheritance; an interface per role, small (one to a few methods), named after the role.
- Dependencies arrive through the constructor (promoted properties). No service locator, no `new` of a service inside
  business code, no static mutable state, no singletons, no global functions holding state.
- Mark overrides with `#[\Override]` on PHP 8.3 and later.
- No magic methods (`__get`, `__call`) unless a framework requires them.
- `DateTimeImmutable` (or a PSR-20 clock injected), never `DateTime` nor `time()` / `new DateTimeImmutable()` deep in
  business code: time comes from a clock so tests control it.
- Money and other exact quantities: integers of the smallest unit or a decimal library, never `float`.

### Functions and control flow

- One expression = one idea. No nested ternary (`a ? b : (c ? d : e)`): successive `if`/`return`, a `match`, or a
  named private method. A single, simple ternary is fine.
- No chain of conditional array unions (`[...] + ($x === null ? [] : ['k' => $x]) + ...`): build the base array, then
  one `if` per optional key.
- An expression that mixes several computations (a string assembled from conditional parts, a sort key, a combined
  condition) is split into named variables or a private method that says what it computes.
- Guard clauses and early returns instead of nested `if`; no `else` after a `return`, `throw`, `continue` or `break`.
- No assignment inside a condition (`if ($x = f())`): assign on its own line, then test.
- `match` instead of `switch` when mapping values; it is strict and exhaustive (`UnhandledMatchError`).
- No boolean flag parameter that changes what a method does: two methods, or an enum. Name literal arguments at the
  call site when their meaning is not obvious (`send(retry: false)`).
- Short methods that do one thing at one level of abstraction; a method whose body needs a comment to explain its
  steps is several methods.
- Prefer `foreach` and plain loops over `array_map`/`array_filter` chains when the chain needs keys, references or
  more than one line of closure.
- Use `mb_*` functions for user-facing text; byte functions (`strlen`, `substr`) only for bytes and ASCII
  identifiers.

### Naming

- Names say intent, in full words: `$unpaidInvoices`, not `$data`, `$arr`, `$tmp`, `$inv2`.
- Booleans read as questions: `isPaid`, `hasItems`, `canRetry`.
- Methods are verbs (`cancel()`, `totalPrice()`); a getter that computes nothing is the property's name or `get*`,
  as the project already does.
- Follow the project's suffix conventions (`*Interface`, `*Exception`, `*Test`, `*Handler`) and invent none.
- Code, identifiers, messages, logs and commits in English unless the project says otherwise.

### Errors

- Throw an exception for a failure; don't return `false`, `null` or an error code from a method whose result is
  otherwise an object. Return `null` only for "not found" when absence is a normal outcome, and type it `?X`.
- Specific exception classes (extending SPL ones: `InvalidArgumentException`, `RuntimeException`, `LogicException`),
  with a message stating what was wrong and the value involved. Keep the cause: `new X('...', previous: $e)`.
- Never catch to ignore. Catch only what you can handle, as narrow a type as possible; never `catch (\Throwable)`
  except at the outermost boundary (entry point, worker loop), where it is logged.
- No `@` error suppression; no `die()`/`exit()` outside an entry script.
- Validate input at the boundary, once, and turn it into typed objects; code behind the boundary trusts its types
  instead of re-checking them.
- Document `@throws` for exceptions a caller is expected to handle.

### Comments

- No comment saying what the code does: rename, extract a method or a variable until the code says it.
- A comment only for a why the code cannot express (a constraint, a workaround with its reason and link, a non-obvious
  decision). A class or public method may carry a docblock stating its contract when its name cannot. Some projects
  forbid even those: follow the project.
- No commented-out code, no `TODO` without a ticket reference, no change log in comments (git has it).
- No debugging leftovers: `dump()`, `dd()`, `var_dump()`, `print_r()`, `die()`, `error_log()` for tracing.

### Security

- SQL through prepared statements or the query builder only; never concatenate input into SQL, shell commands
  (`escapeshellarg` if a shell is unavoidable), paths or headers.
- Escape output for its context (HTML, attribute, URL, JSON); use the template engine's escaping.
- Never `eval`, never `unserialize` untrusted data (or with `['allowed_classes' => [...]]`), never `extract`.
- Secrets come from the environment or a secret store, never from the code or a committed file.
- `random_bytes()`/`random_int()` for anything secret, `password_hash()`/`password_verify()` for passwords,
  `hash_equals()` to compare tokens.
- Never run or include code of a project you are only analysing.

### Performance

- Measure before optimising; readable first.
- No query, HTTP call or file read inside a loop over records (N+1): load in bulk before the loop.
- Generators or chunked iteration for large data sets instead of loading everything in memory.

### Dependencies

- Add a Composer dependency only when it saves real work; prefer what the project already has.
- Constraints with `^`, the lock file committed for applications, `composer audit` clean.
- Never edit `vendor/`.

### Tests

- Every change of behaviour comes with a test; a bug fix starts with a test that fails without the fix.
- Follow the project's framework and style (PHPUnit or Pest, folder, base classes, helpers). Test classes `final`.
- One behaviour per test, named as a sentence (`testAnExpiredCouponIsRefused`), arranged as given / when / then.
- `assertSame` over `assertEquals`; an assertion message saying why when the failure would be unclear.
- Test through the public API, never private methods. Don't mock value objects or the class under test; prefer real
  objects, then in-memory fakes, then stubs; mocks only to check an interaction that is the behaviour itself.
- Deterministic: no real clock, randomness, network or shared state; inject them.
- Data providers for the same behaviour over several inputs.

### Tooling and verification

- Run the project's checks before handing work back (`composer check`): formatter, static analysis, tests. Report
  failures as they are, with their output.
- Static analysis at the project's level (PHPStan; aim for the highest level on new code). Never add a baseline
  entry or `@phpstan-ignore` to make a check pass: fix the code. A false positive is reported, with why.
- Rector and the formatter are run, never fought: if they rewrite your code, keep their version.
- Verify behaviour in the running application (CLI command, HTTP request, worker) when the change can be observed
  there, not only through unit tests.

### Working method

- Read before writing: the files you change, their callers, their tests, and the neighbouring code you will imitate.
- Smallest change that solves the task. No unrelated refactoring, renaming or reformatting in the same change; note
  what you saw instead.
- Never change a public API, a serialised format or a database schema as a side effect: it is a decision to state.
- Don't leave work half done: no stub, no `// implement later`, no skipped test.
- Say what you did not do or could not verify.
