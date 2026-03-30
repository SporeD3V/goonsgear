# Migration Rollback Playbook

Procedure for recovering from a failed migration on staging or production.

## Prerequisites

- SSH access to the target server
- Database credentials (in `.env` on the server)

## Quick Reference

```bash
# SSH into staging
ssh -p 1221 spored3v@<SSH_HOST>
cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio

# Check current migration status
php artisan migrate:status --no-interaction

# Roll back the last batch (all migrations in the most recent batch)
php artisan migrate:rollback --force --no-interaction

# Roll back a specific number of migrations
php artisan migrate:rollback --step=1 --force --no-interaction

# Roll back everything (DESTRUCTIVE — schema only, data is lost)
php artisan migrate:reset --force --no-interaction
```

## Step-by-Step Recovery

### 1. Identify the Failure

Check the deploy log or SSH into the server:

```bash
php artisan migrate:status --no-interaction
```

Look for migrations marked **Pending** (not yet run) or the last **Ran** batch that caused the issue.

### 2. Roll Back the Failed Batch

```bash
# Rolls back all migrations in the most recent batch
php artisan migrate:rollback --force --no-interaction
```

If only a specific number of migrations need reverting:

```bash
php artisan migrate:rollback --step=2 --force --no-interaction
```

### 3. Fix the Migration

1. Fix the migration file locally.
2. Push to `main` — the deploy pipeline re-runs automatically.
3. Alternatively, deploy manually and re-run:

```bash
php artisan migrate --force --no-interaction
```

### 4. If `down()` Method Is Missing or Broken

Some migrations may not have a `down()` method. In that case:

1. **Do NOT run `migrate:rollback`** — it will fail or do nothing useful.
2. Fix the issue manually via SQL:

```bash
php artisan tinker --execute '
    DB::statement("DROP TABLE IF EXISTS broken_table");
    DB::table("migrations")->where("migration", "like", "%broken_migration_name%")->delete();
'
```

3. Re-deploy with the corrected migration.

### 5. If the Database Is Corrupt

As a last resort on **staging only**:

```bash
# Drop all tables and re-run all migrations (DESTROYS ALL DATA)
php artisan migrate:fresh --force --no-interaction

# Re-seed if needed
php artisan db:seed --force --no-interaction
```

> **Never use `migrate:fresh` on production.** Restore from a database backup instead.

## Automated Rollback in the Pipeline

The deploy pipeline (`deploy-stage.yml`) runs migrations with `--force`. If a migration fails:

1. The `script_stop: true` flag causes the SSH step to exit immediately.
2. The deploy is marked as **failed** in GitHub Actions.
3. The previously deployed code files are already on the server, but the failed migration has not been applied (Laravel runs each migration in sequence and stops on error).

To recover: SSH in, roll back if needed, fix the migration, and re-deploy.

## Prevention Checklist

- [ ] Always test migrations locally with `php artisan migrate` and `php artisan migrate:rollback`
- [ ] Ensure every migration has a working `down()` method
- [ ] When modifying columns, include **all** existing column modifiers to prevent silent attribute loss
- [ ] Run `php artisan migrate:fresh --seed` locally to verify full migration chain
