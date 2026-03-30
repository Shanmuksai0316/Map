/**
 * Greeting Utility
 * Returns time-based greeting message
 */

export const getGreeting = (): string => {
  const hour = new Date().getHours();
  
  // Good morning: 00:00-11:59
  if (hour >= 0 && hour < 12) {
    return 'Good morning';
  } 
  // Good afternoon: 12:00-17:59
  else if (hour >= 12 && hour < 18) {
    return 'Good afternoon';
  } 
  // Good evening: 18:00-23:59
  else {
    return 'Good evening';
  }
};
