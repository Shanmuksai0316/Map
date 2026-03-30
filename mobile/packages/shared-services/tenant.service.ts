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
      const cached = StorageService.getObject<{
        tenants: Tenant[];
        timestamp: number;
      }>(this.CACHE_KEY);

      if (cached && Date.now() - cached.timestamp < this.CACHE_EXPIRY_MS) {
        return cached.tenants;
      }

      // Fetch from API
      const response = await fetch(`${APP_CONFIG.CENTRAL_API_URL}/tenants`);
      if (!response.ok) {
        throw new Error('Failed to fetch tenants');
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
      StorageService.setObject(this.CACHE_KEY, {
        tenants,
        timestamp: Date.now()
      });

      return tenants;
    } catch (error) {
      console.error('Failed to fetch tenant list:', error);
      
      // Return cached data if available
      const cached = StorageService.getObject<{
        tenants: Tenant[];
        timestamp: number;
      }>(this.CACHE_KEY);
      
      return cached?.tenants || [];
    }
  }

  /**
   * Get currently selected tenant
   */
  getSelectedTenant(): Tenant | null {
    const tenant = StorageService.getObject<Tenant>(this.STORAGE_KEY);

    if (!tenant) {
      return null;
    }

    const normalized = {
        ...tenant,
        apiUrl: this.normalizeApiUrl(tenant),
    };

    StorageService.setObject(this.STORAGE_KEY, normalized);

    return normalized;
  }

  /**
   * Set the selected tenant
   */
  setSelectedTenant(tenant: Tenant): void {
    const normalized = {
      ...tenant,
      apiUrl: this.normalizeApiUrl(tenant),
    };
    StorageService.setObject(this.STORAGE_KEY, normalized);
  }

  /**
   * Clear selected tenant
   */
  clearSelectedTenant(): void {
    StorageService.delete(this.STORAGE_KEY);
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
   * Get tenant by ID from central API
   */
  async getTenantById(tenantId: string): Promise<Tenant | null> {
    try {
      // Try to find in cached list first
      const cached = StorageService.getObject<{
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

      // Fetch from API - try to get from tenant list endpoint
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
    if (tenant.apiUrl && !/localhost|10\.0\.2\.2/.test(tenant.apiUrl)) {
      let url = tenant.apiUrl.trim();
      url = url.replace('http://', 'https://');
      url = url.replace(':8000', '');
      url = url.replace('/api/v1', '/v1');
      if (!url.endsWith('/v1')) {
        url = url.endsWith('/') ? `${url}v1` : `${url}/v1`;
      }
      return url;
    }

    const domain = tenant.domain?.replace('http://', '').replace('https://', '') || '';
    if (domain) {
      return `${APP_CONFIG.API_PROTOCOL}://${domain}/v1`;
    }

    return `${APP_CONFIG.API_PROTOCOL}://api.${APP_CONFIG.API_BASE_DOMAIN}/v1`;
  }
}

export const tenantService = new TenantService();
