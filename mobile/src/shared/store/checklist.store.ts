import { create } from 'zustand';
import { api } from '../../services/api';
import type { ChecklistInstance, ChecklistTask } from '../types';
import type { Asset } from 'react-native-image-picker';
import { useAuthStore } from './auth.store';

const normalizeRole = (role: string | null | undefined): string =>
  role?.toLowerCase().replace(/\s+/g, '_') ?? '';

const isFallbackStatus = (error: any): boolean => {
  const status = error?.response?.status;
  return status === 403 || status === 404;
};

const getChecklistRouteConfig = (taskIndex?: number, taskId?: number) => {
  const role = normalizeRole(useAuthStore.getState().user?.role);
  const isCampusManager = role === 'campus_manager' || role === 'rector';

  if (isCampusManager) {
    return {
      currentPaths: [
        '/mobile/campus-manager/checklists/current',
        '/campus-manager/checklists/current',
      ],
      completePaths: taskIndex === undefined
        ? []
        : [
            `/mobile/campus-manager/checklists/items/${taskIndex}/complete`,
            `/campus-manager/checklists/items/${taskIndex}/complete`,
          ],
      photoPaths: taskIndex === undefined
        ? []
        : [
            `/mobile/campus-manager/checklists/items/${taskIndex}/photo`,
            `/campus-manager/checklists/items/${taskIndex}/photo`,
          ],
      submitPaths: [
        '/mobile/campus-manager/checklists/submit',
        '/campus-manager/checklists/submit',
      ],
      historyPaths: [],
      isCampusManager: true,
    };
  }

  const targetTask = taskId ?? taskIndex ?? 0;
  return {
    currentPaths: [
      '/mobile/guard/checklist/current',
      '/guard/checklist/current',
      '/mobile/guard/checklists/current',
      '/guard/checklists/current',
    ],
    completePaths: [
      `/mobile/guard/checklist/${targetTask}/complete`,
      `/guard/checklist/${targetTask}/complete`,
    ],
    photoPaths: [
      `/mobile/guard/checklist/${targetTask}/photo`,
      `/guard/checklist/${targetTask}/photo`,
    ],
    submitPaths: [
      '/mobile/guard/checklist/submit',
      '/guard/checklist/submit',
    ],
    historyPaths: [
      '/mobile/guard/checklist/history',
      '/guard/checklist/history',
    ],
    isCampusManager: false,
  };
};

const getWithFallback = async <T = any>(paths: string[], config?: any) => {
  let lastError: any;

  for (const path of paths) {
    try {
      return await api.get<T>(path, config);
    } catch (error: any) {
      lastError = error;
      if (!isFallbackStatus(error) || paths.indexOf(path) === paths.length - 1) {
        throw error;
      }
    }
  }

  throw lastError;
};

const postWithFallback = async <T = any>(paths: string[], body?: any, config?: any) => {
  let lastError: any;

  for (const path of paths) {
    try {
      return await api.post<T>(path, body, config);
    } catch (error: any) {
      lastError = error;
      if (!isFallbackStatus(error) || paths.indexOf(path) === paths.length - 1) {
        throw error;
      }
    }
  }

  throw lastError;
};

const resolveTaskIndex = (tasks: ChecklistTask[], taskIdOrIndex: number): number => {
  const byIdIndex = tasks.findIndex((t: ChecklistTask) => t.id === taskIdOrIndex);
  if (byIdIndex >= 0) {
    return byIdIndex;
  }
  return taskIdOrIndex;
};

const unwrapPayloadData = <T = any>(payload: any): T | null => {
  if (!payload) return null;
  if (payload?.data?.data != null) return payload.data.data as T;
  if (payload?.data != null) return payload.data as T;
  return payload as T;
};

interface ChecklistState {
  currentChecklist: ChecklistInstance | null;
  history: ChecklistInstance[];
  isLoading: boolean;
  error: string | null;
  hasMore: boolean;
  currentPage: number;
  
  // Computed getters for screen compatibility
  tasks: ChecklistTask[];
  pendingTasks: number;

  // Actions
  fetchCurrentChecklist: () => Promise<void>;
  fetchTodayChecklist: () => Promise<void>; // Alias for fetchCurrentChecklist
  completeTask: (taskIdOrIndex: number, comment?: string, photoUrl?: string) => Promise<void>;
  uploadTaskPhoto: (taskIdOrIndex: number, photo: Asset | string) => Promise<string | null>;
  submitChecklist: () => Promise<void>;
  fetchHistory: (page?: number) => Promise<void>;
  resetState: () => void;
}

export const useChecklistStore = create<ChecklistState>((set, get) => ({
  currentChecklist: null,
  history: [],
  isLoading: false,
  error: null,
  hasMore: true,
  currentPage: 1,
  
  // Computed getters for screen compatibility
  get tasks(): ChecklistTask[] {
    return get().currentChecklist?.tasks ?? [];
  },
  get pendingTasks(): number {
    const tasks = get().currentChecklist?.tasks ?? [];
    return tasks.filter((t: ChecklistTask) => !t.completed && !t.is_completed).length;
  },

  fetchCurrentChecklist: async () => {
    set({ isLoading: true, error: null });

    try {
      const routes = getChecklistRouteConfig();
      const response = await getWithFallback(routes.currentPaths);
      const data = unwrapPayloadData<ChecklistInstance>(response);

      set({
        currentChecklist: data,
        isLoading: false,
      });
    } catch (error: any) {
      set({
        error: error.message || 'Failed to fetch checklist',
        isLoading: false,
      });
    }
  },

  // Alias for backward compatibility with screens
  fetchTodayChecklist: async () => {
    return get().fetchCurrentChecklist();
  },

  completeTask: async (taskIdOrIndex: number, comment?: string, photoUrl?: string) => {
    const state = get();
    if (!state.currentChecklist) return;

    // Find task index - caller may pass either item id or visible index.
    const tasks = state.currentChecklist.tasks;
    const taskIndex = resolveTaskIndex(tasks, taskIdOrIndex);

    set({ isLoading: true, error: null });

    try {
      const task = tasks[taskIndex];
      const routes = getChecklistRouteConfig(taskIndex, taskIndex);
      const response = await postWithFallback(routes.completePaths, {
        comment,
        photo_url: photoUrl,
      });
      const responseData = unwrapPayloadData<any>(response);

      // Update local state
      const updatedTasks = [...tasks];
      updatedTasks[taskIndex] = {
        ...updatedTasks[taskIndex],
        completed: true,
        is_completed: true,
        completed_at: responseData?.completed_at ?? new Date().toISOString(),
        comment,
        photo_url: photoUrl || updatedTasks[taskIndex].photo_url,
      };

      const completedCount = updatedTasks.filter((t: ChecklistTask) => t.completed || t.is_completed).length;

      set({
        currentChecklist: {
          ...state.currentChecklist,
          tasks: updatedTasks,
          completed_count: completedCount,
          status: responseData?.checklist_status ?? state.currentChecklist.status,
        },
        isLoading: false,
      });
    } catch (error: any) {
      set({
        error: error.message || 'Failed to complete task',
        isLoading: false,
      });
      throw error;
    }
  },

  uploadTaskPhoto: async (taskIdOrIndex: number, photo: Asset | string) => {
    const state = get();
    if (!state.currentChecklist) return null;

    // Get URI from Asset or use string directly
    const photoUri = typeof photo === 'string' ? photo : photo.uri;
    if (!photoUri) return null;

    // Find task index - caller may pass either item id or visible index.
    const tasks = state.currentChecklist.tasks;
    const taskIndex = resolveTaskIndex(tasks, taskIdOrIndex);

    try {
      const task = tasks[taskIndex];
      const formData = new FormData();
      formData.append('photo', {
        uri: photoUri,
        type: typeof photo === 'object' && photo.type ? photo.type : 'image/jpeg',
        name: typeof photo === 'object' && photo.fileName 
          ? photo.fileName 
          : `checklist_task_${task?.id ?? taskIndex}_${Date.now()}.jpg`,
      } as any);

      const routes = getChecklistRouteConfig(taskIndex, taskIndex);
      const response = await postWithFallback(routes.photoPaths, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      const responseData = unwrapPayloadData<any>(response);
      const uploadedPhotoUrl = responseData?.photo_url ?? null;

      // Update local state
      const updatedTasks = [...tasks];
      updatedTasks[taskIndex] = {
        ...updatedTasks[taskIndex],
        photo_url: uploadedPhotoUrl ?? updatedTasks[taskIndex].photo_url,
      };

      set({
        currentChecklist: {
          ...state.currentChecklist,
          tasks: updatedTasks,
        },
      });

      return uploadedPhotoUrl;
    } catch (error: any) {
      console.error('Failed to upload photo:', error);
      return null;
    }
  },

  submitChecklist: async () => {
    const state = get();
    if (!state.currentChecklist) return;

    set({ isLoading: true, error: null });

    try {
      const routes = getChecklistRouteConfig();
      await postWithFallback(routes.submitPaths);
      // After submit, backend resets the "form" instance.
      await get().fetchCurrentChecklist();
      set({ isLoading: false });
    } catch (error: any) {
      set({
        error: error?.response?.data?.message || error.message || 'Failed to submit checklist',
        isLoading: false,
      });
      throw error;
    }
  },

  fetchHistory: async (page = 1) => {
    const state = get();
    if (state.isLoading) return;

    set({ isLoading: true, error: null });

    const routes = getChecklistRouteConfig();
    if (routes.isCampusManager) {
      set({
        history: [],
        currentPage: 1,
        hasMore: false,
        isLoading: false,
      });
      return;
    }

    try {
      const response = await getWithFallback(routes.historyPaths, {
        params: { page, per_page: 20 },
      });

      const newHistory = response?.data?.data ?? [];
      const meta = response?.data?.meta ?? {
        current_page: page,
        per_page: 20,
        total: newHistory.length,
      };

      set({
        history:
          page === 1 ? newHistory : [...state.history, ...newHistory],
        currentPage: meta.current_page,
        hasMore: meta.current_page < Math.ceil(meta.total / meta.per_page),
        isLoading: false,
      });
    } catch (error: any) {
      set({
        error: error.message || 'Failed to fetch history',
        isLoading: false,
      });
    }
  },

  resetState: () => {
    set({
      currentChecklist: null,
      history: [],
      isLoading: false,
      error: null,
      hasMore: true,
      currentPage: 1,
    });
  },
}));

export default useChecklistStore;
