import { z } from 'zod';

export const roomChangeSchema = z.object({
  description: z
    .string()
    .min(1, 'Description is required')
    .min(10, 'Description must be at least 10 characters')
    .max(500, 'Description must not exceed 500 characters'),
  preferred_room_number: z
    .string()
    .max(20, 'Room number must not exceed 20 characters')
    .optional()
    .or(z.literal('')),
  preferred_floor: z
    .string()
    .max(10, 'Floor must not exceed 10 characters')
    .optional()
    .or(z.literal('')),
  sharing_preference: z
    .enum(['single', 'double', 'triple', 'quad'], {
      errorMap: () => ({ message: 'Invalid sharing preference' }),
    })
    .optional(),
  date_required: z
    .date()
    .optional()
    .refine(
      (date) => {
        if (!date) return true;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return date >= today;
      },
      {
        message: 'Date required must be today or in the future',
      }
    ),
});

export type RoomChangeFormData = z.infer<typeof roomChangeSchema>;

