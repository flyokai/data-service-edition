# flyokai/data-service-edition

> Project template for socket-based async data services on the [Flyokai framework](https://github.com/flyokai/flyokai).

Includes everything in [`flyokai/webapi-edition`](https://github.com/flyokai/webapi-edition) plus the **data-service stack** — long-running services that accept channel-multiplexed requests over a socket, with OAuth handshake, typed request/response routing, middleware pipeline, and remote iterators.

## Bootstrap a new project

```bash
composer create-project flyokai/data-service-edition my-service dev-dev
cd my-service

vendor/bin/flyok-setup install \
    --db-host=localhost \
    --db-user=app \
    --db-pass=secret \
    --db-name=app \
    --base-url=http://localhost:8080
```

`vendor/bin/flyok-setup install` deploys the runtime scripts (`bin/flyok-setup`, `bin/flyok-console`, `bin/flyok-cluster`), creates `storage/flyok.config.json`, generates the OAuth signing keys, and reconciles the database from your `#[Table]`-tagged Solid DTOs.

## What's included

Everything in the **webapi-edition**, plus:

| Module | Purpose |
|--------|---------|
| `flyokai/data-service` | Socket listener, OAuth handshake, router, middleware |
| `flyokai/data-service-message` | Built-in request/response DTOs (`Ping`, `AccessToken`, `EchoIterator`, `DdlTableDefinition`, `RunReindex`) |

> `flyokai/amp-channel-dispatcher` ships in both editions (the cluster/worker IPC depends on it) — only `data-service` and `data-service-message` differentiate this edition.

Full module list:

| Group | Modules |
|-------|---------|
| Application core | `flyokai/application`, `flyokai/amphp-injector`, `flyokai/revolt-event-loop` |
| DTOs / patterns | `flyokai/data-mate`, `flyokai/composition`, `flyokai/generic`, `flyokai/misc` |
| Schema & search | `flyokai/db-schema`, `flyokai/search-criteria` |
| Database | `flyokai/laminas-db`, `flyokai/laminas-db-driver-amp`, `flyokai/laminas-db-driver-async`, `flyokai/laminas-db-bulk-update`, `flyokai/zend-db-sql-insertmultiple` |
| Async infra | `flyokai/amp-mate`, `flyokai/amp-data-pipeline`, `flyokai/amp-csv-reader`, `flyokai/amp-opensearch`, `flyokai/amp-channel-dispatcher` |
| CLI | `flyokai/symfony-console` |
| Auth | `flyokai/user`, `flyokai/oauth-server` |
| Indexer | `flyokai/indexer` |
| **Data service** | **`flyokai/data-service`, `flyokai/data-service-message`** |
| Docs | `flyokai/flyokai` (metapackage) |

For the framework reference, see `vendor/flyokai/flyokai/README.md` after install.

## Architecture (data-service flow)

```
Client → Socket → OAuth Handshake → StreamChannel → Dispatcher → Router → Middleware → Handler → Response
```

1. Client connects → `SocketDataServer::accept()`.
2. The first message **must** be `AccessToken`; the server validates it before anything else.
3. `StreamChannel` + `Dispatcher` are initialised.
4. The dispatcher loop receives → routes → middleware → handler → sends response, until the client disconnects or times out.

See `vendor/flyokai/data-service/README.md` for builders, middleware, and route registration.

## Day-to-day commands

```bash
# Subsequent setup runs
php bin/flyok-setup upgrade

# General CLI
php bin/flyok-console acl:role:create --name admin '[]'
php bin/flyok-console user:create --uname=admin --email=admin@example.com --role=admin --pass=secret123
php bin/flyok-console oauth:client:create admin client_credentials

# Cluster (workers run HTTP + listen for cluster messages)
php bin/flyok-cluster start
php bin/flyok-cluster stop
php bin/flyok-cluster restart

# Reindex
php bin/flyok-console indexer:reindex

# Single-process HTTP (testing)
php bin/flyok-console http:start
```

## Adding a request handler

The [`new-request-handler`](.agents/skills/flyokai-new-request-handler.md) skill scaffolds:

1. A request DTO in `Request/MyRequest.php`
2. A response DTO in `Response/MyResponse.php`
3. A handler in `Handler/MyHandler.php`
4. A `diconfig_worker.php` registration on `dataRouterBuilder:default`

Inside the worker bootstrap you'd write something like:

```php
$routerBuilder = $this->container->get('dataRouterBuilder:default');
$routerBuilder->addRoute(MyRequest::class, $this->container->get(MyHandler::class));
```

## Branching

Same as the rest of the framework:

- `main` — stable.
- `dev` — active. `composer create-project … dev-dev` resolves to it.

## `composer.lock` is committed — and that's intentional

This template ships with a `composer.lock` checked in, and `composer create-project` will use it.

### Why

1. **Reproducibility for new projects.** When someone runs `composer create-project flyokai/data-service-edition my-service`, they get the exact same vendor versions every team member gets. Without the lock, each new project would float to whatever HEAD looks like on every flyokai/* repo at install time — leading to subtle drift.
2. **Tested combinations.** The lock pins a combination of flyokai/* versions that has been smoke-tested together. Random `dev-dev` HEADs across 26 packages are not.
3. **Faster installs.** `composer install` (which is what `create-project` runs) reads the lock directly. `composer update` resolves the dependency graph from scratch — much slower and network-heavy.

### Update flow

In **this template repository**:

```bash
# 1. Bump dependency tips. This re-resolves the entire graph.
composer update

# 2. Run the local smoke test. (Repeat install, run setup, sanity check.)
rm -rf vendor bin storage
vendor/bin/flyok-setup install --db-host=… --db-user=… …
php bin/flyok-console ping
# (Optional) start the data-service and send a test request.

# 3. If everything works, commit the new lock.
git add composer.lock
git commit -m "chore: bump flyokai/* to <date>"
git push origin dev
# Merge dev → main when you want the stable channel to advance.
```

When (not if) `composer update` produces an incompatible mix, `composer.json` is the place to add temporary version constraints (e.g. `"flyokai/foo": "dev-dev as 1.2.0"` or pin to a specific commit) until the upstream module is fixed.

In a **downstream project** (one that was created from this template):

```bash
# Pull whatever versions the template author tested.
composer install                                 # uses the lock as-is

# Or bump just the flyokai/* family at any time.
composer update 'flyokai/*'

# Or take whatever is HEAD on every dependency.
composer update
```

Downstream projects keep their own `composer.lock` once they diverge — they don't track the template's lock anymore.

### When to update the template's lock

- **Always:** before tagging a new release of either edition.
- **On security fixes** in any flyokai/* dependency.
- **On a regular cadence** (weekly or monthly) so the lock doesn't drift months away from the actual `dev` branches.
- **Never** silently — every lock bump should be a single commit with a recognisable message and a smoke test passing.

## What's *not* in this edition

- **Magento DTOs / cache backend** — `flyokai/magento-dto`, `flyokai/magento-amp-mate`. Add via `composer require` if your project needs them.
- **Business modules** — `unirgy/rapidflow-*`, `unirgy/license-*`, `unirgy/service-connector`. Project-specific.

If your project is a plain HTTP API and does not need socket-based async services, use [`flyokai/webapi-edition`](https://github.com/flyokai/webapi-edition) instead.

## License

MIT
