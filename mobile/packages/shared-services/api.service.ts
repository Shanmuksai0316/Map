import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';
import { APP_CONFIG } from '../config/app.config';
import { StorageService } from './storage.service';
import { tenantService } from './tenant.service';

class ApiService {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: tenantService.getCurrentApiUrl(),
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    // Request interceptor to add auth token and tenant code
    this.client.interceptors.request.use(
      (config) => {
        const token = StorageService.get(APP_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
        if (token && config.headers) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        
        // Add tenant code header
        const selectedTenant = tenantService.getSelectedTenant();
        if (selectedTenant && config.headers) {
          config.headers['X-Tenant-Code'] = selectedTenant.code;
        }

        // Always resolve latest base URL from tenant service
        config.baseURL = tenantService.getCurrentApiUrl();

        if (__DEV__) {
          // eslint-disable-next-line no-console
          console.log('[API] Request', config.method?.toUpperCase(), config.baseURL, config.url, {
            tenant: selectedTenant?.code,
            hasToken: Boolean(token),
          });
        }
        
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error) => {
        if (error.response?.status === 401) {
          // Token expired or invalid - clear storage and redirect to login
          StorageService.clear();
        }
        return Promise.reject(error);
      }
    );
  }

  async get<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.get(url, config);
    return response.data;
  }

  async post<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.post(url, data, config);
    return response.data;
  }

  async put<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.put(url, data, config);
    return response.data;
  }

  async patch<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.patch(url, data, config);
    return response.data;
  }

  async delete<T>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response: AxiosResponse<T> = await this.client.delete(url, config);
    return response.data;
  }

  /**
   * Update the base URL when tenant changes
   */
  updateBaseUrl(): void {
    this.client.defaults.baseURL = tenantService.getCurrentApiUrl();
  }
}

export const apiService = new ApiService();

