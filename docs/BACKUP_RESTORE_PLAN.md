# Backup & Restore Resilience Plan

## Recovery Objectives

| Metric | Target | Notes |
|--------|--------|-------|
| **RPO** (Recovery Point Objective) | 24 hours | Maximum acceptable data loss |
| **RTO** (Recovery Time Objective) | 1 hour | Maximum time to restore service |

## What Needs Backing Up

| Asset | Location | Method |
|-------|----------|--------|
| **Database** (MySQL) | `goonsgearDB` on 127.0.0.1:3306 | `mysqldump` |
| **Uploaded media** | `storage/app/public/` on server | File copy / rsync |
| **Environment config** | `.env` on server | Manual backup (contains secrets) |

Code is **not backed up** — it's always recoverable from the Git repository via the deploy pipeline.

## Database Backup

### Manual Backup (SSH)

```bash
ssh -p 1221 spored3v@<SSH_HOST>
cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio

# Dump full database
mysqldump -u goonsgearUSER -p goonsgearDB > ~/backup-$(date +%Y%m%d-%H%M%S).sql

# Compressed
mysqldump -u goonsgearUSER -p goonsgearDB | gzip > ~/backup-$(date +%Y%m%d-%H%M%S).sql.gz
```

### Automated Daily Backup (Cron)

Add to CloudPanel cron or server crontab:

```cron
0 3 * * * mysqldump -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB | gzip > /home/macaw-goonsgear/backups/db-$(date +\%Y\%m\%d).sql.gz && find /home/macaw-goonsgear/backups/ -name "db-*.sql.gz" -mtime +7 -delete
```

This runs at 03:00 daily and keeps 7 days of backups.

### Restore from Backup

```bash
# Decompress if needed
gunzip backup-20260330.sql.gz

# Restore (WARNING: overwrites existing data)
mysql -u goonsgearUSER -p goonsgearDB < backup-20260330.sql
```

## Media Backup

### Manual Backup

```bash
# On server — tar the public storage
cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio
tar -czf ~/media-backup-$(date +%Y%m%d).tar.gz storage/app/public/
```

### Restore Media

```bash
cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio
tar -xzf ~/media-backup-20260330.tar.gz
php artisan storage:link --force --no-interaction
```

## Full Restore Procedure (Staging)

If the staging environment is completely lost:

1. **Provision a new server** and install CloudPanel
2. **Create site** pointing to `goonsgear.macaw.studio`
3. **Create MySQL database** (same credentials or update `.env`)
4. **Deploy code**: trigger GitHub Actions `deploy-stage.yml` (workflow_dispatch)
5. **Create `.env`** on the server (copy from `.env.staging` in repo, adjust as needed)
6. **Restore database**:
   ```bash
   mysql -u goonsgearUSER -p goonsgearDB < backup.sql
   ```
7. **Restore media** (if backup exists):
   ```bash
   tar -xzf media-backup.tar.gz
   ```
8. **Fix permissions**:
   ```bash
   chmod -R 770 storage bootstrap/cache
   ```
9. **Run post-deploy**:
   ```bash
   php artisan storage:link --force
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

If no database backup exists, seed from scratch:
```bash
php artisan migrate:fresh --force --seed --no-interaction
```

## Staging Restore Drill Checklist

Perform this drill periodically to verify the restore process works:

- [ ] Take a fresh database dump
- [ ] Verify dump file is valid: `mysql -u root -e "SOURCE backup.sql" --database=test_restore`
- [ ] Confirm file size is reasonable (not empty or truncated)
- [ ] Verify media backup contains expected files
- [ ] Document any issues encountered

## Notes

- CloudPanel may provide its own backup mechanism — check CloudPanel UI > Backups
- For production, consider offsite backups (S3, external storage) and longer retention
- Database dumps should be stored outside the web-accessible directory
- Never commit database backups or `.env` files to Git
