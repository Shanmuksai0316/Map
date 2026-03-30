import { z } from 'zod';

export const ticketSchema = z.object({
  title: z
    .string()
    .min(1, 'Title is required')
    .max(200, 'Title must not exceed 200 characters'),
  issue: z
    .string()
    .min(1, 'Issue is required')
    .max(200, 'Issue must not exceed 200 characters'),
  description: z
    .string()
    .min(1, 'Description is required')
    .min(10, 'Description must be at least 10 characters')
    .max(1000, 'Description must not exceed 1000 characters'),
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

