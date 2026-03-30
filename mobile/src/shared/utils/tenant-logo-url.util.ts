import { Platform } from 'react-native';
import { APP_CONFIG } from '../config/app.config';
import { tenantService } from '../services/tenant.service';

type UnknownRecord = Record<string, unknown>;

const LOCAL_HOSTS = new Set(['localhost', '127.0.0.1', '10.0.2.2']);

const isRecord = (value: unknown): value is UnknownRecord =>
  value !== null && typeof value === 'object';

const firstString = (...values: unknown[]): string | null => {
  for (const value of values) {
    if (typeof value === 'string' && value.trim().length > 0) {
      return value.trim();
    }
  }
  return null;
};

const getBaseOrigin = (): string => {
  const currentApiUrl = tenantService.getCurrentApiUrl() || APP_CONFIG.CENTRAL_API_URL;
  try {
    return new URL(currentApiUrl).origin;
  } catch {
    return APP_CONFIG.CENTRAL_API_URL.replace(/\/api\/v1\/?$/, '');
  }
};

const normalizeAbsoluteUrl = (rawUrl: string): string => {
  try {
    const url = new URL(rawUrl);
    const host = url.hostname.toLowerCase();
    const isLocalHost = LOCAL_HOSTS.has(host);

    // iOS ATS blocks non-local HTTP by default; force HTTPS for remote hosts.
    if (url.protocol === 'http:' && !isLocalHost) {
      url.protocol = 'https:';
    }

    // Android emulator cannot reach host machine via localhost.
    if (Platform.OS === 'android' && host === 'localhost') {
      url.hostname = '10.0.2.2';
    }

    return url.toString();
  } catch {
    return rawUrl;
  }
};

export const resolveTenantLogoUrl = (input?: string | null): string | null => {
  const raw = input?.trim();
  if (!raw) {
    return null;
  }

  if (/^(data:|file:|content:|ph:)/i.test(raw)) {
    return raw;
  }

  if (/^\/\//.test(raw)) {
    return normalizeAbsoluteUrl(`https:${raw}`);
  }

  if (/^https?:\/\//i.test(raw)) {
    return normalizeAbsoluteUrl(raw);
  }

  const origin = getBaseOrigin().replace(/\/+$/, '');
  const normalizedPath = raw.replace(/^\.?\//, '');
  return normalizeAbsoluteUrl(`${origin}/${normalizedPath}`);
};

const unwrapProfilePayload = (payload: unknown): UnknownRecord | null => {
  if (!isRecord(payload)) {
    return null;
  }

  let current: unknown = payload;
  for (let depth = 0; depth < 2; depth += 1) {
    if (!isRecord(current) || !('data' in current)) {
      break;
    }

    const next = (current as UnknownRecord).data;
    if (!isRecord(next)) {
      break;
    }

    current = next;
  }

  return isRecord(current) ? current : null;
};

export const extractTenantLogoUrl = (payload: unknown): string | null => {
  const profile = unwrapProfilePayload(payload);
  if (!profile) {
    return null;
  }

  const tenant = isRecord(profile.tenant) ? profile.tenant : null;
  const tenantSettings = tenant && isRecord(tenant.settings)
    ? (tenant.settings as UnknownRecord)
    : null;
  const tenantBranding = tenantSettings && isRecord(tenantSettings.branding)
    ? (tenantSettings.branding as UnknownRecord)
    : null;
  const rawLogoUrl = firstString(
    profile.tenant_logo_url,
    profile.tenant_logo,
    profile.logo_url,
    profile.logo,
    tenant?.logo_url,
    tenant?.logoUrl,
    tenant?.logo
  );

  if (rawLogoUrl) {
    return resolveTenantLogoUrl(rawLogoUrl);
  }

  // Some payloads expose only storage path fields instead of full URL.
  const rawLogoPath = firstString(
    profile.tenant_logo_path,
    profile.logo_path,
    tenant?.logo_path,
    tenantBranding?.logo_path
  );

  if (!rawLogoPath) {
    return null;
  }

  const normalizedPath = rawLogoPath.replace(/^\/+/, '');
  const publicPath = normalizedPath.startsWith('storage/')
    ? normalizedPath
    : `storage/${normalizedPath}`;

  return resolveTenantLogoUrl(`/${publicPath}`);
};
