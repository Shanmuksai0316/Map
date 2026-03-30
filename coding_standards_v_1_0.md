# CodingStandards_v1.0.md — MAP‑HMS

**Release:** v1.0  
**Date:** 26‑Sep‑2025 (IST)  
**Scope:** Laravel/PHP, React Native/TypeScript, Git/PR process, performance, security

---

## 0) Principles
- Prefer readable, boring solutions over cleverness.  
- Pure functions where possible; isolate IO.  
- Small files & components; one responsibility.  
- Everything tested at least once.

---

## 1) PHP/Laravel
- **Style:** PSR‑12 via **Laravel Pint**. No custom sniffs unless necessary.  
- **Static analysis:** **PHPStan** level 6+ (tune for Laravel generics); or Psalm equivalent.  
- **Types:** Native scalar & return types everywhere; `mixed` forbidden.  
- **Controllers:** thin; delegate to Services/Actions.  
- **Requests:** dedicated `FormRequest` classes; validate *all* inputs; custom rules for `tenant_id` scope if needed.  
- **Resources (API):** explicit Transformers; never leak internal fields; use snake_case in DB, camelCase in JSON if desired (stick to one).  
- **Policies:** check role + scope; deny by default.  
- **Events/Queues:** all slow tasks go to queues (`notifications`, `webhooks`, `exports`, `uploads`).  
- **Migrations:** forward‑only; destructive changes in two steps (add new → backfill → switch → drop old).  
- **Errors:** Use consistent error codes (see API spec). No HTML errors on API routes.  
- **Testing:** Pest + Factories; DB transactions; test policies and validation; seed multi‑tenant fixtures.  
- **Performance:** avoid N+1; eager load; paginate; cache hot counts in Redis with short TTL.

**Directory hints:**
```
app/Domain/<Module>/{Models,Policies,Actions,Requests,Resources,Controllers}
```

---

## 2) React Native / TypeScript
- **Language:** TypeScript strict mode on; no `any` except typed escapes with comment.  
- **State:** **Zustand**; avoid deep global state; colocate where possible.  
- **Components:** function components; hooks; no class components.  
- **Networking:** central `api/client.ts` with fetch wrapper (retries/backoff, auth header, JSON decode, error mapping).  
- **Forms:** `react-hook-form` + `zod` schemas; input masks for phones.  
- **Navigation:** stable screen names; deep links for notifications.  
- **Styling:** inline style objects or `StyleSheet.create`; theme tokens from **DesignSystem_v1.0.md**.  
- **Accessibility:** a11y labels, roles; min 44px targets.  
- **Testing:** Jest + RTL for components; Detox E2E for login + 1 critical flow.  
- **Performance:** memoize list rows; FlatList with `keyExtractor`; avoid nested scrolls; image size caps & compression before upload.

---

## 3) Security & Privacy
- Never log secrets, OTPs, tokens, medical text, or PII.  
- Enforce **step‑up OTP** for approvals/PII/mark‑as‑paid; one active device; device binding.  
- Block screenshots on OTP/PII screens.  
- Presigned S3 uploads only; MIME allowlist; AV scan; EXIF strip.  
- Webhooks verified (HMAC) + idempotent.

---

## 4) Commits, Branches, PRs
- **Conventional Commits:** `feat`, `fix`, `docs`, `chore`, `refactor`, `perf`, `test`, `ci`, `build`, `revert`.  
- **Branching:** `feat/<module>-<short>` or `fix/<area>-<short>`; PR to `develop`, squash merge.  
- **PR checklist:** screenshots (UI), migrations & data impacts, tests, flags, security notes, updated docs links.  
- **Review SLA:** 24h for peer review; emergency hotfixes allowed on `main` with back‑merge to `develop`.

---

## 5) Error Taxonomy
- **Format:** `{ code, message, details? }` JSON; HTTP per RFC.  
- **Families:**  
  - `E_VALIDATION`, `E_UNAUTHORIZED`, `E_FORBIDDEN_SCOPE`, `E_NOT_FOUND`, `E_RATE_LIMITED`,  
  - `E_CONFLICT`, `E_TENANT_ADDON_DISABLED`, `E_STEPUP_REQUIRED`,  
  - `E_UPLOAD_REJECTED`, `E_WEBHOOK_SIGNATURE_INVALID`, `E_WEBHOOK_DUPLICATE`, `E_INTERNAL`.

---

## 6) Observability
- **Logs:** JSON; include `req_id`, `actor_user_id`, `actor_role`, `tenant_id`; mask PII.  
- **Tracing:** minimal (req id); correlate with Horizon job ids.  
- **Dashboards:** Sentry error budgets; CloudWatch for queue lag, webhook failures; API latency p95.

---

## 7) Definition of Done
- Code formatted & linted; unit/API tests green; E2E for critical paths updated.  
- Feature behind flags if risky; migrations safe and documented.  
- Docs updated (OpenAPI/PRD/CHANGELOG).  
- Review complete; rollout notes added if needed.

