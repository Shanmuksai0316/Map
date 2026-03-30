import { StorageService } from './storage.service';
import { APP_CONFIG } from '../config/app.config';

export interface Tenant {
  id: string;
  code: string;
  name: string;
  domain: string;
  apiUrl: string;
}

export interface TenantListResponse {
  tenants: Tenant[];
}

class TenantService {
  private readonly STORAGE_KEY = 'selected_tenant';
  private readonly CACHE_KEY = 'tenant_list';
  private readonly CACHE_EXPIRY_MS = 5 * 60 * 1000; // 5 minutes

  /**
   * Get list of available tenants
   */
  async getTenantList(): Promise<Tenant[]> {
    try {
      // Check cache first
      const cached = await StorageService.getObject<{
        tenants: Tenant[];
        timestamp: number;
      }>(this.CACHE_KEY);

      if (cached && Date.now() - cached.timestamp < this.CACHE_EXPIRY_MS) {
        return cached.tenants;
      }

      const tenantListUrl = `${APP_CONFIG.CENTRAL_API_URL}/tenants`;
      const response = await fetch(tenantListUrl);
      if (!response.ok) {
        let bodyPreview = '';
        try { bodyPreview = await response.text(); } catch (_) {}
        const statusMsg = ` (HTTP ${response.status}${response.statusText ? ` ${response.statusText}` : ''})`;
        console.error('[TenantService] Tenant list request failed:', response.status, response.statusText, bodyPreview?.slice?.(0,150));
        throw new Error(`Failed to fetch tenants${statusMsg}`);
      }

      const json = await response.json();
      const tenantPayload: TenantListResponse | undefined = json?.tenants
        ? json
        : json?.data?.tenants
          ? json.data
          : undefined;

      if (!tenantPayload?.tenants) {
        throw new Error('Malformed tenant response');
      }

      // Use server-provided apiUrl verbatim instead of overwriting it
      const tenants = tenantPayload.tenants.map(tenant => {
        const normalized = {
          ...tenant,
          apiUrl: this.normalizeApiUrl(tenant)
        };
        return normalized;
      });

      // Cache the result
      await StorageService.setObject(this.CACHE_KEY, {
        tenants,
        timestamp: Date.now()
      });

      return tenants;
    } catch (error) {
      console.error('Failed to fetch tenant list:', error);
      
      // Return cached data if available
      const cached = await StorageService.getObject<{
        tenants: Tenant[];
        timestamp: number;
      }>(this.CACHE_KEY);
      
      return cached?.tenants || [];
    }
  }

  /**
   * Get currently selected tenant (synchronous - from cache)
   */
  getSelectedTenant(): Tenant | null {
    const tenant = StorageService.getObjectSync<Tenant>(this.STORAGE_KEY);

    if (!tenant) {
      return null;
    }

    const normalized = {
        ...tenant,
        apiUrl: this.normalizeApiUrl(tenant),
    };

    // Update cache asynchronously (fire and forget)
    StorageService.setObject(this.STORAGE_KEY, normalized).catch((error) => {
      console.error('[TenantService] Error updating tenant cache:', error);
    });

    return normalized;
  }

  /**
   * Set the selected tenant
   */
  async setSelectedTenant(tenant: Tenant): Promise<void> {
    const normalized = {
      ...tenant,
      apiUrl: this.normalizeApiUrl(tenant),
    };
    await StorageService.setObject(this.STORAGE_KEY, normalized);
  }

  /**
   * Clear selected tenant
   */
  async clearSelectedTenant(): Promise<void> {
    await StorageService.delete(this.STORAGE_KEY);
  }

  /**
   * Get API base URL for current tenant
   */
  getCurrentApiUrl(): string {
    const tenant = this.getSelectedTenant();
    return tenant?.apiUrl || APP_CONFIG.CENTRAL_API_URL;
  }

  /**
   * Check if tenant is selected
   */
  hasSelectedTenant(): boolean {
    return this.getSelectedTenant() !== null;
  }

  /**
   * Get tenant by code
   */
  async getTenantByCode(code: string): Promise<Tenant | null> {
    const tenants = await this.getTenantList();
    return tenants.find(tenant => tenant.code === code) || null;
  }

  /**
   * Get tenant by ID from 'central API'
   */
  async getTenantById(tenantId: string): Promise<Tenant | null> {
    try {
      // Try to find in cached list first
      const cached = await StorageService.getObject<{
        tenants: Tenant[];
        timestamp: number;
      }>(this.CACHE_KEY);
      
      if (cached?.tenants) {
        const found = cached.tenants.find(t => t.id === tenantId);
        if (found) {
          return {
            ...found,
            apiUrl: this.normalizeApiUrl(found),
          };
        }
      }

      // Fetch from 'API - try to get from tenant list endpoint'
      const response = await fetch(`${APP_CONFIG.CENTRAL_API_URL}/tenants`);
      if (response.ok) {
        const json = await response.json();
        const tenantPayload: TenantListResponse | undefined = json?.tenants
          ? json
          : json?.data?.tenants
            ? json.data
            : undefined;

        if (tenantPayload?.tenants) {
          const found = tenantPayload.tenants.find(t => t.id === tenantId);
          if (found) {
            const normalized = {
              ...found,
              apiUrl: this.normalizeApiUrl(found),
            };
            return normalized;
          }
        }
      }

      // If not found in list, try direct fetch (if backend supports it)
      // For now, return null and let caller handle
      return null;
    } catch (error) {
      console.error('Failed to fetch tenant by ID:', error);
      return null;
    }
  }

  private normalizeApiUrl(tenant: Tenant): string {
    const rawApiUrl = (tenant.apiUrl || (tenant as any)?.api_url || '').trim();

    if (rawApiUrl && !/localhost|10\.0\.2\.2/.test(rawApiUrl)) {
      let url = rawApiUrl.replace(/^http:\/\//i, 'https://').replace(':8000', '');
      // Guard against bad hostnames like "ppcu.mapservices.in.mapservices.in"
      url = url.replace(/\.mapservices\.in\.mapservices\.in\b/gi, '.mapservices.in');
      // Prefer tenant domain for staff workflows when the API URL points to the central host.
      const normalizedDomain = tenant.domain
        ?.replace(/^https?:\/\//i, '')
        .replace(/\/+$/, '')
        .replace(/\.mapservices\.in\.mapservices\.in\b/gi, '.mapservices.in');
      if (normalizedDomain && /api\.mapservices\.in/i.test(url)) {
        return `${APP_CONFIG.API_PROTOCOL}://${normalizedDomain}/api/v1`;
      }
      // Some backends return /v1 but routes are actually under /api/v1
      // Normalize to /api/v1 to avoid hitting the wrong base path.
      if (/\/v1\/?$/.test(url) && !/\/api\/v1\/?$/.test(url)) {
        url = url.replace(/\/v1\/?$/, '/api/v1');
      }
      if (!url.endsWith('/v1') && !url.endsWith('/api/v1')) {
        url = url.endsWith('/') ? `${url}api/v1` : `${url}/api/v1`;
      }
      return url;
    }

    let domain = tenant.domain?.replace(/^https?:\/\//i, '').replace(/\/+$/, '') || '';
    domain = domain.replace(/\.mapservices\.in\.mapservices\.in\b/gi, '.mapservices.in');
    if (domain) {
      // Backend routes are at /api/v1, so ensure baseURL includes /api/v1
      return `${APP_CONFIG.API_PROTOCOL}://${domain}/api/v1`;
    }

    return APP_CONFIG.CENTRAL_API_URL;
  }
}

export const tenantService = new TenantService();
