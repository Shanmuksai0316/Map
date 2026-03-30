/**
 * Share template for the student app ("Share the application").
 * Used when sharing the app from Social Media screen or Profile.
 * Single source of truth so the same message shows across iOS, Android, and all share targets
 * (WhatsApp, SMS, email, etc.).
 */

/** App name shown in the share message */
export const STUDENT_APP_SHARE_APP_NAME = 'Vidyarthi';

/**
 * Optional app link. Use your Play Store / App Store / landing page URL.
 * When set, it is included in the message and passed as `url` to Share for link previews.
 */
export const STUDENT_APP_SHARE_URL =
  'https://mapservices.in/';

/**
 * Short tagline for the share dialog title and previews.
 */
export const STUDENT_APP_SHARE_TAGLINE = 'Your smart hostel life, one app.';

/**
 * Title for the share dialog (Android uses this; iOS may show it in some contexts).
 */
export const STUDENT_APP_SHARE_TITLE = `Share ${STUDENT_APP_SHARE_APP_NAME} — ${STUDENT_APP_SHARE_TAGLINE}`;

/**
 * Default share message body. Shown as-is in most apps (SMS, WhatsApp, etc.).
 * Elaborate template: value proposition, features, and CTA with URL.
 */
export const STUDENT_APP_SHARE_MESSAGE = [
  `Hey! I'm using ${STUDENT_APP_SHARE_APP_NAME} — ${STUDENT_APP_SHARE_TAGLINE}`,
  '',
  'It’s the official app for hostel students. I use it for:',
  '• Outpass & gate pass requests',
  '• Leave applications & guest entry',
  '• Notice board & announcements',
  '• Attendance, complaints, and more',
  '',
  'Everything in one place, no running to the office. If your college uses it, download and stay connected:',
  STUDENT_APP_SHARE_URL,
].join('\n');

/**
 * Returns the share payload for React Native Share.share().
 * Use this so both Social Media and Profile (and any future entry points) use the same template.
 */
export function getStudentAppSharePayload(): {
  title: string;
  message: string;
  url: string;
} {
  return {
    title: STUDENT_APP_SHARE_TITLE,
    message: STUDENT_APP_SHARE_MESSAGE,
    url: STUDENT_APP_SHARE_URL,
  };
}
