import { create } from 'zustand';
import { apiService } from '../services/api.service';
import { useAuthStore } from './auth.store';
import type { Incident, MedicalEmergency } from '../types';

const WARDEN_EMERGENCY_BASE = '/mobile/warden/emergency';
const CAMPUS_MANAGER_EMERGENCY_BASE = '/mobile/campus-manager/emergency';

function normalizeRole(role: string | null | undefined): string {
  return role?.toLowerCase().replace(/\s+/g, '_') ?? '';
}

function getEmergencyBasePaths(): string[] {
  const role = normalizeRole(useAuthStore.getState().user?.role);
  if (role === 'warden') {
    return [WARDEN_EMERGENCY_BASE, CAMPUS_MANAGER_EMERGENCY_BASE];
  }

  return [CAMPUS_MANAGER_EMERGENCY_BASE, WARDEN_EMERGENCY_BASE];
}

function shouldTryFallback(error: any): boolean {
  const status = error?.response?.status;
  return status === 403 || status === 404;
}

async function emergencyGet(
  path: string,
  options?: Record<string, unknown>,
) {
  const basePaths = getEmergencyBasePaths();
  let lastError: any;

  for (let i = 0; i < basePaths.length; i += 1) {
    try {
      return await apiService.get(`${basePaths[i]}${path}`, options);
    } catch (error: any) {
      lastError = error;
      if (!shouldTryFallback(error) || i === basePaths.length - 1) {
        throw error;
      }
    }
  }

  throw lastError;
}

async function emergencyPost(
  path: string,
  data?: Record<string, unknown>,
) {
  const basePaths = getEmergencyBasePaths();
  let lastError: any;

  for (let i = 0; i < basePaths.length; i += 1) {
    try {
      return await apiService.post(`${basePaths[i]}${path}`, data);
    } catch (error: any) {
      lastError = error;
      if (!shouldTryFallback(error) || i === basePaths.length - 1) {
        throw error;
      }
    }
  }

  throw lastError;
}

interface EmergencyState {
  incidents: Incident[];
  medicalEmergencies: MedicalEmergency[];
  unacknowledgedCount: number;
  isLoading: boolean;
  error: string | null;
  hasMore: boolean;
  currentPage: number;
  
  // Actions
  fetchIncidents: (page?: number, acknowledged?: boolean) => Promise<void>;
  fetchMedicalEmergencies: (page?: number, acknowledged?: boolean) => Promise<void>;
  fetchUnacknowledgedCount: () => Promise<void>;
  acknowledgeIncident: (incidentId: number) => Promise<void>;
  acknowledgeMedical: (medicalId: number) => Promise<void>;
  resetState: () => void;
}

export const useEmergencyStore = create<EmergencyState>((set, get) => ({
  incidents: [],
  medicalEmergencies: [],
  unacknowledgedCount: 0,
  isLoading: false,
  error: null,
  hasMore: true,
  currentPage: 1,

  fetchIncidents: async (page = 1, acknowledged?: boolean) => {
    const state = get();
    if (state.isLoading) return;

    set({ isLoading: true, error: null });

    try {
      const params: Record<string, any> = { page, per_page: 20 };
      if (acknowledged !== undefined) {
        params.acknowledged = acknowledged ? 1 : 0;
      }

      const response = await emergencyGet('/incidents', {
        params,
      });

      const newIncidents = response.data.data;
      const meta = response.data.meta;

      set({
        incidents:
          page === 1 ? newIncidents : [...state.incidents, ...newIncidents],
        currentPage: meta.current_page,
        hasMore: meta.current_page < Math.ceil(meta.total / meta.per_page),
        isLoading: false,
      });
    } catch (error: any) {
      set({
        error: error.message || 'Failed to fetch incidents',
        isLoading: false,
      });
    }
  },

  fetchMedicalEmergencies: async (page = 1, acknowledged?: boolean) => {
    const state = get();
    if (state.isLoading) return;

    set({ isLoading: true, error: null });

    try {
      const params: Record<string, number | boolean> = { page, per_page: 20 };
      if (acknowledged !== undefined) {
        params.acknowledged = acknowledged ? 1 : 0;
      }
      const response = await emergencyGet('/medical', {
        params,
      });

      const newEmergencies = response.data.data;
      const meta = response.data.meta;

      set({
        medicalEmergencies:
          page === 1
            ? newEmergencies
            : [...state.medicalEmergencies, ...newEmergencies],
        currentPage: meta.current_page,
        hasMore: meta.current_page < Math.ceil(meta.total / meta.per_page),
        isLoading: false,
      });
    } catch (error: any) {
      set({
        error: error.message || 'Failed to fetch medical emergencies',
        isLoading: false,
      });
    }
  },

  fetchUnacknowledgedCount: async () => {
    try {
      const response = await emergencyGet('/incidents/unread-count');
      set({ unacknowledgedCount: response.data?.data?.unread_count ?? response.data?.unread_count ?? 0 });
    } catch (error: any) {
      if (error?.response?.status === 404) {
        try {
          // Fallback for tenants that do not expose unread-count endpoint.
          const incidentsResponse = await emergencyGet('/incidents', {
            params: { acknowledged: 0, page: 1, per_page: 1 },
          });
          const fallbackCount =
            incidentsResponse?.data?.meta?.total ??
            (Array.isArray(incidentsResponse?.data?.data) ? incidentsResponse.data.data.length : 0);
          set({ unacknowledgedCount: fallbackCount });
        } catch {
          set({ unacknowledgedCount: 0 });
        }
        return;
      }
      console.error('Failed to fetch unacknowledged count:', error);
    }
  },

  acknowledgeIncident: async (incidentId: number) => {
    try {
      await emergencyPost(`/incidents/${incidentId}/acknowledge`);
      const state = get();
      set({
        incidents: state.incidents.filter((i) => i.id !== incidentId),
        unacknowledgedCount: Math.max(0, state.unacknowledgedCount - 1),
      });
    } catch (error: any) {
      console.error('Failed to acknowledge incident:', error);
      throw error;
    }
  },

  acknowledgeMedical: async (medicalId: number) => {
    try {
      await emergencyPost(`/medical/${medicalId}/acknowledge`);
      const state = get();
      set({
        medicalEmergencies: state.medicalEmergencies.filter((e) => e.id !== medicalId),
        unacknowledgedCount: Math.max(0, (state.unacknowledgedCount ?? 0) - 1),
      });
    } catch (error: any) {
      console.error('Failed to acknowledge medical emergency:', error);
      throw error;
    }
  },

  resetState: () => {
    set({
      incidents: [],
      medicalEmergencies: [],
      unacknowledgedCount: 0,
      isLoading: false,
      error: null,
      hasMore: true,
      currentPage: 1,
    });
  },
}));

export default useEmergencyStore;
