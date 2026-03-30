# Default Project Workflow (Future Projects SOP)

Use this as the standard operating procedure for all new projects.  
Goal: every project starts with the same structure, quality gates, and deployment readiness from day 1.

## 1) Default Stack and Tools

- Backend: Laravel + PostgreSQL + Redis + Queue/Horizon
- Admin/Web: Filament
- Mobile: React Native (TypeScript) with app variants if needed
- State and validation: Zustand + React Hook Form + Zod
- Infra: Docker, Cloudflare DNS/SSL, VPS + Coolify/containers
- Integrations baseline: SMS provider, push notifications, email provider, S3-compatible storage
- Quality tools: Pest, Jest, PHPStan, Pint, ESLint, TypeScript checks

## 2) Project Bootstrap Checklist (Day 0)

## Repository Setup

- [ ] Create mono-repo structure: `api/`, `mobile/`, `docs/`, `scripts/`, `config/`
- [ ] Add `README.md`, `CONTRIBUTING.md`, `docs/README.md`
- [ ] Add `.editorconfig`, `.gitignore`, `.gitattributes`
- [ ] Add `Makefile` with `setup`, `lint`, `test`, `review`, `deploy` targets
- [ ] Configure commit style (Conventional Commits)

## Environment Setup

- [ ] Define `.env.example` for API and mobile
- [ ] Configure local Docker services (PostgreSQL, Redis)
- [ ] Verify app starts locally (API + web panel + mobile)
- [ ] Add health endpoints (`/health`, `/health/detailed`)
- [ ] Add seed data for demo/UAT roles

## Security and Access Baseline

- [ ] Implement role-based permissions before feature development
- [ ] Implement tenant isolation rules (if multi-tenant)
- [ ] Add audit logging for sensitive actions
- [ ] Add OTP/step-up auth for sensitive flows
- [ ] Add rate limiting and strict request validation

## 3) Default Build Workflow (Every Feature)

1. Define scope by role and surface (API, web, mobile, integration).
2. Implement backend contract first (routes, validation, policy, service).
3. Implement web/mobile in parity with backend contract.
4. Add tests for happy path, failure path, auth path, tenant path.
5. Run local quality gates.
6. Update docs and UAT checklist in same PR.

## 4) Mandatory Quality Gates (Before Merge)

- API tests pass: `cd api && vendor/bin/pest`
- Mobile tests pass: `cd mobile && npm test`
- PHP static analysis clean: `cd api && ./vendor/bin/phpstan analyse`
- PHP formatting clean: `cd api && ./vendor/bin/pint --test`
- Mobile lint/type clean: `cd mobile && npm run lint`
- Migration safety checked (forward + rollback + idempotency where required)
- Health check script passes

If any gate fails, no merge and no deploy.

## 5) Release Workflow (Default)

1. Freeze scope and update changelog.
2. Run full quality suite and security audit.
3. Take database backup.
4. Deploy via script/automation.
5. Run migrations safely.
6. Run post-deploy health checks.
7. Validate critical user journeys (login, role dashboard, primary transaction).
8. Validate integrations (SMS, push, email, storage).
9. Publish release notes and UAT evidence.

## 6) Deployment Defaults

- Always backup before migration.
- Always run health checks after deployment.
- Always verify queue worker status.
- Always keep rollback steps ready and tested.
- Prefer scripted deploys over manual commands.

## 7) Documentation Defaults (Required in Every Project)

- `docs/ARCHITECTURE_Overview.md`
- `docs/RELEASE_Checklist.md`
- `docs/TESTING_Strategy.md`
- `docs/SECURITY_Practices.md`
- `docs/KB/Troubleshooting.md`
- role-based UAT checklists

No feature is complete without docs updates.

## 8) Branching and Commit Convention

- Branch naming:
  - `feature/<name>`
  - `fix/<name>`
  - `docs/<name>`
  - `chore/<name>`
- Commit style:
  - `feat(scope): ...`
  - `fix(scope): ...`
  - `docs(scope): ...`
  - `chore(scope): ...`

## 9) Standard Command Set

```bash
# First time
make setup

# Daily workflow
make up
make lint
make test
make review

# Release prep
cd api && php artisan migrate:status
./scripts/db-dump.sh
./scripts/health-check-all.sh
```

## 10) Definition of Done (Project Standard)

A task is done only when all items are true:

- [ ] Backend complete with policy/authorization checks
- [ ] Web/mobile parity delivered where applicable
- [ ] Tests and quality gates pass
- [ ] Migration and deployment impact reviewed
- [ ] UAT checklist updated
- [ ] Release/docs updated
- [ ] Monitoring/health verification complete

---

Owner: Engineering  
Usage: Copy this SOP into every new project and apply unchanged unless explicitly overridden.
