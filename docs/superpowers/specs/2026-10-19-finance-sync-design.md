# A → B → C finance integration

## Scheduling correction

The user clarified that A → B cloning is hourly and B → C is manual. B schedules `finance:clone-from-a`, which restores A into B and publishes only after success. C has no scheduler; a CSRF-protected POST action on its dashboard invokes the existing consumer. The demo launcher starts only B's scheduler and stops the previously managed C scheduler. This supersedes the original hourly-consumer schedule described below.

## Current application separation

The initial combined prototype has been split at the user's request. A remains at the repository root. `app-b/` is an independent Laravel publisher using MSSQL B, and `app-c/` is an independent Laravel consumer/dashboard using PostgreSQL. Each has its own artisan entry point, Composer dependencies, environment, cache, storage, routes and tests. Runtime code is not loaded across application directories. The root `phpunit.integration.xml` suite loads B/C code only for testing their contract.

Publisher files now live under `app-b/app/`; consumer/dashboard files and mirror migrations live under `app-c/`. Root A no longer hosts `/app-c`, snapshot routes, or finance synchronization commands. Its sidebar links to the separate C origin (default port 8002). The active deployment instructions are in the root README. The original approach below remains applicable to the data-transfer logic; combined-installation references are superseded by this separation.

Approved direction: A and B use MSSQL; A is cloned to B. C uses PostgreSQL and pulls B through an API gateway hourly. This repository supplies reusable B/C components, as approved in chat. Existing A admin and API routes remain compatible.

## Scope and approach

The known source schema contains `db_transaksi` and `db_pegawai`. Only these tables are exported; this is a data mirror, not a conversion of MSSQL stored procedures or an arbitrary database clone. B reads its own restored database, never A. C stores the mirror in dedicated `mirror_b_*` tables; application users/settings remain separate.

For thousands of rows, use immutable full snapshots, paginated at 500 rows, rather than timestamp deltas (the source has no modification timestamps or deletion log). This captures updates and deletes. A later Change Tracking implementation can reduce transfer volume after measurement. Direct per-page live queries were rejected because concurrent edits can mix source states.

B publishes snapshots in a serializable database transaction with stable ID ordering. Publication uses a private directory and an atomic latest-manifest replacement. Clone/restore must finish before publication. Exports include exact decimal strings, row counts, and page checksums. Existing snapshots expire after 24 hours and are cleaned by the next successful publication.

C authenticates with a server-side API key, downloads and validates every page into a unique private staging directory, then replaces both mirror tables and records the snapshot ID in one database transaction. A failure preserves the previous mirror; an already applied snapshot is skipped. A PostgreSQL advisory transaction lock serializes final application across processes; cache locks prevent normal overlapping runs. Remote HTTP access uses timeouts, bounded retries, no redirects, and HTTPS outside local development.

## Components

- Config: disabled by default; independent publisher and consumer flags, source/target connection names, upstream URL/key, batch size and snapshot lifetime.
- B: publish command and API-key-protected manifest/page GET routes. C: migration, sync command, and hourly schedule. No application-C public UI is added.
- A → B: parameterized SQLCMD backup/restore script and runbook, with explicit target replacement opt-in; never automatically executed by the agent. Database backup includes only the database, not server logins/jobs. Extra B data cannot live in the overwritten database.
- Deployment: separate Laravel installations for A/B/C, separate secrets; B storage must be shared if serving from multiple nodes. Provider APIs disabled except on B.

## Verification

Feature tests cover authentication, role gates, immutable pagination, decimal preservation, updates/deletes, empty snapshots, corrupt/missing pages, rollback, repeat synchronization and configuration validation. SQLite is used for automated behavior checks; native MSSQL/PostgreSQL integration requires provisioned test databases and `pdo_pgsql`. No live database is restored or migrated automatically.
