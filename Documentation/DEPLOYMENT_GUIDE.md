# eConstruction Supply - Production CI/CD & Operations Guide

This operational manual documents the production architecture, version control standards, automated deployment pipeline, database migration protocols, and disaster recovery procedures for **eConstruction Supply** (`https://econstruction-supply.site`).

---

## 1. Architecture Overview

```text
┌──────────────────────────────────────┐
│ LOCAL DEVELOPMENT                    │
│ D:\projects\eConstructionSite        │
│                                      │
│ - Branch: development / feature/*    │
│ - Local testing & verification       │
│ - Clean Git commits                  │
└──────────────────┬───────────────────┘
                   │ git push
                   ▼
┌──────────────────────────────────────┐
│ GITHUB REPOSITORY                    │
│ hubIT1023/eConstructionSite          │
│                                      │
│ - master (Production-ready releases) │
│ - development (Active dev branch)    │
│ - Version tags: v1.0.0, v1.0.1...    │
│ - GitHub Actions CI/CD Pipeline      │
└──────────────────┬───────────────────┘
                   │ SSH Deployment Trigger
                   ▼
┌────────────────────────────────────────────────────────┐
│ DROPLET PRODUCTION (ubuntu-s-2vcpu-2gb-90gb-intel-syd1)│
│ econstruction-supply.site                              │
│                                                        │
│ 1. Container Pre-flight Health Check                   │
│ 2. Automated Pre-Deploy Database Snapshot              │
│ 3. Atomic Git Fetch & Checkout                         │
│ 4. Sequential Database Migration Runner                │
│ 5. Upload Directory & Permission Verification          │
│ 6. Automated Health Verification (HTTP 200 checks)     │
│ 7. Automatic Rollback on any failure                   │
│ 8. Audit logging in /root/eConstructionSite/deploy.log │
└────────────────────────────────────────────────────────┘
```

---

## 2. Branching Strategy

| Branch | Purpose | Deployment Target | Protection |
| :--- | :--- | :--- | :--- |
| **`master`** | Production-ready releases | Live Droplet (`econstruction-supply.site`) | Protected; deployed via CI/CD |
| **`development`** | Active integration branch | Local testing | Developer collaboration |
| **`feature/*`** | Specific features | Local development | Merged to `development` via PR |
| **`hotfix/*`** | Emergency production fixes | Direct PR to `master` and back-merged to `development` | Tagged upon merge |

---

## 3. Local Development Workflow

1. Switch to `development` branch before starting work:
   ```bash
   git checkout development
   git pull origin development
   ```
2. Create feature branch if working on large changes:
   ```bash
   git checkout -b feature/order-receipt-update
   ```
3. Test changes locally and run test suites:
   ```bash
   # Run verification test suites in tests/verification/
   ```
4. Stage and commit:
   ```bash
   git add <modified-files>
   git commit -m "feat(orders): add enhanced customer invoice layout"
   ```
5. Merge into `development` and push:
   ```bash
   git checkout development
   git merge feature/order-receipt-update
   git push origin development
   ```

---

## 4. Release & Deployment Workflow

When features in `development` are tested and ready for production release:

1. **Merge to `master`**:
   ```bash
   git checkout master
   git pull origin master
   git merge development
   ```
2. **Tag the Release**:
   ```bash
   git tag -a v1.0.1 -m "Release v1.0.1: Enhanced customer invoice layout"
   ```
3. **Push to GitHub**:
   ```bash
   git push origin master --tags
   ```
4. **Automated CI/CD**:
   - GitHub Actions validates PHP syntax across all project files.
   - Triggers deployment script `/root/scripts/deploy.sh` on the Droplet.
   - Alternatively, trigger manually from the **GitHub Actions tab** with `workflow_dispatch`.

---

## 5. Production Deployment Engine (`/root/scripts/deploy.sh`)

Deployments are governed by the automated, idempotent shell script on the Droplet:

```bash
# To deploy the latest master:
/root/scripts/deploy.sh origin/master

# To deploy a specific release tag or commit hash:
/root/scripts/deploy.sh v1.0.1
```

### Safety Steps Executed by `deploy.sh`:
1. **Pre-flight Container Health**: Ensures `econstructionsite-web`, `econstructionsite-db`, and `hubit-web` are running.
2. **Pre-Deploy Database Snapshot**: Automatically creates `ecomDB_pre_deploy_<timestamp>_<commit>.sql.gz` in `/root/backups/econstructionsite/pre_deploy/`.
3. **Git Fetch & Checkout**: Fetches and switches directly to the target commit/tag.
4. **Automated Migrations**: Checks for unapplied `.sql` scripts in `database/migrations/` and applies them sequentially.
5. **Permissions & Uploads Integrity**: Confirms `assets/uploads/` exists with proper write permissions.
6. **Health Verification**: Runs HTTP checks against local port `9090` and the public domain `https://econstruction-supply.site/`.
7. **Automatic Rollback**: If health checks return non-200, it automatically reverts to the previous commit and exits with error code 1.
8. **Audit Logging**: Appends timestamp, target commit, and previous commit to `/root/eConstructionSite/deployments.log`.

---

## 6. Database Migration Process

The database is PostgreSQL 14 (`ecomDB`). Schema changes must be controlled through version-controlled migration files.

### Adding a Migration:
1. Create a sequentially numbered `.sql` file in `database/migrations/`:
   ```text
   database/migrations/002_add_supplier_commission_column.sql
   ```
2. Write idempotent SQL:
   ```sql
   ALTER TABLE tbl_supplier ADD COLUMN IF NOT EXISTS commission_rate NUMERIC(5,2) DEFAULT 0.00;
   ```
3. Commit the migration file into Git:
   ```bash
   git add database/migrations/002_add_supplier_commission_column.sql
   git commit -m "db: add commission_rate column to tbl_supplier"
   ```
4. When `deploy.sh` runs, it detects `002_add_supplier_commission_column.sql`, executes it inside `econstructionsite-db`, and logs the migration in `tbl_migrations`.

---

## 7. Automated Daily Backups & Retention

A dedicated backup script `/root/scripts/backup-db.sh` runs automatically every day at 02:00 UTC via root crontab:

```text
0 2 * * * /root/scripts/backup-db.sh >/dev/null 2>&1
```

- **Daily Backup Directory**: `/root/backups/econstructionsite/daily/`
- **File Format**: `ecomDB_daily_YYYYMMDD_HHMMSS.sql.gz`
- **Retention Policy**: Backups older than 14 days are automatically pruned.
- **Log File**: `/root/backups/econstructionsite/backup.log`

### Manual On-Demand Backup:
```bash
/root/scripts/backup-db.sh
```

---

## 8. Emergency Rollback Process

If a production defect is identified after deployment:

### Automated Rollback (via script):
```bash
# Roll back to the immediately previous commit:
/root/scripts/rollback.sh

# Or roll back to a specific known-good release tag or commit hash:
/root/scripts/rollback.sh v1.0.0-production-baseline
```

### Database Restoration (if migration caused data corruption):
1. Locate the pre-deployment snapshot created immediately before the deployment:
   ```bash
   ls -lt /root/backups/econstructionsite/pre_deploy/
   ```
2. Restore the database dump:
   ```bash
   gunzip -c /root/backups/econstructionsite/pre_deploy/ecomDB_pre_deploy_<timestamp>_<hash>.sql.gz | docker exec -i econstructionsite-db psql -U ecom_admin -d ecomDB
   ```

---

## 9. Production Hotfix Procedure

If an urgent fix must be applied to live production:

1. Create a hotfix branch locally from `master`:
   ```bash
   git checkout master
   git pull origin master
   git checkout -b hotfix/pos-receipt-tax-calc
   ```
2. Apply the fix and verify locally.
3. Commit and tag:
   ```bash
   git commit -m "fix(pos): resolve receipt tax rounding issue"
   git tag -a v1.0.1-hotfix -m "Hotfix: POS tax calculation"
   ```
4. Merge into `master` and push:
   ```bash
   git checkout master
   git merge hotfix/pos-receipt-tax-calc
   git push origin master --tags
   ```
5. Trigger deployment via GitHub Actions or run on the Droplet:
   ```bash
   /root/scripts/deploy.sh v1.0.1-hotfix
   ```
6. **Back-merge to development** so changes are not lost:
   ```bash
   git checkout development
   git merge master
   git push origin development
   ```

---

## 10. Security & Secrets Management

- **Protected Secrets**: Database credentials, SMTP passwords, and API keys reside exclusively in `/root/eConstructionSite/.env`.
- **Git Exclusion**: `.env`, `*.env`, `.env.*` are excluded by `.gitignore` and must never be checked into Git.
- **Example Template**: Use `.env.example` to document environment variable names without exposing real passwords.
- **Database Port Security**: Port `54321` should be restricted to `127.0.0.1:54321` in `docker-compose.yml` to prevent public internet brute force attempts.

---

## 11. GitHub Actions Secrets Configuration

To enable automated SSH deployments via GitHub Actions, configure the following repository secrets in GitHub (`Settings` > `Secrets and variables` > `Actions`):

| Secret Name | Description | Value |
| :--- | :--- | :--- |
| `DROPLET_HOST` | Droplet Public IP address | `170.64.251.19` (or `209.38.26.235`) |
| `DROPLET_USER` | Deployment SSH user | `root` |
| `DROPLET_SSH_KEY` | Private SSH Key for authentication | Private key corresponding to Droplet `~/.ssh/authorized_keys` |
| `DROPLET_PORT` | SSH Port | `22` |

---

## 12. Troubleshooting & Health Checks

### Check live site status:
```bash
curl -sI https://econstruction-supply.site/
```

### View container logs:
```bash
docker logs econstructionsite-web --tail 50 -f
docker logs econstructionsite-db --tail 50 -f
```

### View deployment history:
```bash
cat /root/eConstructionSite/deployments.log
```

### Verify database connection:
```bash
docker exec -it econstructionsite-db psql -U ecom_admin -d ecomDB -c "SELECT count(*) FROM tbl_product;"
```
