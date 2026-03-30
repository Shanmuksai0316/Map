import { z } from 'zod';

export const ticketSchema = z.object({
  issue: z
    .string()
    .min(1, 'Issue is required')
    .max(200, 'Issue must not exceed 200 characters'),
  description: z
    .string()
    .max(1000, 'Description must not exceed 1000 characters')
    .optional()
    .default(''),
  request_type: z
    .enum(['housekeeping', 'repair_maintenance'], {
      errorMap: () => ({ message: 'Invalid request type' }),
    }),
  photos: z
    .array(z.string())
    .max(3, 'Maximum 3 photos allowed')
    .default([])
    .optional(),
});

export type TicketFormData = z.infer<typeof ticketSchema>;

