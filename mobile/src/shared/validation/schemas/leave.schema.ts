import { z } from 'zod';

export const leaveSchema = z.object({
  reason_for_leave: z
    .string()
    .min(1, 'Reason for leave is required')
    .min(10, 'Reason for leave must be at least 10 characters')
    .max(500, 'Reason for leave must not exceed 500 characters'),
  from_date: z
    .date({
      required_error: 'From date is required',
    })
    .refine(
      (date) => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return date >= today;
      },
      {
        message: 'From date must be today or in the future',
      }
    ),
  to_date: z.date({
    required_error: 'To date is required',
  }),
  emergency_contact: z
    .string()
    .regex(/^\d{10}$/, 'Emergency contact must be a valid 10-digit phone number')
    .optional()
    .or(z.literal('')),
}).refine(
  (data) => data.to_date >= data.from_date,
  {
    message: 'To date must be after or equal to from date',
    path: ['to_date'],
  }
);

export type LeaveFormData = z.infer<typeof leaveSchema>;

