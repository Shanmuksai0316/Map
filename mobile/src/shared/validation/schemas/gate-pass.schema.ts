import { z } from 'zod';

// Updated outpass reasons (removed 'leave' and 'sick', added 'medical' and 'urgent')
export const OUTPASS_REASONS = [
  { value: 'normal', label: 'Normal' },
  { value: 'medical', label: 'Medical' },
  { value: 'urgent', label: 'Urgent' },
] as const;

// Legacy export for backward compatibility
export const GATE_PASS_REASONS = OUTPASS_REASONS;

// Updated schema: removed overnight, valid_until, note; added required_date
export const outpassSchema = z.object({
  reason: z
    .enum(['normal', 'medical', 'urgent'], {
      required_error: 'Please select a reason',
    }),
  required_date: z.date({
    required_error: 'Required date is mandatory',
  }),
});

// Legacy export for backward compatibility
export const gatePassSchema = outpassSchema;

export type OutpassFormData = z.infer<typeof outpassSchema>;
export type GatePassFormData = OutpassFormData; // Legacy export
