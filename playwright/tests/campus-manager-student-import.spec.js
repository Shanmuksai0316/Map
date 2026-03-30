const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const DEFAULT_BASE_URL = 'https://ppcu.mapservices.in';
const DEFAULT_PHONE = '7975452363';
const DEFAULT_OTP = '123456';

const baseUrl = (process.env.PW_CM_BASE_URL || DEFAULT_BASE_URL).replace(/\/$/, '');
const phone = process.env.PW_CM_PHONE || DEFAULT_PHONE;
const otp = process.env.PW_CM_OTP || DEFAULT_OTP;

const runStamp = new Date().toISOString().replace(/[:.]/g, '-');
const runDir = path.join(process.cwd(), 'output', 'playwright', `campus-manager-student-import-${runStamp}`);
const uploadDir = path.join(runDir, 'fixtures');
const reportPath = path.join(runDir, 'report.md');

function url(p) {
  return new URL(p.startsWith('/') ? p : `/${p}`, baseUrl).toString();
}

function clean(v) {
  return String(v || '')
    .replace(/\u001b\[[0-9;]*m/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

function extractImportId(href) {
  const m = String(href || '').match(/\/campus-manager\/import-jobs\/(\d+)/i);
  return m ? Number(m[1]) : 0;
}

async function visible(page, locators, timeout = 3000) {
  for (const locator of locators) {
    try {
      const first = locator.first();
      await first.waitFor({ state: 'visible', timeout });
      return first;
    } catch {
      // try next
    }
  }
  return null;
}

async function clickFirst(page, locators, timeout = 3000) {
  const target = await visible(page, locators, timeout);
  if (!target) return false;
  await target.click();
  return true;
}

function createStudentCsv() {
  fs.mkdirSync(uploadDir, { recursive: true });
  const uniq = Date.now();
  const fileName = `students-${uniq}.csv`;
  const filePath = path.join(uploadDir, fileName);

  const row = {
    full_name: `PW Student ${uniq}`,
    email_address: `pw.student.${uniq}@example.com`,
    mobile_number: `9${String(uniq).slice(-9)}`,
    gender: 'male',
    date_of_birth: '2004-05-17',
    map_id: `MAP-${uniq}`,
    erp_number: `ERP-${uniq}`,
    department: 'Computer Science',
    year_of_study: '2',
  };

  const headers = [
    'full_name',
    'email_address',
    'mobile_number',
    'gender',
    'date_of_birth',
    'map_id',
    'erp_number',
    'department',
    'year_of_study',
  ];

  const csv = [
    headers.join(','),
    headers.map((h) => row[h]).join(','),
  ].join('\n');

  fs.writeFileSync(filePath, csv, 'utf8');
  return { filePath, fileName, row };
}

async function loginCampusManager(page) {
  for (let attempt = 1; attempt <= 2; attempt += 1) {
    await page.goto(url('/campus-manager/login'), { waitUntil: 'domcontentloaded' });

    const phoneInput = await visible(page, [
      page.getByLabel(/phone number/i),
      page.locator('input[type="tel"]'),
      page.locator('input[name*="phone" i]'),
    ], 10000);

    if (!phoneInput) {
      throw new Error('Campus Manager login phone input not found.');
    }

    await phoneInput.fill(phone);

    const bypassButton = await visible(page, [
      page.getByRole('button', { name: /login with bypass code/i }),
    ], 1500);

    if (bypassButton) {
      const otpInput = await visible(page, [
        page.getByLabel(/enter otp/i),
        page.getByLabel(/otp/i),
        page.locator('input[name*="otp" i]'),
      ], 8000);
      if (!otpInput) throw new Error('Bypass OTP input was not visible.');
      await otpInput.fill(otp);
      await page.locator('form').first().evaluate((form) => form.requestSubmit());
    } else {
      const sent = await clickFirst(page, [
        page.getByRole('button', { name: /send otp/i }),
      ], 4000);
      if (!sent) throw new Error('Send OTP button not found.');

      const otpInput = await visible(page, [
        page.getByLabel(/enter otp/i),
        page.getByLabel(/otp/i),
        page.locator('input[name*="otp" i]'),
      ], 15000);
      if (!otpInput) throw new Error('OTP input not visible after Send OTP.');
      await otpInput.fill(otp);
      await page.locator('form').first().evaluate((form) => form.requestSubmit());
    }

    // Some environments complete auth session but fail to redirect from login.
    // Verify session by opening a protected Campus Manager page directly.
    await page.waitForTimeout(1200);
    await page.goto(url('/campus-manager/import-jobs/start-import'), { waitUntil: 'domcontentloaded' }).catch(() => {});
    const runImportVisible = await page.getByRole('button', { name: /run import/i }).isVisible().catch(() => false);
    if (runImportVisible) {
      return;
    }

    const expired = await page.getByText(/page expired/i).isVisible().catch(() => false);
    if (attempt === 2) {
      throw new Error(expired ? 'Login failed with 419 Page Expired.' : 'Campus Manager session was not established after OTP submit.');
    }

    await page.waitForTimeout(1500);
  }
}

async function uploadAndVerify(page, csvInfo) {
  const sortedImportList = '/campus-manager/import-jobs?tableSortColumn=created_at&tableSortDirection=desc';
  await page.goto(url(sortedImportList), { waitUntil: 'domcontentloaded' });
  const baselineHref = await page.locator('table tbody tr').first().getByRole('link', { name: /view/i }).first().getAttribute('href').catch(() => '');
  const baselineImportId = extractImportId(baselineHref);

  await page.goto(url('/campus-manager/import-jobs/start-import'), { waitUntil: 'domcontentloaded' });

  const runImportBtn = await visible(page, [
    page.getByRole('button', { name: /run import/i }),
  ], 10000);
  if (!runImportBtn) throw new Error('Run Import button not found on start-import page.');

  const kindSelect = await visible(page, [
    page.getByLabel(/import type/i),
    page.locator('select[name*="kind" i]'),
  ], 3000);
  if (kindSelect) {
    await kindSelect.selectOption('students').catch(() => {});
  }

  const fileInput = await visible(page, [
    page.locator('input[type="file"]'),
  ], 15000);
  if (!fileInput) throw new Error('File upload input not found.');

  await fileInput.setInputFiles(csvInfo.filePath);
  await page.getByText(/upload complete/i).first().waitFor({ state: 'visible', timeout: 30000 }).catch(() => {});
  await expect(runImportBtn).toBeEnabled({ timeout: 30000 });

  let submitted = false;
  for (let i = 0; i < 3; i += 1) {
    await runImportBtn.click();
    await page.waitForTimeout(2500);
    const stillOnStart = /\/campus-manager\/import-jobs\/start-import/.test(page.url());
    if (!stillOnStart) {
      submitted = true;
      break;
    }
  }
  if (!submitted) {
    const warning = clean(await page.getByText(/please wait for upload to finish/i).first().innerText().catch(() => ''));
    throw new Error(warning || 'Run Import did not submit from start-import page.');
  }

  let finalStatus = '';
  let inserted = '';
  let detailsUrl = '';
  let latestImportId = baselineImportId;

  const start = Date.now();
  while (Date.now() - start < 240000) {
    await page.goto(url(sortedImportList), { waitUntil: 'domcontentloaded' });

    const latestHref = await page.locator('table tbody tr').first().getByRole('link', { name: /view/i }).first().getAttribute('href').catch(() => '');
    latestImportId = extractImportId(latestHref);

    if (latestImportId <= baselineImportId || !latestHref) {
      await page.waitForTimeout(4000);
      continue;
    }

    await page.goto(latestHref, { waitUntil: 'domcontentloaded' });
    detailsUrl = page.url();

    finalStatus = clean(await page.getByLabel(/^status$/i).first().inputValue({ timeout: 1500 }).catch(() => ''));
    inserted = clean(await page.getByLabel(/inserted rows/i).first().inputValue({ timeout: 1500 }).catch(() => ''));
    const processedRowsRaw = clean(await page.getByLabel(/processed rows/i).first().inputValue({ timeout: 1500 }).catch(() => '0'));
    const errorRowsRaw = clean(await page.getByLabel(/error rows/i).first().inputValue({ timeout: 1500 }).catch(() => '0'));
    const insertedRows = Number((inserted.match(/\d+/) || ['0'])[0]);
    const processedRows = Number((processedRowsRaw.match(/\d+/) || ['0'])[0]);
    const errorRows = Number((errorRowsRaw.match(/\d+/) || ['0'])[0]);

    if (processedRows >= 1 && insertedRows >= 1 && errorRows === 0) {
      finalStatus = finalStatus || 'Completed';
      return { finalStatus, inserted, detailsUrl };
    }

    if (/failed/i.test(finalStatus) || /dryrunerrors/i.test(finalStatus) || errorRows > 0) {
      const possibleErr = clean(await page.getByText(/error/i).first().innerText().catch(() => 'Import failed.'));
      throw new Error(`Import ended in ${finalStatus}. ${possibleErr}`);
    }

    await page.waitForTimeout(5000);
  }

  throw new Error(`Import did not reach Completed within timeout. Baseline ID: ${baselineImportId}, latest ID: ${latestImportId}, last status: ${finalStatus || 'unknown'}. Details: ${detailsUrl || '-'}`);
}

test.describe.serial('Campus Manager bulk student upload', () => {
  test('uploads students CSV and verifies import completion', async ({ page }, testInfo) => {
    test.setTimeout(360000);
    fs.mkdirSync(runDir, { recursive: true });
    const csvInfo = createStudentCsv();

    const summary = {
      baseUrl,
      loginPhone: phone,
      csvFile: csvInfo.fileName,
      csvRow: csvInfo.row,
      status: 'UNKNOWN',
      detailsUrl: '',
      insertedRows: '',
      error: '',
    };

    try {
      await loginCampusManager(page);
      const result = await uploadAndVerify(page, csvInfo);
      summary.status = result.finalStatus;
      summary.detailsUrl = result.detailsUrl;
      summary.insertedRows = result.inserted;

      await page.screenshot({ path: path.join(runDir, 'import-success.png'), fullPage: true });
      expect(/completed/i.test(summary.status)).toBeTruthy();
      if (summary.insertedRows) {
        const match = summary.insertedRows.match(/\d+/);
        if (match) {
          expect(Number(match[0])).toBeGreaterThanOrEqual(1);
        }
      }
    } catch (err) {
      summary.status = 'FAILED';
      summary.error = clean(err.message || String(err));
      await page.screenshot({ path: path.join(runDir, 'import-failure.png'), fullPage: true }).catch(() => {});
      throw err;
    } finally {
      const report = [
        '# Campus Manager Student Upload Report',
        `- Generated: ${new Date().toISOString()}`,
        `- Base URL: ${baseUrl}`,
        `- Phone: ${phone}`,
        `- CSV File: ${summary.csvFile}`,
        `- Final Status: ${summary.status}`,
        `- Inserted Rows: ${summary.insertedRows || '-'}`,
        `- Details URL: ${summary.detailsUrl || '-'}`,
        `- Error: ${summary.error || '-'}`,
      ].join('\n');
      fs.writeFileSync(reportPath, report, 'utf8');
      await testInfo.attach('campus-manager-student-upload-report', {
        path: reportPath,
        contentType: 'text/markdown',
      });
    }
  });
});
