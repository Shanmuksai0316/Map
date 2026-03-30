import { z } from 'zod';

// Updated guest schema - removed id_type and id_number per feedback
const guestSchema = z.object({
  name: z
    .string()
    .min(1, 'Guest name is required')
    .max(100, 'Guest name must not exceed 100 characters'),
  relationship: z
    .string()
    .min(1, 'Relationship is required'),
  phone: z
    .union([
      z.string().length(0), // Empty string
      z.string().regex(/^\d{10}$/, 'Phone must be a valid 10-digit number'),
    ])
    .optional()
    .transform((val) => val === '' ? undefined : val),
});

// Updated guest entry schema - removed primary_contact_mobile per feedback
export const guestEntrySchema = z.object({
  guests: z
    .array(guestSchema)
    .min(1, 'At least one guest is required')
    .max(4, 'Maximum 4 guests allowed'),
  visit_date: z.date({
    required_error: 'Visit date is required',
  }),
  check_in_time: z.date().optional().nullable(),
  purpose_to_visit: z
    .string()
    .min(1, 'Purpose to visit is required')
    .min(10, 'Purpose to visit must be at least 10 characters')
    .max(500, 'Purpose to visit must not exceed 500 characters'),
}).refine(
  (data) => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return data.visit_date >= today;
  },
  {
    message: 'Visit date must be today or in the future',
    path: ['visit_date'],
  }
);

export type GuestEntryFormData = z.infer<typeof guestEntrySchema>;
export type GuestFormData = z.infer<typeof guestSchema>;

