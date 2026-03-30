import type { AxiosRequestConfig } from 'axios';
import { apiService } from '../shared/services/api.service';

/**
 * Legacy API wrapper used by some staff screens and stores.
 *
 * It adapts the newer `apiService` (which returns `response.data`)
 * to an axios-like interface where callers expect an object with
 * a `.data` property (and often then access `.data.data`).
 */
type ResponseLike<T = any> = { data: T };

export const api = {
  async get<T = any>(url: string, config?: AxiosRequestConfig): Promise<ResponseLike<T>> {
    const data = await apiService.get<T>(url, config);
    return { data };
  },

  async post<T = any>(url: string, body?: any, config?: AxiosRequestConfig): Promise<ResponseLike<T>> {
    const data = await apiService.post<T>(url, body, config);
    return { data };
  },

  async put<T = any>(url: string, body?: any, config?: AxiosRequestConfig): Promise<ResponseLike<T>> {
    const data = await apiService.put<T>(url, body, config);
    return { data };
  },

  async patch<T = any>(url: string, body?: any, config?: AxiosRequestConfig): Promise<ResponseLike<T>> {
    const data = await apiService.patch<T>(url, body, config);
    return { data };
  },

  async delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<ResponseLike<T>> {
    const data = await apiService.delete<T>(url, config);
    return { data };
  },
};

