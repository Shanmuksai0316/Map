/**
 * Greeting Utility
 * Returns an IST-aware greeting message so staff dashboards feel personal.
 */
export const getGreeting = (inputDate?: Date): string => {
  const now = inputDate ?? new Date();
  const utcMillis = now.getTime() + now.getTimezoneOffset() * 60000;
  const istMillis = utcMillis + 5.5 * 60 * 60 * 1000;
  const istDate = new Date(istMillis);
  const hour = istDate.getHours();
  const minute = istDate.getMinutes();
  const totalMinutes = hour * 60 + minute;

  if (totalMinutes >= 240 && totalMinutes < 720) {
    return 'Good Morning';
  }

  if (totalMinutes >= 720 && totalMinutes < 1020) {
    return 'Good Afternoon';
  }

  if (totalMinutes >= 1020 && totalMinutes < 1260) {
    return 'Good Evening';
  }

  return 'Good Night';
};
