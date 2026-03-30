import { z } from 'zod';

export const sickLeaveSchema = z.object({
  illness: z
    .string()
    .min(1, 'Illness is required')
    .min(3, 'Illness must be at least 3 characters')
    .max(100, 'Illness must not exceed 100 characters'),
  illness_details: z
    .string()
    .min(1, 'Illness details are required')
    .min(20, 'Illness details must be at least 20 characters')
    .max(1000, 'Illness details must not exceed 1000 characters'),
  need_medical_attention: z.boolean().default(false),
  contact_parents: z.boolean().default(false),
});

export type SickLeaveFormData = z.infer<typeof sickLeaveSchema>;

