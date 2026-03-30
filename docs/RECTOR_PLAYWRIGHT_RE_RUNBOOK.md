# Rector RE Playwright Runbook

This runbook executes the automated Rector RE workflow matrix:

- RE-008
- RE-011
- RE-012
- RE-013
- RE-014
- RE-029
- RE-030
- RE-031
- RE-033
- RE-021
- RE-025
- RE-028
- RE-039

## 1. Prerequisites

1. API/web app is reachable at a Rector panel URL (default used by script: `http://demo-college.localhost:8000`).
2. Rector login works with OTP.
3. Node dependencies are installed:

```bash
npm ci
npx playwright install chromium
```

## 2. Environment variables

Set these before the run (defaults are shown):

```bash
export PW_RECTOR_BASE_URL="http://demo-college.localhost:8000"
export PW_RECTOR_PHONE="+919876543216"
export PW_RECTOR_OTP="123456"

# Optional: fail when a case ends as NOT_DONE
export PW_RECTOR_FAIL_ON_NOT_DONE=0
```

## 3. Run the suite

Preferred command:

```bash
npm run test:e2e:rector:re
```

Equivalent raw command:

```bash
PW_HTML_REPORT_DIR=output/playwright/rector-re/html \
PW_OUTPUT_DIR=output/playwright/rector-re/test-results \
npx playwright test playwright/tests/rector-re-regression.spec.js --workers=1
```

## 4. Outputs

The run produces:

1. Playwright artifacts: `output/playwright/rector-re/test-results`
2. HTML report: `output/playwright/rector-re/html`
3. Case matrix report (generated per run):
   - `output/playwright/rector-re-<timestamp>/rector-re-report.md`
   - `output/playwright/rector-re-<timestamp>/rector-re-report.json`

## 5. Notes on statuses

- `PASS`: Case completed with expected evidence.
- `PARTIAL`: Flow executed but expected post-condition could not be fully confirmed.
- `NOT_DONE`: Required UI/data prerequisite was missing.
- `FAIL`: Automation failed unexpectedly (selector/route/error).
