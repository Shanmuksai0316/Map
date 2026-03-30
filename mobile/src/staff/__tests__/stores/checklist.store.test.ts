import { renderHook, act } from '@testing-library/react-hooks';
import { useChecklistStore } from '../../shared/store/checklist.store';
import { api } from '../../services/api';

jest.mock('../../services/api');

describe('useChecklistStore', () => {
  beforeEach(() => {
    const { result } = renderHook(() => useChecklistStore());
    act(() => {
      result.current.reset();
    });
    jest.clearAllMocks();
  });

  it('initializes with default values', () => {
    const { result } = renderHook(() => useChecklistStore());
    expect(result.current.tasks).toEqual([]);
    expect(result.current.pendingTasks).toBe(0);
    expect(result.current.isLoading).toBe(false);
  });

  it('fetches today checklist successfully', async () => {
    const mockTasks = [
      { id: 1, title: 'Task 1', is_completed: false },
      { id: 2, title: 'Task 2', is_completed: true },
    ];

    (api.get as jest.Mock).mockResolvedValueOnce({
      data: { data: { tasks: mockTasks } },
    });

    const { result } = renderHook(() => useChecklistStore());

    await act(async () => {
      await result.current.fetchTodayChecklist();
    });

    expect(result.current.tasks).toEqual(mockTasks);
    expect(result.current.pendingTasks).toBe(1);
  });

  it('completes a task', async () => {
    const mockTasks = [
      { id: 1, title: 'Task 1', is_completed: false },
      { id: 2, title: 'Task 2', is_completed: false },
    ];

    const { result } = renderHook(() => useChecklistStore());

    act(() => {
      result.current.setTasks(mockTasks);
    });

    (api.post as jest.Mock).mockResolvedValueOnce({});

    await act(async () => {
      await result.current.completeTask(1);
    });

    expect(result.current.tasks.find(t => t.id === 1)?.is_completed).toBe(true);
    expect(result.current.pendingTasks).toBe(1);
  });

  it('uploads photo for task', async () => {
    const mockTasks = [
      { id: 1, title: 'Task 1', is_completed: false, requires_photo: true },
    ];

    const { result } = renderHook(() => useChecklistStore());

    act(() => {
      result.current.setTasks(mockTasks);
    });

    (api.post as jest.Mock).mockResolvedValueOnce({
      data: { data: { photo_url: 'https://example.com/photo.jpg' } },
    });

    const mockAsset = { uri: 'file://photo.jpg', type: 'image/jpeg' };

    await act(async () => {
      await result.current.uploadTaskPhoto(1, mockAsset);
    });

    expect(result.current.tasks.find(t => t.id === 1)?.photo_url).toBe(
      'https://example.com/photo.jpg'
    );
  });

  it('calculates progress correctly', () => {
    const { result } = renderHook(() => useChecklistStore());

    act(() => {
      result.current.setTasks([
        { id: 1, title: 'Task 1', is_completed: true },
        { id: 2, title: 'Task 2', is_completed: false },
        { id: 3, title: 'Task 3', is_completed: true },
        { id: 4, title: 'Task 4', is_completed: false },
      ]);
    });

    expect(result.current.getProgress()).toBe(50);
  });
});

