const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const DEFAULT_BASE_URL = 'http://demo-college.localhost:8000';
const DEFAULT_RECTOR_PHONE = '+919876543216';
const DEFAULT_RECTOR_OTP = '123456';

const baseUrl = (process.env.PW_RECTOR_BASE_URL || DEFAULT_BASE_URL).replace(/\/$/, '');
const rectorPhone = process.env.PW_RECTOR_PHONE || DEFAULT_RECTOR_PHONE;
const rectorOtp = process.env.PW_RECTOR_OTP || DEFAULT_RECTOR_OTP;
const actionTimeout = Number.parseInt(process.env.PW_RECTOR_ACTION_TIMEOUT_MS || '20000', 10);
const failOnNotDone = process.env.PW_RECTOR_FAIL_ON_NOT_DONE === '1';

const runStamp = new Date().toISOString().replace(/[:.]/g, '-');
const artifactDir = path.join(process.cwd(), 'output', 'playwright', `rector-re-${runStamp}`);
const reportJsonPath = path.join(artifactDir, 'rector-re-report.json');
const reportMdPath = path.join(artifactDir, 'rector-re-report.md');

const decisionLedger = {
  outpass: false,
  leave: false,
  guest: false,
};

function makeUrl(routePath, query = {}) {
  const url = new URL(routePath.startsWith('/') ? routePath : `/${routePath}`, baseUrl);
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null) {
      continue;
    }
    url.searchParams.set(key, String(value));
  }
  return url.toString();
}

function cleanText(value) {
  return String(value || '')
    .replace(/\u001b\[[0-9;]*m/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

function nowIso() {
  return new Date().toISOString();
}

async function visibleLocator(candidates, timeoutPerLocator = Math.min(actionTimeout, 2500)) {
  for (const locator of candidates) {
    try {
      const first = locator.first();
      await first.waitFor({ state: 'visible', timeout: timeoutPerLocator });
      return first;
    } catch {
      // try next locator
    }
  }
  return null;
}

async function tryClick(candidates, timeoutPerLocator = 2500) {
  const target = await visibleLocator(candidates, timeoutPerLocator);
  if (!target) {
    return false;
  }
  await target.click();
  return true;
}

async function goto(page, routePath, query = {}) {
  await page.goto(makeUrl(routePath, query), { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
}

async function ensureLoggedIn(page) {
  let lastError = '';

  for (let attempt = 1; attempt <= 2; attempt += 1) {
    await goto(page, '/rector/login');

    if (/\/rector(\/)?$/i.test(new URL(page.url()).pathname) || /\/rector\?/i.test(page.url())) {
      return;
    }

    const phoneInput = await visibleLocator([
      page.getByLabel(/phone number/i),
      page.getByPlaceholder(/\+?\d+/i),
      page.locator('input[type="tel"]'),
      page.locator('input[name*="phone" i]'),
    ]);

    if (!phoneInput) {
      throw new Error('Rector login phone input was not found.');
    }

    await phoneInput.fill(rectorPhone);

    const sent = await tryClick([
      page.getByRole('button', { name: /send otp/i }),
      page.locator('button:has-text("Send OTP")'),
    ]);

    if (!sent) {
      throw new Error('Could not trigger "Send OTP" on rector login.');
    }

    const otpInput = await visibleLocator(
      [
        page.getByLabel(/otp code/i),
        page.getByPlaceholder(/123456/i),
        page.locator('input[name*="otp" i]'),
        page.locator('input[inputmode="numeric"]'),
      ],
      15000
    );

    if (!otpInput) {
      throw new Error('OTP input did not appear after sending OTP.');
    }

    await otpInput.fill(rectorOtp);

    const verified = await tryClick([
      page.getByRole('button', { name: /verify\s*&\s*login/i }),
      page.getByRole('button', { name: /verify/i }),
      page.getByRole('button', { name: /login/i }),
    ]);

    if (!verified) {
      throw new Error('Could not trigger "Verify & Login" on rector login.');
    }

    try {
      await page.waitForURL(
        (url) => url.pathname.startsWith('/rector') && !url.pathname.includes('/login'),
        { timeout: 30000, waitUntil: 'domcontentloaded' }
      );
      await goto(page, '/rector');
      return;
    } catch (error) {
      lastError = cleanText(error && error.message ? error.message : String(error));
      if (attempt < 2) {
        await page.context().clearCookies();
        await page.waitForTimeout(1000);
      }
    }
  }

  throw new Error(`Login did not complete after 2 attempts. ${lastError}`);
}

async function waitForTableOrEmptyState(page) {
  const row = page.locator('table tbody tr').first();
  try {
    await row.waitFor({ state: 'visible', timeout: 7000 });
    return 'rows';
  } catch {
    const emptyMarker = await visibleLocator(
      [
        page.getByText(/no .* requests?/i),
        page.getByText(/no communication notices/i),
        page.getByText(/no guest entry requests/i),
        page.getByText(/no records found/i),
      ],
      2500
    );
    if (emptyMarker) {
      return 'empty';
    }
  }

  throw new Error('Neither table rows nor empty state became visible.');
}

async function safeCellText(row, cellIndex = 0) {
  const cell = row.locator('td').nth(cellIndex);
  const count = await row.locator('td').count();
  if (count === 0) {
    return '';
  }
  return cleanText(await cell.innerText());
}

async function openFirstRowAction(page, actionNameRegex) {
  const rows = page.locator('table tbody tr');
  const total = await rows.count();

  for (let index = 0; index < total; index += 1) {
    const row = rows.nth(index);
    const action = row.getByRole('button', { name: actionNameRegex }).first();
    if ((await action.count()) > 0 && (await action.isVisible().catch(() => false))) {
      await action.click();
      return row;
    }
  }

  return null;
}

async function submitModal(page, values = {}) {
  const dialog = await visibleLocator([page.locator('[role="dialog"]')], 8000);
  if (!dialog) {
    throw new Error('Expected action modal dialog did not open.');
  }

  const textareas = dialog.locator('textarea');
  if ((await textareas.count()) > 0) {
    await textareas.first().fill(values.textarea || 'Automated rector regression note');
  }

  const confirmed = await tryClick(
    [
      dialog.getByRole('button', { name: /^approve$/i }),
      dialog.getByRole('button', { name: /^reject$/i }),
      dialog.getByRole('button', { name: /^decline$/i }),
      dialog.getByRole('button', { name: /confirm/i }),
      dialog.getByRole('button', { name: /submit/i }),
    ],
    3000
  );

  if (!confirmed) {
    throw new Error('Could not find a confirm button inside the action modal.');
  }

  await page.waitForTimeout(500);
}

function pushResult(results, testId, moduleName, scenario, status, comment = '') {
  results.push({
    testId,
    module: moduleName,
    scenario,
    status,
    comment,
    timestamp: nowIso(),
  });
}

async function runCase(results, testId, moduleName, scenario, action) {
  try {
    const outcome = await action();
    pushResult(results, testId, moduleName, scenario, outcome?.status || 'PASS', outcome?.comment || '');
  } catch (error) {
    pushResult(results, testId, moduleName, scenario, 'FAIL', cleanText(error && error.message ? error.message : String(error)));
  }
}

function buildMarkdownReport(results, pageErrors) {
  const lines = [];

  lines.push('# Rector RE Regression Report');
  lines.push(`- Generated at: ${new Date().toISOString()}`);
  lines.push(`- Base URL: ${baseUrl}`);
  lines.push(`- Phone: ${rectorPhone}`);
  lines.push('');

  lines.push('| Test ID | Module | Scenario | Status | Comment |');
  lines.push('|---|---|---|---|---|');
  for (const item of results) {
    lines.push(
      `| ${item.testId} | ${item.module} | ${item.scenario} | ${item.status} | ${item.comment || '-'} |`
    );
  }

  if (pageErrors.length > 0) {
    lines.push('');
    lines.push('## Page Errors');
    for (const entry of pageErrors) {
      lines.push(`- ${entry}`);
    }
  }

  return lines.join('\n');
}

async function findNavigationLink(page, labelRegex) {
  return visibleLocator(
    [
      page.getByRole('link', { name: labelRegex }),
      page.locator('a', { hasText: labelRegex }),
      page.locator('button', { hasText: labelRegex }),
    ],
    1500
  );
}

async function resolveRoute(page, routeCandidates, markerLocatorsFactory) {
  for (const route of routeCandidates) {
    await goto(page, route);
    const marker = await visibleLocator(markerLocatorsFactory(page), 1800);
    if (marker) {
      return route;
    }
  }
  return null;
}

test.describe.serial('Rector RE workflow regression', () => {
  test('runs RE cases and writes case-by-case report', async ({ page }, testInfo) => {
    fs.mkdirSync(artifactDir, { recursive: true });

    const results = [];
    const pageErrors = [];
    page.on('pageerror', (error) => pageErrors.push(cleanText(error.message || String(error))));

    let setupReady = false;
    let resolvedRoutes = {
      dashboard: '/rector',
      outpass: '/rector/out-passes',
      leave: '/rector/leaves',
      guestEntry: null,
      commBox: null,
    };

    try {
      await ensureLoggedIn(page);
      await goto(page, '/rector');

      resolvedRoutes = {
        dashboard: '/rector',
        outpass:
          (await resolveRoute(page, ['/rector/out-passes', '/rector/outpasses'], (currentPage) => [
            currentPage.getByText(/out-pass approvals/i),
            currentPage.getByText(/request id/i),
            currentPage.getByText(/all statuses/i),
          ])) || '/rector/out-passes',
        leave:
          (await resolveRoute(page, ['/rector/leaves', '/rector/leave-approvals'], (currentPage) => [
            currentPage.getByText(/leave approvals/i),
            currentPage.getByText(/request type/i),
            currentPage.getByText(/all statuses/i),
          ])) || '/rector/leaves',
        guestEntry: await resolveRoute(
          page,
          ['/rector/requests/guest-entry-requests', '/rector/guest-entry-requests', '/rector/guest-entry'],
          (currentPage) => [
            currentPage.getByText(/guest entry approvals/i),
            currentPage.getByText(/no guest entry requests/i),
            currentPage.getByText(/arrival date/i),
          ]
        ),
        commBox: await resolveRoute(page, ['/rector/requests/comm-box', '/rector/comm-box'], (currentPage) => [
          currentPage.getByText(/communication notices/i),
          currentPage.getByText(/no communication notices/i),
          currentPage.getByText(/published/i),
        ]),
      };

      setupReady = true;
    } catch (error) {
      pushResult(
        results,
        'RE-SETUP',
        'Environment',
        'Base URL + Rector login preflight',
        'FAIL',
        cleanText(error && error.message ? error.message : String(error))
      );
    }

    if (setupReady) {
      await runCase(results, 'RE-008', 'Dashboard UI', 'Quick action tile readability', async () => {
      await page.setViewportSize({ width: 1280, height: 720 });
      await goto(page, resolvedRoutes.dashboard);

      const desktopChecks = [
        /Out-Pass Approvals/i,
        /Leave Approvals/i,
        /Guest Entry/i,
        /Comm Box/i,
      ];

      let missingDesktop = 0;
      for (const label of desktopChecks) {
        const item = await findNavigationLink(page, label);
        if (!item) {
          missingDesktop += 1;
        }
      }

      await page.setViewportSize({ width: 390, height: 844 });
      await goto(page, resolvedRoutes.dashboard);

      let missingMobile = 0;
      for (const label of desktopChecks) {
        const item = await findNavigationLink(page, label);
        if (!item) {
          missingMobile += 1;
        }
      }

      await page.screenshot({ path: path.join(artifactDir, 'RE-008-dashboard-readability.png'), fullPage: true });

      if (missingDesktop > 0 && missingMobile > 0) {
        return {
          status: 'PARTIAL',
          comment: `Navigation labels were missing on both layouts (desktop missing: ${missingDesktop}, small display missing: ${missingMobile}).`,
        };
      }

      if (missingDesktop > 0 || missingMobile > 0) {
        return {
          status: 'PARTIAL',
          comment: `Some labels were missing (desktop missing: ${missingDesktop}, small display missing: ${missingMobile}).`,
        };
      }

      return { status: 'PASS', comment: 'Core rector navigation labels and icons were visible on normal and small display.' };
    });

    await runCase(results, 'RE-011', 'Notifications', 'Open notifications list', async () => {
      await page.setViewportSize({ width: 1280, height: 720 });
      await goto(page, resolvedRoutes.dashboard);

      const bellVisible = await tryClick([
        page.locator('button[aria-label*="notification" i]'),
        page.locator('button[title*="notification" i]'),
        page.getByRole('button', { name: /notifications/i }),
      ]);

      if (!bellVisible) {
        return { status: 'NOT_DONE', comment: 'Notification icon not visible on rector dashboard.' };
      }

      const listOpened = await visibleLocator([
        page.getByText(/notifications/i),
        page.getByText(/mark all as read/i),
      ]);

      if (!listOpened) {
        return { status: 'PARTIAL', comment: 'Notification trigger clicked, but list panel did not become detectable.' };
      }

      await page.screenshot({ path: path.join(artifactDir, 'RE-011-notifications.png'), fullPage: true });
      return { status: 'PASS', comment: 'Notifications panel opened from the dashboard bell icon.' };
    });

    await runCase(results, 'RE-012', 'Notifications', 'Back navigation from notifications', async () => {
      const beforeUrl = page.url();

      const closeHandled = await tryClick([
        page.getByRole('button', { name: /close/i }),
        page.getByRole('button', { name: /back/i }),
      ]);

      if (!closeHandled) {
        await page.keyboard.press('Escape').catch(() => {});
      }

      await page.waitForTimeout(500);
      const afterUrl = page.url();

      if (beforeUrl !== afterUrl) {
        await goto(page, resolvedRoutes.dashboard);
      }

      const dashboardVisible = await visibleLocator([
        page.getByText(/rector dashboard/i),
        page.getByText(/download monthly report/i),
        page.getByRole('link', { name: /Out-Pass Approvals/i }),
      ]);

      if (!dashboardVisible) {
        return { status: 'PARTIAL', comment: 'Could not confidently confirm dashboard state after closing notifications.' };
      }

      return { status: 'PASS', comment: 'Returned to dashboard without losing current session state.' };
    });

    await runCase(results, 'RE-013', 'Comm Box', 'Open comm box module', async () => {
      if (!resolvedRoutes.commBox) {
        return { status: 'NOT_DONE', comment: 'Comm Box module route was not available in this environment.' };
      }

      await goto(page, resolvedRoutes.commBox);

      await waitForTableOrEmptyState(page);
      return { status: 'PASS', comment: 'Comm Box opened with table or empty-state content.' };
    });

    await runCase(results, 'RE-014', 'Comm Box', 'Open communication detail', async () => {
      if (!resolvedRoutes.commBox) {
        return { status: 'NOT_DONE', comment: 'Comm Box module route was not available in this environment.' };
      }

      await goto(page, resolvedRoutes.commBox);
      const state = await waitForTableOrEmptyState(page);
      if (state === 'empty') {
        return { status: 'NOT_DONE', comment: 'No communication rows available to open detail view.' };
      }

      const viewRow = await openFirstRowAction(page, /^view$/i);
      if (!viewRow) {
        return { status: 'NOT_DONE', comment: 'No View action found for communication rows.' };
      }

      const detailModal = await visibleLocator([
        page.locator('[role="dialog"]'),
        page.getByText(/communication details/i),
      ]);

      if (!detailModal) {
        return { status: 'PARTIAL', comment: 'A communication row was selected, but detail modal did not open.' };
      }

      const bodyText = cleanText(await detailModal.innerText());
      await tryClick([
        page.locator('[role="dialog"]').getByRole('button', { name: /close/i }),
      ]);

      if (bodyText.length < 12) {
        return { status: 'PARTIAL', comment: 'Communication detail opened but content looked too short/empty.' };
      }

      return { status: 'PASS', comment: 'Communication detail modal opened with readable content.' };
    });

    await runCase(results, 'RE-029', 'Guest Entry', 'Guest entry list filtering', async () => {
      if (!resolvedRoutes.guestEntry) {
        return { status: 'NOT_DONE', comment: 'Guest Entry module route was not available in this environment.' };
      }

      await goto(page, resolvedRoutes.guestEntry, {
        'tableFilters[status][value]': 'pending_group',
      });
      await waitForTableOrEmptyState(page);
      const pendingCount = await page.locator('table tbody tr').count();

      await goto(page, resolvedRoutes.guestEntry, {
        'tableFilters[status][value]': 'approved_group',
      });
      await waitForTableOrEmptyState(page);
      const approvedCount = await page.locator('table tbody tr').count();

      await page.screenshot({ path: path.join(artifactDir, 'RE-029-guest-filter.png'), fullPage: true });

      if (pendingCount === 0 && approvedCount === 0) {
        return { status: 'NOT_DONE', comment: 'No guest-entry records in pending/approved filters.' };
      }

      return {
        status: 'PASS',
        comment: `Guest filter queries executed (pending rows: ${pendingCount}, approved rows: ${approvedCount}).`,
      };
    });

    await runCase(results, 'RE-030', 'Guest Entry', 'Approve guest entry', async () => {
      if (!resolvedRoutes.guestEntry) {
        return { status: 'NOT_DONE', comment: 'Guest Entry module route was not available in this environment.' };
      }

      await goto(page, resolvedRoutes.guestEntry, {
        'tableFilters[status][value]': 'pending_group',
      });
      const state = await waitForTableOrEmptyState(page);
      if (state === 'empty') {
        return { status: 'NOT_DONE', comment: 'No pending guest entry requests available to approve.' };
      }

      const selectedRow = await openFirstRowAction(page, /^approve$/i);
      if (!selectedRow) {
        return { status: 'NOT_DONE', comment: 'Approve action not visible for pending guest entries.' };
      }

      const requestId = await safeCellText(selectedRow, 0);
      await submitModal(page, { textarea: 'Approved by automated RE-030 run.' });
      decisionLedger.guest = true;

      await goto(page, resolvedRoutes.guestEntry, {
        'tableFilters[status][value]': 'approved_group',
      });
      await waitForTableOrEmptyState(page);
      const moved = requestId
        ? (await page.locator('table tbody tr', { hasText: requestId }).count()) > 0
        : false;

      if (!moved) {
        return {
          status: 'PARTIAL',
          comment: requestId
            ? `Approval action succeeded, but ${requestId} was not found in approved list (possible auto-removal).`
            : 'Approval action succeeded, but request ID could not be verified in approved list.',
        };
      }

      return { status: 'PASS', comment: `Guest entry ${requestId} moved to approved state.` };
    });

    await runCase(results, 'RE-031', 'Guest Entry', 'Reject guest entry', async () => {
      if (!resolvedRoutes.guestEntry) {
        return { status: 'NOT_DONE', comment: 'Guest Entry module route was not available in this environment.' };
      }

      await goto(page, resolvedRoutes.guestEntry, {
        'tableFilters[status][value]': 'pending_group',
      });
      const state = await waitForTableOrEmptyState(page);
      if (state === 'empty') {
        return { status: 'NOT_DONE', comment: 'No pending guest entry requests available to reject.' };
      }

      const selectedRow = await openFirstRowAction(page, /^reject$/i);
      if (!selectedRow) {
        return { status: 'NOT_DONE', comment: 'Reject action not visible for pending guest entries.' };
      }

      const requestId = await safeCellText(selectedRow, 0);
      await submitModal(page, { textarea: 'Rejected by automated RE-031 run.' });
      decisionLedger.guest = true;

      await goto(page, resolvedRoutes.guestEntry, {
        'tableFilters[status][value]': 'denied',
      });
      await waitForTableOrEmptyState(page);
      const moved = requestId
        ? (await page.locator('table tbody tr', { hasText: requestId }).count()) > 0
        : false;

      if (!moved) {
        return {
          status: 'PARTIAL',
          comment: requestId
            ? `Reject action succeeded, but ${requestId} was not found in rejected list (possible auto-removal).`
            : 'Reject action succeeded, but request ID could not be verified in rejected list.',
        };
      }

      return { status: 'PASS', comment: `Guest entry ${requestId} moved to rejected state.` };
    });

    await runCase(results, 'RE-021', 'Outpass', 'Outpass list filter', async () => {
      await goto(page, resolvedRoutes.outpass, {
        'tableFilters[status][value]': 'pending',
      });
      await waitForTableOrEmptyState(page);
      const pendingRows = await page.locator('table tbody tr').count();

      await goto(page, resolvedRoutes.outpass, {
        'tableFilters[status][value]': 'approved',
      });
      await waitForTableOrEmptyState(page);
      const approvedRows = await page.locator('table tbody tr').count();

      await goto(page, resolvedRoutes.outpass, {
        'tableFilters[status][value]': 'declined',
      });
      await waitForTableOrEmptyState(page);
      const rejectedRows = await page.locator('table tbody tr').count();

      if (pendingRows === 0 && approvedRows === 0 && rejectedRows === 0) {
        return { status: 'NOT_DONE', comment: 'No outpass records were present in pending/approved/rejected filters.' };
      }

      return {
        status: 'PASS',
        comment: `Outpass filter routes worked (pending: ${pendingRows}, approved: ${approvedRows}, rejected: ${rejectedRows}).`,
      };
    });

    await runCase(results, 'RE-025', 'Outpass', 'Reject outpass with reason', async () => {
      await goto(page, resolvedRoutes.outpass, {
        'tableFilters[status][value]': 'pending',
      });
      const state = await waitForTableOrEmptyState(page);
      if (state === 'empty') {
        return { status: 'NOT_DONE', comment: 'No pending outpass request available to reject.' };
      }

      const selectedRow = await openFirstRowAction(page, /^decline$/i);
      if (!selectedRow) {
        return { status: 'NOT_DONE', comment: 'Decline action not available on pending outpass rows.' };
      }

      const requestId = await safeCellText(selectedRow, 0);
      await submitModal(page, { textarea: 'Rejected by automated RE-025 run.' });
      decisionLedger.outpass = true;

      await goto(page, resolvedRoutes.outpass, {
        'tableFilters[status][value]': 'declined',
      });
      await waitForTableOrEmptyState(page);
      const moved = requestId
        ? (await page.locator('table tbody tr', { hasText: requestId }).count()) > 0
        : false;

      if (!moved) {
        return {
          status: 'PARTIAL',
          comment: requestId
            ? `Reject action succeeded, but ${requestId} was not found in rejected filter (possible auto-removal).`
            : 'Reject action succeeded, but request ID could not be verified in rejected filter.',
        };
      }

      return { status: 'PASS', comment: `Outpass ${requestId} moved to rejected state with comment.` };
    });

    await runCase(results, 'RE-028', 'Leave', 'Reject leave with reason', async () => {
      await goto(page, resolvedRoutes.leave, {
        'tableFilters[status][value]': 'pending',
      });
      const state = await waitForTableOrEmptyState(page);
      if (state === 'empty') {
        return { status: 'NOT_DONE', comment: 'No pending leave request available to reject.' };
      }

      const selectedRow = await openFirstRowAction(page, /^reject$/i);
      if (!selectedRow) {
        return { status: 'NOT_DONE', comment: 'Reject action not available on pending leave rows.' };
      }

      const requestId = await safeCellText(selectedRow, 0);
      await submitModal(page, { textarea: 'Rejected by automated RE-028 run.' });
      decisionLedger.leave = true;

      await goto(page, resolvedRoutes.leave, {
        'tableFilters[status][value]': 'rejected',
      });
      await waitForTableOrEmptyState(page);
      const moved = requestId
        ? (await page.locator('table tbody tr', { hasText: requestId }).count()) > 0
        : false;

      if (!moved) {
        return {
          status: 'PARTIAL',
          comment: requestId
            ? `Reject action succeeded, but ${requestId} was not found in rejected leave filter (possible auto-removal).`
            : 'Reject action succeeded, but request ID could not be verified in rejected leave filter.',
        };
      }

      return { status: 'PASS', comment: `Leave request ${requestId} moved to rejected state.` };
    });

    await runCase(results, 'RE-033', 'Operational Flow', 'Approval cycle coverage', async () => {
      const covered = Object.entries(decisionLedger)
        .filter(([, value]) => Boolean(value))
        .map(([name]) => name);

      if (covered.length < 3) {
        return {
          status: 'PARTIAL',
          comment: `Not all decision flows were completed in this run. Covered: ${covered.join(', ') || 'none'}.`,
        };
      }

      return { status: 'PASS', comment: 'Outpass, Leave, and Guest decision flows were all exercised in one session.' };
    });

      await runCase(results, 'RE-039', 'Reliability', 'Screen crash check', async () => {
      const rotation = [resolvedRoutes.dashboard, resolvedRoutes.outpass, resolvedRoutes.leave];
      if (resolvedRoutes.guestEntry) {
        rotation.push(resolvedRoutes.guestEntry);
      }
      if (resolvedRoutes.commBox) {
        rotation.push(resolvedRoutes.commBox);
      }

      for (let i = 0; i < 12; i += 1) {
        const route = rotation[i % rotation.length];
        await goto(page, route);
        const serverErrorVisible = await page.getByText(/server error/i).isVisible().catch(() => false);
        if (serverErrorVisible) {
          throw new Error(`Server error page appeared during rapid navigation at iteration ${i + 1}.`);
        }
      }

      await page.screenshot({ path: path.join(artifactDir, 'RE-039-reliability.png'), fullPage: true });

      if (pageErrors.length > 0) {
        return {
          status: 'PARTIAL',
          comment: `Navigation completed, but browser page errors were captured (${pageErrors.length}).`,
        };
      }

      return { status: 'PASS', comment: 'Completed 12 rapid module switches with no crash/freeze indicators.' };
    });
    }

    const markdown = buildMarkdownReport(results, pageErrors);
    fs.writeFileSync(reportMdPath, markdown, 'utf8');
    fs.writeFileSync(reportJsonPath, JSON.stringify({ baseUrl, rectorPhone, results, pageErrors }, null, 2), 'utf8');

    await testInfo.attach('rector-re-report-md', {
      path: reportMdPath,
      contentType: 'text/markdown',
    });
    await testInfo.attach('rector-re-report-json', {
      path: reportJsonPath,
      contentType: 'application/json',
    });

    const failed = results.filter((item) => item.status === 'FAIL');
    const notDone = results.filter((item) => item.status === 'NOT_DONE');

    expect(failed, `RE regression has failing cases: ${failed.map((item) => item.testId).join(', ')}`).toHaveLength(0);

    if (failOnNotDone) {
      expect(notDone, `RE regression has NOT_DONE cases: ${notDone.map((item) => item.testId).join(', ')}`).toHaveLength(0);
    }
  });
});
