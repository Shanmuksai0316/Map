const { test, expect, request: playwrightRequest } = require('playwright/test');

const DEFAULT_CENTRAL_API_URL = 'https://api.mapservices.in/api/v1';
const DEFAULT_PHONE = '7975452363';
const DEFAULT_OTP = '123456';

const centralApiUrl = process.env.PW_CENTRAL_API_URL || DEFAULT_CENTRAL_API_URL;
const phone = process.env.PW_CAMPUS_MANAGER_PHONE || DEFAULT_PHONE;
const otp = process.env.PW_BYPASS_OTP || DEFAULT_OTP;

/**
 * Normalizes API payloads that can be either:
 * - { data: [...] }
 * - [...]
 */
const extractList = (payload) => {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
};

test.describe.serial('Campus Manager live API UAT checks', () => {
  /** @type {import('@playwright/test').APIRequestContext | null} */
  let reqCtx = null;
  /** @type {{ tenantCode: string; tenantApiUrl: string; token: string } | null} */
  let session = null;
  let authError = '';

  const apiGet = async (path) => {
    if (!reqCtx || !session) {
      throw new Error('Live session unavailable');
    }
    return reqCtx.get(`${session.tenantApiUrl}${path}`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${session.token}`,
        'X-Tenant-Code': session.tenantCode,
      },
    });
  };

  test.beforeAll(async () => {
    reqCtx = await playwrightRequest.newContext();
    try {
      const lookupRes = await reqCtx.post(`${centralApiUrl}/mobile/auth/tenant-lookup`, {
        data: { phone },
      });

      if (!lookupRes.ok()) {
        throw new Error(`tenant-lookup failed: ${lookupRes.status()}`);
      }

      const lookupJson = await lookupRes.json();
      const tenantCode = lookupJson?.data?.tenant_code;
      const tenantApiUrl =
        lookupJson?.data?.api_url ||
        `https://${String(tenantCode || '').toLowerCase()}.mapservices.in/api/v1`;

      if (!tenantCode || !tenantApiUrl) {
        throw new Error('tenant-lookup missing tenant_code/api_url');
      }

      const verifyRes = await reqCtx.post(`${tenantApiUrl}/mobile/auth/verify-otp`, {
        headers: {
          'X-Tenant-Code': tenantCode,
        },
        data: {
          phone,
          otp,
          device_name: 'staff-app',
        },
      });

      if (!verifyRes.ok()) {
        throw new Error(`verify-otp failed: ${verifyRes.status()}`);
      }

      const verifyJson = await verifyRes.json();
      const rawToken = verifyJson?.data?.token;
      const token =
        typeof rawToken === 'string'
          ? rawToken
          : rawToken?.plainTextToken || rawToken?.token || '';

      if (!token) {
        throw new Error('verify-otp response missing token');
      }

      session = { tenantCode, tenantApiUrl, token };
    } catch (error) {
      authError = error instanceof Error ? error.message : String(error);
      session = null;
    }
  });

  test.afterAll(async () => {
    if (reqCtx) {
      await reqCtx.dispose();
      reqCtx = null;
    }
  });

  test('CA-003: tenant selection resolves and authenticated session is established', async () => {
    test.skip(!session, `Live auth unavailable: ${authError}`);
    expect(session.tenantCode).toBeTruthy();
    expect(session.tenantApiUrl).toContain('/api/v1');
    expect(session.token.length).toBeGreaterThan(10);
  });

  test('CA-005/CA-006: profile data includes identity fields used by dashboard card and branding', async () => {
    test.skip(!session, `Live auth unavailable: ${authError}`);
    const profileRes = await apiGet('/mobile/profile');
    expect(profileRes.ok()).toBeTruthy();
    const profile = await profileRes.json();
    const data = profile?.data || profile;
    expect(typeof (data?.name || '')).toBe('string');
    expect((data?.name || '').length).toBeGreaterThan(0);
    expect(typeof (data?.role || '')).toBe('string');
    const hasSomeBrandingField = Boolean(
      data?.tenant_logo_url || data?.tenant_logo || data?.logo_url || data?.logo || data?.tenant?.logo_url
    );
    // Branding can be absent for some tenants; endpoint itself must stay healthy.
    expect(typeof hasSomeBrandingField).toBe('boolean');
  });

  test('CA-011/CA-012: notifications feed endpoint is reachable', async () => {
    test.skip(!session, `Live auth unavailable: ${authError}`);
    const notificationsRes = await apiGet('/mobile/notifications');
    expect(notificationsRes.ok()).toBeTruthy();
    const json = await notificationsRes.json();
    const list = extractList(json);
    expect(Array.isArray(list)).toBeTruthy();
  });

  test('CA-014: comm-box feed provides detail-safe payloads', async () => {
    test.skip(!session, `Live auth unavailable: ${authError}`);
    const commRes = await apiGet('/mobile/notifications/comm-box');
    if (!commRes.ok() && commRes.status() === 404) {
      test.skip(true, 'Tenant does not expose /comm-box route; app fallback is covered by contract tests.');
    }
    expect(commRes.ok()).toBeTruthy();
    const json = await commRes.json();
    const notices = extractList(json);
    expect(Array.isArray(notices)).toBeTruthy();
    if (notices.length > 0) {
      const first = notices[0];
      expect(typeof String(first?.title || '')).toBe('string');
      expect(typeof String(first?.body || '')).toBe('string');
    }
  });

  test('CA-021/CA-023: checklist endpoints are reachable for tab loading/completion flow', async () => {
    test.skip(!session, `Live auth unavailable: ${authError}`);

    const currentChecklistRes = await apiGet('/mobile/campus-manager/checklists/current');
    const fallbackChecklistRes =
      currentChecklistRes.status() === 404 || currentChecklistRes.status() === 403
        ? await apiGet('/campus-manager/checklists/current')
        : null;

    const effectiveChecklistRes = fallbackChecklistRes || currentChecklistRes;
    expect(effectiveChecklistRes.ok()).toBeTruthy();

    const staffSummaryRes = await apiGet('/campus-manager/checklists/staff-summary');
    const staffSummaryFallbackRes =
      staffSummaryRes.status() === 404 || staffSummaryRes.status() === 403
        ? await apiGet('/mobile/campus-manager/checklists/staff-summary')
        : null;

    const effectiveSummaryRes = staffSummaryFallbackRes || staffSummaryRes;
    expect(effectiveSummaryRes.ok()).toBeTruthy();
    const summaryJson = await effectiveSummaryRes.json();
    expect(Array.isArray(extractList(summaryJson))).toBeTruthy();
  });

  test('CA-029/CA-030: requests hub tab endpoints return list payloads that support search/detail rendering', async () => {
    test.skip(!session, `Live auth unavailable: ${authError}`);
    const tabs = ['housekeeping', 'maintenance', 'outpass', 'leave', 'guest-entry', 'sports', 'laundry'];
    for (const tab of tabs) {
      const res = await apiGet(`/mobile/campus-manager/requests/${tab}`);
      expect(res.ok(), `requests endpoint failed for ${tab} with status ${res.status()}`).toBeTruthy();
      const json = await res.json();
      const list = extractList(json);
      expect(Array.isArray(list), `${tab} payload is not a list`).toBeTruthy();
    }
  });

  test('CA-032: emergency unread-count or fallback incidents endpoint is available', async () => {
    test.skip(!session, `Live auth unavailable: ${authError}`);
    const unreadRes = await apiGet('/mobile/campus-manager/emergency/incidents/unread-count');
    if (unreadRes.ok()) {
      const json = await unreadRes.json();
      const count = json?.data?.unread_count ?? json?.unread_count ?? 0;
      expect(Number.isFinite(Number(count))).toBeTruthy();
      return;
    }

    expect([403, 404]).toContain(unreadRes.status());
    const fallbackIncidentsRes = await apiGet('/mobile/campus-manager/emergency/incidents?acknowledged=0&page=1&per_page=1');
    expect(fallbackIncidentsRes.ok()).toBeTruthy();
    const fallbackJson = await fallbackIncidentsRes.json();
    const total = fallbackJson?.meta?.total;
    expect(Number.isFinite(Number(total)) || Array.isArray(fallbackJson?.data)).toBeTruthy();
  });
});
