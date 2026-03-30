/**
 * SLA Utility Functions
 * Calculates SLA time remaining and determines breach status per PRD §4.7
 */

export interface SLAConfig {
  hours: number;
  category: string;
}

// SLA configurations per PRD §4.7
export const SLA_CONFIGS: Record<string, SLAConfig> = {
  housekeeping: { hours: 12, category: 'Housekeeping' },
  maintenance: { hours: 24, category: 'Maintenance' },
  repair: { hours: 24, category: 'Repair & Maintenance' },
  laundry: { hours: 48, category: 'Laundry' },
  sports: { hours: 24, category: 'Sports' },
  general: { hours: 24, category: 'General' },
  room_change: { hours: 24, category: 'Room Change' },
};

export interface SLATimeRemaining {
  hoursRemaining: number;
  minutesRemaining: number;
  isBreached: boolean;
  percentageRemaining: number;
  formattedTime: string;
}

/**
 * Calculate SLA time remaining for a ticket
 * SLA timers start at ticket creation and pause only when marked "In Progress"
 * @param createdAt ISO timestamp of ticket creation
 * @param status Current ticket status
 * @param slaHours SLA hours for this ticket category
 */
export const calculateSLATimeRemaining = (
  createdAt: string,
  status: string,
  slaHours: number
): SLATimeRemaining => {
  const created = new Date(createdAt);
  const now = new Date();
  
  // If ticket is "In Progress", calculate time from when it was marked in progress
  // For simplicity, we'll use updated_at if available, otherwise use created_at
  // In production, backend should track when status changed to "in_progress"
  const startTime = status === 'in_progress' ? created : created;
  
  const elapsedMs = now.getTime() - startTime.getTime();
  const elapsedHours = elapsedMs / (1000 * 60 * 60);
  
  const hoursRemaining = Math.max(0, slaHours - elapsedHours);
  const minutesRemaining = Math.max(0, Math.floor((hoursRemaining % 1) * 60));
  const isBreached = elapsedHours >= slaHours;
  const percentageRemaining = Math.max(0, Math.min(100, (hoursRemaining / slaHours) * 100));
  
  // Format time remaining
  let formattedTime = '';
  if (isBreached) {
    const breachHours = Math.floor(elapsedHours - slaHours);
    const breachMinutes = Math.floor(((elapsedHours - slaHours) % 1) * 60);
    formattedTime = `Breached: +${breachHours}h ${breachMinutes}m`;
  } else if (hoursRemaining >= 1) {
    formattedTime = `${Math.floor(hoursRemaining)}h ${minutesRemaining}m remaining`;
  } else {
    formattedTime = `${minutesRemaining}m remaining`;
  }
  
  return {
    hoursRemaining,
    minutesRemaining,
    isBreached,
    percentageRemaining,
    formattedTime,
  };
};

/**
 * Get SLA configuration for a ticket category
 */
export const getSLAConfig = (category: string): SLAConfig => {
  const normalizedCategory = category.toLowerCase();
  
  // Map common category names to SLA configs
  if (normalizedCategory.includes('housekeeping') || normalizedCategory.includes('hk')) {
    return SLA_CONFIGS.housekeeping;
  }
  if (normalizedCategory.includes('maintenance') || normalizedCategory.includes('repair') || normalizedCategory.includes('rm')) {
    return SLA_CONFIGS.maintenance;
  }
  if (normalizedCategory.includes('laundry')) {
    return SLA_CONFIGS.laundry;
  }
  if (normalizedCategory.includes('sports')) {
    return SLA_CONFIGS.sports;
  }
  if (normalizedCategory.includes('room') && normalizedCategory.includes('change')) {
    return SLA_CONFIGS.room_change;
  }
  
  return SLA_CONFIGS.general;
};

