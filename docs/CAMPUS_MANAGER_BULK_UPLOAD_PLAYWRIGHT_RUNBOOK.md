# Campus Manager Bulk Student Upload - Playwright Runbook

## What this automates

- Campus Manager OTP/bypass login
- Open `Bulk Upload Students` start-import page
- Upload a generated students CSV file
- Run import
- Verify import job reaches `Completed`
- Verify inserted rows are at least 1

## Prerequisites

```bash
cd /Users/nagrajyr/Downloads/mapmars
npm ci
npx playwright install chromium
```

## Environment variables

```bash
export PW_CM_BASE_URL="https://ppcu.mapservices.in"
export PW_CM_PHONE="7975452363"
export PW_CM_OTP="123456"
```

## Run

Preferred:

```bash
npm run test:e2e:campus:student-import
```

Equivalent raw command:

```bash
PW_HTML_REPORT_DIR=output/playwright/campus-student-import/html \
PW_OUTPUT_DIR=output/playwright/campus-student-import/test-results \
npx playwright test playwright/tests/campus-manager-student-import.spec.js --workers=1
```

## Artifacts

- Playwright results: `output/playwright/campus-student-import/test-results`
- HTML report: `output/playwright/campus-student-import/html`
- Run report: `output/playwright/campus-manager-student-import-<timestamp>/report.md`
- Upload fixture CSV: `output/playwright/campus-manager-student-import-<timestamp>/fixtures/`
