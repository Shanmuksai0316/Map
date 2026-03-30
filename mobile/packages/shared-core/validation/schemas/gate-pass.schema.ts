import { z } from 'zod';

/**
 * Gate Pass (OutPass) Request Schema
 * 
 * Fields:
 * - reason: Type of outpass (normal, leave, sick)
 * - overnight: Whether student needs to stay overnight
 * - valid_until: Optional expiry time (default: 8 hours from request)
 * - note: Optional note for the request
 */
export const gatePassSchema = z.object({
  reason: z.enum(['normal', 'leave', 'sick'], {
    required_error: 'Please select a reason',
  }),
  overnight: z.boolean().default(false),
  valid_until: z.date().optional(),
  note: z
    .string()
    .max(500, 'Note must not exceed 500 characters')
    .optional(),
}).refine(
  (data) => {
    // If valid_until is provided, ensure it's in the future
    if (data.valid_until) {
      return data.valid_until > new Date();
    }
    return true;
  },
  {
    message: 'Valid until date must be in the future',
    path: ['valid_until'],
  }
);

export type GatePassFormData = z.infer<typeof gatePassSchema>;

/**
 * Reason options for the form picker
 */
export const GATE_PASS_REASONS = [
  { value: 'normal', label: 'Normal Outing', description: 'Market, personal errands, etc.' },
  { value: 'leave', label: 'Leave', description: 'Going home or extended leave' },
  { value: 'sick', label: 'Medical', description: 'Medical appointment or emergency' },
] as const;
