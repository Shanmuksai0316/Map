import { z } from 'zod';

// Updated room change schema - removed all fields except description (reason)
export const roomChangeSchema = z.object({
  description: z
    .string()
    .min(1, 'Reason is required')
    .min(10, 'Reason must be at least 10 characters')
    .max(500, 'Reason must not exceed 500 characters'),
});

export type RoomChangeFormData = z.infer<typeof roomChangeSchema>;

