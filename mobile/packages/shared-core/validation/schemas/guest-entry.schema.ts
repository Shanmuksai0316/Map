import { z } from 'zod';

const guestSchema = z.object({
  name: z
    .string()
    .min(1, 'Guest name is required')
    .max(100, 'Guest name must not exceed 100 characters'),
  relationship: z
    .string()
    .min(1, 'Relationship is required'),
  phone: z
    .string()
    .regex(/^\d{10}$/, 'Phone must be a valid 10-digit number')
    .optional()
    .or(z.literal('')),
  id_type: z
    .string()
    .min(1, 'ID type is required'),
  id_number: z
    .string()
    .min(1, 'ID number is required')
    .max(50, 'ID number must not exceed 50 characters'),
});

export const guestEntrySchema = z.object({
  guests: z
    .array(guestSchema)
    .min(1, 'At least one guest is required')
    .max(4, 'Maximum 4 guests allowed'),
  primary_contact_mobile: z
    .string()
    .regex(/^\d{10}$/, 'Primary contact mobile must be a valid 10-digit number'),
  visit_date: z.date({
    required_error: 'Visit date is required',
  }),
  check_in_time: z.date({
    required_error: 'Check-in time is required',
  }),
  check_out_time: z.date({
    required_error: 'Check-out time is required',
  }),
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
).refine(
  (data) => {
    const checkInTime = data.check_in_time.getHours() * 60 + data.check_in_time.getMinutes();
    const checkOutTime = data.check_out_time.getHours() * 60 + data.check_out_time.getMinutes();
    return checkOutTime > checkInTime;
  },
  {
    message: 'Check-out time must be after check-in time',
    path: ['check_out_time'],
  }
);

export type GuestEntryFormData = z.infer<typeof guestEntrySchema>;
export type GuestFormData = z.infer<typeof guestSchema>;

