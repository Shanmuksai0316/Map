import HapticFeedback from 'react-native-haptic-feedback';

export type HapticType =
  | 'light'
  | 'medium'
  | 'heavy'
  | 'success'
  | 'warning'
  | 'error'
  | 'selection'
  | 'impactLight'
  | 'impactMedium'
  | 'impactHeavy'
  | 'rigid'
  | 'soft'
  | 'none';

class HapticService {
  private options = {
    enableVibrateFallback: true,
    ignoreAndroidSystemSettings: false,
  };

  trigger(type: HapticType = 'light') {
    try {
      switch (type) {
        case 'light':
          HapticFeedback.trigger('impactLight', this.options);
          break;
        case 'medium':
          HapticFeedback.trigger('impactMedium', this.options);
          break;
        case 'heavy':
          HapticFeedback.trigger('impactHeavy', this.options);
          break;
        case 'success':
          HapticFeedback.trigger('notificationSuccess', this.options);
          break;
        case 'warning':
          HapticFeedback.trigger('notificationWarning', this.options);
          break;
        case 'error':
          HapticFeedback.trigger('notificationError', this.options);
          break;
        case 'selection':
          HapticFeedback.trigger('selection', this.options);
          break;
        case 'impactLight':
          HapticFeedback.trigger('impactLight', this.options);
          break;
        case 'impactMedium':
          HapticFeedback.trigger('impactMedium', this.options);
          break;
        case 'impactHeavy':
          HapticFeedback.trigger('impactHeavy', this.options);
          break;
        case 'rigid':
          HapticFeedback.trigger('rigid', this.options);
          break;
        case 'soft':
          HapticFeedback.trigger('soft', this.options);
          break;
        case 'none':
        default:
          // No haptic feedback
          break;
      }
    } catch (error) {
      // Silently fail if haptic feedback is not available
      console.warn('Haptic feedback not available:', error);
    }
  }

  // Convenience methods for common actions
  onTabPress() {
    this.trigger('selection');
  }

  onButtonPress() {
    this.trigger('light');
  }

  onFormSubmit() {
    this.trigger('medium');
  }

  onSuccess() {
    this.trigger('success');
  }

  onError() {
    this.trigger('error');
  }

  onWarning() {
    this.trigger('warning');
  }
}

export const hapticService = new HapticService();
