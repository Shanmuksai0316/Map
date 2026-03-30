import { describe, it, expect } from '@jest/globals';
import {
  leaveSchema,
  sickLeaveSchema,
  gatePassSchema,
  guestEntrySchema,
  roomChangeSchema,
  ticketSchema,
} from '../schemas';

describe('Validation Schemas', () => {
  describe('leaveSchema', () => {
    it('should validate a valid leave request', () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      const dayAfter = new Date();
      dayAfter.setDate(dayAfter.getDate() + 2);

      const validData = {
        reason_for_leave: 'Family emergency - need to attend',
        from_date: tomorrow,
        to_date: dayAfter,
        emergency_contact: '9876543210',
      };

      const result = leaveSchema.safeParse(validData);
      expect(result.success).toBe(true);
    });

    it('should reject leave request with reason less than 10 characters', () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      const dayAfter = new Date();
      dayAfter.setDate(dayAfter.getDate() + 2);

      const invalidData = {
        reason_for_leave: 'Short',
        from_date: tomorrow,
        to_date: dayAfter,
      };

      const result = leaveSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
      if (!result.success && result.error.errors && result.error.errors.length > 0) {
        expect(result.error.errors[0].message).toContain('10 characters');
      }
    });

    it('should reject leave request with past from_date', () => {
      const invalidData = {
        reason_for_leave: 'Valid reason for leave request',
        from_date: new Date('2020-01-01'),
        to_date: new Date('2024-12-25'),
      };

      const result = leaveSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });

    it('should reject leave request where to_date is before from_date', () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 2);
      const dayAfter = new Date();
      dayAfter.setDate(dayAfter.getDate() + 1);

      const invalidData = {
        reason_for_leave: 'Valid reason for leave request',
        from_date: tomorrow,
        to_date: dayAfter,
      };

      const result = leaveSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });

    it('should reject invalid phone number format', () => {
      const invalidData = {
        reason_for_leave: 'Valid reason for leave request',
        from_date: new Date('2024-12-20'),
        to_date: new Date('2024-12-25'),
        emergency_contact: '12345',
      };

      const result = leaveSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });
  });

  describe('sickLeaveSchema', () => {
    it('should validate a valid sick leave request', () => {
      const validData = {
        illness: 'Fever',
        illness_details: 'High fever with body ache and headache for the past two days',
        need_medical_attention: true,
        contact_parents: false,
      };

      const result = sickLeaveSchema.safeParse(validData);
      expect(result.success).toBe(true);
    });

    it('should reject sick leave with illness less than 3 characters', () => {
      const invalidData = {
        illness: 'Fe', // Less than 3 characters
        illness_details: 'Valid illness details with more than 20 characters',
        need_medical_attention: false,
        contact_parents: false,
      };

      const result = sickLeaveSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
      if (!result.success && result.error.errors && result.error.errors.length > 0) {
        expect(result.error.errors[0].message).toContain('3 characters');
      }
    });

    it('should reject sick leave with details less than 20 characters', () => {
      const invalidData = {
        illness: 'Fever',
        illness_details: 'Short details',
        need_medical_attention: false,
        contact_parents: false,
      };

      const result = sickLeaveSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });
  });

  describe('gatePassSchema', () => {
    it('should validate a valid gate pass request', () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      const dayAfter = new Date();
      dayAfter.setDate(dayAfter.getDate() + 2);

      const validData = {
        purpose: 'Medical appointment at hospital',
        out_date: tomorrow,
        out_time: new Date('2024-12-20T10:00:00'),
        expected_in_date: dayAfter,
        expected_in_time: new Date('2024-12-21T18:00:00'),
      };

      const result = gatePassSchema.safeParse(validData);
      expect(result.success).toBe(true);
    });

    it('should reject gate pass with purpose less than 5 characters', () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);

      const invalidData = {
        purpose: 'Med',
        out_date: tomorrow,
        out_time: new Date('2024-12-20T10:00:00'),
        expected_in_date: tomorrow,
        expected_in_time: new Date('2024-12-20T18:00:00'),
      };

      const result = gatePassSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });

    it('should reject gate pass with past out_date', () => {
      const invalidData = {
        purpose: 'Valid purpose for gate pass',
        out_date: new Date('2020-01-01'),
        out_time: new Date('2024-12-20T10:00:00'),
        expected_in_date: new Date('2024-12-20'),
        expected_in_time: new Date('2024-12-20T18:00:00'),
      };

      const result = gatePassSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });

    it('should reject gate pass where expected_in is before out', () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);

      const invalidData = {
        purpose: 'Valid purpose for gate pass',
        out_date: tomorrow,
        out_time: new Date('2024-12-20T18:00:00'),
        expected_in_date: tomorrow,
        expected_in_time: new Date('2024-12-20T10:00:00'),
      };

      const result = gatePassSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });
  });

  describe('guestEntrySchema', () => {
    it('should validate a valid guest entry request', () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);

      const validData = {
        guests: [
          {
            name: 'John Doe',
            relationship: 'Father',
            phone: '9876543210',
            id_type: 'aadhar_card',
            id_number: '123456789012',
          },
        ],
        primary_contact_mobile: '9876543210',
        visit_date: tomorrow,
        check_in_time: new Date('2024-12-20T10:00:00'),
        check_out_time: new Date('2024-12-20T18:00:00'),
        purpose_to_visit: 'Visiting student for family function',
      };

      const result = guestEntrySchema.safeParse(validData);
      expect(result.success).toBe(true);
    });

    it('should reject guest entry with empty guests array', () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);

      const invalidData = {
        guests: [],
        primary_contact_mobile: '9876543210',
        visit_date: tomorrow,
        check_in_time: new Date('2024-12-20T10:00:00'),
        check_out_time: new Date('2024-12-20T18:00:00'),
        purpose_to_visit: 'Valid purpose for visit',
      };

      const result = guestEntrySchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });

    it('should reject guest entry with invalid phone number', () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);

      const invalidData = {
        guests: [
          {
            name: 'John Doe',
            relationship: 'Father',
            id_type: 'aadhar_card',
            id_number: '123456789012',
          },
        ],
        primary_contact_mobile: '12345',
        visit_date: tomorrow,
        check_in_time: new Date('2024-12-20T10:00:00'),
        check_out_time: new Date('2024-12-20T18:00:00'),
        purpose_to_visit: 'Valid purpose for visit',
      };

      const result = guestEntrySchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });

    it('should reject guest entry where check_out is before check_in', () => {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);

      const invalidData = {
        guests: [
          {
            name: 'John Doe',
            relationship: 'Father',
            id_type: 'aadhar_card',
            id_number: '123456789012',
          },
        ],
        primary_contact_mobile: '9876543210',
        visit_date: tomorrow,
        check_in_time: new Date('2024-12-20T18:00:00'),
        check_out_time: new Date('2024-12-20T10:00:00'),
        purpose_to_visit: 'Valid purpose for visit',
      };

      const result = guestEntrySchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });
  });

  describe('roomChangeSchema', () => {
    it('should validate a valid room change request', () => {
      const futureDate = new Date();
      futureDate.setDate(futureDate.getDate() + 5);

      const validData = {
        description: 'Need to change room due to noise issues and better accommodation requirements',
        preferred_room_number: '101',
        preferred_floor: '1',
        sharing_preference: 'single' as const,
        date_required: futureDate,
      };

      const result = roomChangeSchema.safeParse(validData);
      expect(result.success).toBe(true);
    });

    it('should validate room change with optional fields empty', () => {
      const validData = {
        description: 'Need to change room due to noise issues',
      };

      const result = roomChangeSchema.safeParse(validData);
      expect(result.success).toBe(true);
    });

    it('should reject room change with description less than 10 characters', () => {
      const invalidData = {
        description: 'Short',
      };

      const result = roomChangeSchema.safeParse(invalidData);
      // Description is required and must be at least 10 characters
      expect(result.success).toBe(false);
      if (!result.success && result.error.errors && result.error.errors.length > 0) {
        expect(result.error.errors[0].message).toContain('10 characters');
      }
    });

    it('should reject room change with past date_required', () => {
      const invalidData = {
        description: 'Valid description with more than 10 characters',
        date_required: new Date('2020-01-01'),
      };

      const result = roomChangeSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });
  });

  describe('ticketSchema', () => {
    it('should validate a valid ticket request', () => {
      const validData = {
        title: 'Broken window',
        request_type: 'repair_maintenance',
        issue: 'Broken window',
        description: 'Window in room 101 is broken and needs immediate repair',
      };

      const result = ticketSchema.safeParse(validData);
      expect(result.success).toBe(true);
    });

    it('should validate ticket with optional photos', () => {
      const validData = {
        title: 'Cleaning required',
        request_type: 'housekeeping',
        issue: 'Cleaning required',
        description: 'Room needs deep cleaning and sanitization',
        photos: ['photo1.jpg', 'photo2.jpg'],
      };

      const result = ticketSchema.safeParse(validData);
      expect(result.success).toBe(true);
    });

    it('should reject ticket with invalid request_type', () => {
      const invalidData = {
        title: 'Valid title',
        request_type: 'invalid_type' as any,
        issue: 'Valid issue',
        description: 'Valid description with more than 10 characters',
      };

      const result = ticketSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });

    it('should reject ticket with issue less than 1 character', () => {
      const invalidData = {
        title: 'Valid title',
        request_type: 'repair_maintenance',
        issue: '',
        description: 'Valid description with more than 10 characters',
      };

      const result = ticketSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });

    it('should reject ticket with description less than 10 characters', () => {
      const invalidData = {
        title: 'Valid title',
        request_type: 'repair_maintenance',
        issue: 'Valid issue',
        description: 'Short',
      };

      const result = ticketSchema.safeParse(invalidData);
      expect(result.success).toBe(false);
    });
  });
});

