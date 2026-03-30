import messaging, { FirebaseMessagingTypes } from '@react-native-firebase/messaging';
import notifee, { AndroidImportance, EventType } from '@notifee/react-native';
import { Platform } from 'react-native';
import { APP_CONFIG } from '../config/app.config';
import { apiService } from './api.service';
import { StorageService } from './storage.service';
import { useNotificationStore } from '../store/notification.store';

const TOKEN_STORAGE_KEY = 'push_fcm_token';

/** Screen name to open when user taps a push (e.g. 'Notifications'). Backend can send data.screen in the message. */
const DEFAULT_TAP_SCREEN = 'Notifications';

type NavigationRef = { current: { navigate: (name: string, params?: object) => void } | null } | null;

class PushNotificationService {
  private initialized = false;
  private refreshUnsubscribe: (() => void) | null = null;
  private foregroundUnsubscribe: (() => void) | null = null;
  private notificationOpenedUnsubscribe: (() => void) | null = null;
  private notifeeForegroundUnsubscribe: (() => void) | null = null;
  private pendingNotificationScreen: string | null = null;
  private pendingNotificationParams: object | null = null;
  private navigationRef: NavigationRef = null;
  private androidChannelId: string | null = null;

  setNavigationRef(ref: NavigationRef): void {
    this.navigationRef = ref;
    this.tryNavigateToPending();
  }

  getPendingNotificationScreen(): string | null {
    return this.pendingNotificationScreen;
  }

  clearPendingNotificationScreen(): void {
    this.pendingNotificationScreen = null;
    this.pendingNotificationParams = null;
  }

  private setPendingFromMessage(remoteMessage: FirebaseMessagingTypes.RemoteMessage | null): void {
    if (!remoteMessage) return;
    const screen = (remoteMessage.data?.screen as string) || DEFAULT_TAP_SCREEN;
    this.pendingNotificationScreen = screen;

    // When opening the Notifications screen from a push tap, also instruct
    // the screen to auto-open the latest notification detail.
    if (screen === DEFAULT_TAP_SCREEN) {
      this.pendingNotificationParams = { openLatestNotification: true };
    } else {
      this.pendingNotificationParams = {};
    }

    this.tryNavigateToPending();
  }

  private tryNavigateToPending(): void {
    const screen = this.pendingNotificationScreen;
    const params = this.pendingNotificationParams || undefined;
    if (!screen || !this.navigationRef?.current) return;
    try {
      this.navigationRef.current.navigate(screen, params);
      this.pendingNotificationScreen = null;
      this.pendingNotificationParams = null;
    } catch (e) {
      console.warn('[Push] Navigate to pending screen failed', e);
    }
  }

  async initialize(): Promise<void> {
    if (this.initialized) {
      return;
    }

    await messaging().setAutoInitEnabled(true);
    // Set a no-op background handler to ensure background messages are handled by Firebase SDK
    messaging().setBackgroundMessageHandler(async () => {});

    // Prepare local notification channel for foreground messages (Android)
    if (Platform.OS === 'android') {
      try {
        this.androidChannelId =
          this.androidChannelId ??
          (await notifee.createChannel({
            id: 'default',
            name: 'Default',
            importance: AndroidImportance.HIGH,
          }));
      } catch (err) {
        console.warn('[Push] Failed to create Notifee channel', err);
      }
    }

    this.initialized = true;
  }

  async start(): Promise<void> {
    await this.initialize();
    await this.registerDeviceToken();
    this.attachListeners();
    this.attachNotificationOpenedListeners();
  }

  async registerDeviceToken(providedToken?: string): Promise<void> {
    const hasPermission = await this.ensurePermission();
    if (!hasPermission) return;

    const token = providedToken || (await messaging().getToken());
    if (!token) return;

    const cachedToken = await StorageService.get(TOKEN_STORAGE_KEY);
    if (cachedToken === token) return;

    try {
      await apiService.post('/mobile/devices/register', {
        platform: Platform.OS === 'ios' ? 'ios' : 'android',
        token,
        meta: {
          app_variant: APP_CONFIG.BUILD_VARIANT,
        },
      });
      await StorageService.set(TOKEN_STORAGE_KEY, token);
    } catch (error: any) {
      console.warn('[Push] Failed to register device token', error?.message || error);
    }
  }

  async ensurePermission(): Promise<boolean> {
    const authStatus = await messaging().requestPermission();
    return (
      authStatus === messaging.AuthorizationStatus.AUTHORIZED ||
      authStatus === messaging.AuthorizationStatus.PROVISIONAL
    );
  }

  attachListeners(): void {
    if (!this.refreshUnsubscribe) {
      this.refreshUnsubscribe = messaging().onTokenRefresh(async (token) => {
        await this.registerDeviceToken(token);
      });
    }

    if (!this.foregroundUnsubscribe) {
      this.foregroundUnsubscribe = messaging().onMessage(
        async (remoteMessage: FirebaseMessagingTypes.RemoteMessage) => {
          // Refresh unread count when a notification arrives in foreground
          try {
            const { fetchUnreadCount } = useNotificationStore.getState();
            await fetchUnreadCount();
          } catch (err) {
            console.warn('[Push] Foreground handler error', err);
          }

          // Show a local notification while app is in foreground
          try {
            const title =
              remoteMessage.notification?.title ||
              (remoteMessage.data?.title as string) ||
              'Notification';
            const body =
              remoteMessage.notification?.body ||
              (remoteMessage.data?.body as string) ||
              '';

            if (Platform.OS === 'android') {
              const channelId =
                this.androidChannelId ??
                (await notifee.createChannel({
                  id: 'default',
                  name: 'Default',
                  importance: AndroidImportance.HIGH,
                }));
              await notifee.displayNotification({
                title,
                body,
                android: {
                  channelId,
                  // Ensure press on the system notification is detectable
                  pressAction: { id: 'default' },
                },
                // Preserve original data so we can inspect it on press if needed
                data: remoteMessage.data ?? {},
              });
            } else {
              await notifee.displayNotification({
                title,
                body,
                data: remoteMessage.data ?? {},
              });
            }
          } catch (err) {
            console.warn('[Push] Failed to display foreground notification', err);
          }

        }
      );
    }

    // Handle taps on Notifee notifications while app is in foreground
    if (!this.notifeeForegroundUnsubscribe) {
      this.notifeeForegroundUnsubscribe = notifee.onForegroundEvent(({ type }) => {
        if (type === EventType.PRESS) {
          // Always route to Notifications screen and open latest notification detail.
          this.pendingNotificationScreen = DEFAULT_TAP_SCREEN;
          this.pendingNotificationParams = { openLatestNotification: true };
          this.tryNavigateToPending();
        }
      });
    }
  }

  private attachNotificationOpenedListeners(): void {
    // App opened from quit by tapping notification
    messaging()
      .getInitialNotification()
      .then((remoteMessage) => {
        if (remoteMessage) this.setPendingFromMessage(remoteMessage);
      })
      .catch((err) => console.warn('[Push] getInitialNotification error', err));

    // App opened from background by tapping notification
    if (!this.notificationOpenedUnsubscribe) {
      this.notificationOpenedUnsubscribe = messaging().onNotificationOpenedApp((remoteMessage) => {
        this.setPendingFromMessage(remoteMessage);
      });
    }
  }

  async clearCachedToken(): Promise<void> {
    await StorageService.delete(TOKEN_STORAGE_KEY);
  }

  teardown(): void {
    if (this.refreshUnsubscribe) {
      this.refreshUnsubscribe();
      this.refreshUnsubscribe = null;
    }
    if (this.foregroundUnsubscribe) {
      this.foregroundUnsubscribe();
      this.foregroundUnsubscribe = null;
    }
    if (this.notificationOpenedUnsubscribe) {
      this.notificationOpenedUnsubscribe();
      this.notificationOpenedUnsubscribe = null;
    }
    if (this.notifeeForegroundUnsubscribe) {
      this.notifeeForegroundUnsubscribe();
      this.notifeeForegroundUnsubscribe = null;
    }
    this.navigationRef = null;
    this.pendingNotificationScreen = null;
    this.initialized = false;
  }
}

export const pushNotificationService = new PushNotificationService();
