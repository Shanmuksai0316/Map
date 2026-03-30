import { renderHook, act } from '@testing-library/react-hooks';
import { useNotificationStore } from '../../shared/store/notification.store';
import { api } from '../../services/api';

jest.mock('../../services/api');

describe('useNotificationStore', () => {
  beforeEach(() => {
    // Reset the store before each test
    const { result } = renderHook(() => useNotificationStore());
    act(() => {
      result.current.reset();
    });
  });

  it('initializes with default values', () => {
    const { result } = renderHook(() => useNotificationStore());
    expect(result.current.unreadCount).toBe(0);
    expect(result.current.notifications).toEqual([]);
    expect(result.current.isLoading).toBe(false);
  });

  it('fetches unread count successfully', async () => {
    (api.get as jest.Mock).mockResolvedValueOnce({
      data: { data: { count: 5 } },
    });

    const { result } = renderHook(() => useNotificationStore());

    await act(async () => {
      await result.current.fetchUnreadCount();
    });

    expect(result.current.unreadCount).toBe(5);
  });

  it('fetches notifications successfully', async () => {
    const mockNotifications = [
      { id: 1, title: 'Test 1', read: false },
      { id: 2, title: 'Test 2', read: true },
    ];

    (api.get as jest.Mock).mockResolvedValueOnce({
      data: { data: mockNotifications },
    });

    const { result } = renderHook(() => useNotificationStore());

    await act(async () => {
      await result.current.fetchNotifications();
    });

    expect(result.current.notifications).toEqual(mockNotifications);
  });

  it('marks notification as read', async () => {
    const mockNotifications = [
      { id: 1, title: 'Test 1', read: false },
      { id: 2, title: 'Test 2', read: false },
    ];

    const { result } = renderHook(() => useNotificationStore());

    // Set initial notifications
    act(() => {
      result.current.setNotifications(mockNotifications);
      result.current.setUnreadCount(2);
    });

    (api.post as jest.Mock).mockResolvedValueOnce({});

    await act(async () => {
      await result.current.markAsRead(1);
    });

    expect(result.current.notifications[0].read).toBe(true);
    expect(result.current.unreadCount).toBe(1);
  });

  it('marks all notifications as read', async () => {
    const mockNotifications = [
      { id: 1, title: 'Test 1', read: false },
      { id: 2, title: 'Test 2', read: false },
    ];

    const { result } = renderHook(() => useNotificationStore());

    act(() => {
      result.current.setNotifications(mockNotifications);
      result.current.setUnreadCount(2);
    });

    (api.post as jest.Mock).mockResolvedValueOnce({});

    await act(async () => {
      await result.current.markAllAsRead();
    });

    expect(result.current.notifications.every(n => n.read)).toBe(true);
    expect(result.current.unreadCount).toBe(0);
  });
});

