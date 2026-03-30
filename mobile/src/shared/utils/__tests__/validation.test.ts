import {
  validateEmail,
  validatePhone,
  validateRequired,
  validateMinLength,
  validateMaxLength,
  validateDateRange,
  validateFutureDate,
  validateTimeRange,
  validateAadhar,
  validateIDNumber,
  validateGatePass,
  sanitizeText,
  sanitizePhone,
  sanitizeIDNumber,
} from '../validation';

describe('Validation Utilities', () => {
  describe('validateEmail', () => {
    it('should return valid for correct email', () => {
      const result = validateEmail('test@example.com');
      expect(result.isValid).toBe(true);
      expect(result.error).toBeUndefined();
    });

    it('should return invalid for empty email', () => {
      const result = validateEmail('');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Email is required');
    });

    it('should return invalid for invalid email format', () => {
      const result = validateEmail('invalid-email');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Please enter a valid email address');
    });
  });

  describe('validatePhone', () => {
    it('should return valid for 10-digit phone', () => {
      const result = validatePhone('1234567890');
      expect(result.isValid).toBe(true);
    });

    it('should return invalid for empty phone', () => {
      const result = validatePhone('');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Phone number is required');
    });

    it('should return invalid for non-10-digit phone', () => {
      const result = validatePhone('12345');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Phone number must be 10 digits');
    });

    it('should clean phone with non-digits', () => {
      const result = validatePhone('123-456-7890');
      expect(result.isValid).toBe(true);
    });
  });

  describe('validateRequired', () => {
    it('should return valid for non-empty string', () => {
      const result = validateRequired('test', 'Field');
      expect(result.isValid).toBe(true);
    });

    it('should return invalid for empty string', () => {
      const result = validateRequired('', 'Field');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Field is required');
    });

    it('should return invalid for whitespace-only string', () => {
      const result = validateRequired('   ', 'Field');
      expect(result.isValid).toBe(false);
    });
  });

  describe('validateMinLength', () => {
    it('should return valid for string meeting minimum length', () => {
      const result = validateMinLength('test string', 5, 'Field');
      expect(result.isValid).toBe(true);
    });

    it('should return invalid for string below minimum length', () => {
      const result = validateMinLength('test', 10, 'Field');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Field must be at least 10 characters');
    });
  });

  describe('validateMaxLength', () => {
    it('should return valid for string within maximum length', () => {
      const result = validateMaxLength('test', 10, 'Field');
      expect(result.isValid).toBe(true);
    });

    it('should return invalid for string exceeding maximum length', () => {
      const result = validateMaxLength('test string', 5, 'Field');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Field must not exceed 5 characters');
    });
  });

  describe('validateDateRange', () => {
    it('should return valid when to_date is after from_date', () => {
      const fromDate = new Date('2024-01-01');
      const toDate = new Date('2024-01-02');
      const result = validateDateRange(fromDate, toDate);
      expect(result.isValid).toBe(true);
    });

    it('should return invalid when to_date is before from_date', () => {
      const fromDate = new Date('2024-01-02');
      const toDate = new Date('2024-01-01');
      const result = validateDateRange(fromDate, toDate);
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('End date must be after start date');
    });
  });

  describe('validateFutureDate', () => {
    it('should return valid for future date', () => {
      const futureDate = new Date();
      futureDate.setDate(futureDate.getDate() + 1);
      const result = validateFutureDate(futureDate, 'Date');
      expect(result.isValid).toBe(true);
    });

    it('should return invalid for past date', () => {
      const pastDate = new Date('2020-01-01');
      const result = validateFutureDate(pastDate, 'Date');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Date must be a future date');
    });
  });

  describe('validateTimeRange', () => {
    it('should return valid when check-out is after check-in', () => {
      const checkIn = new Date('2024-01-01T10:00:00');
      const checkOut = new Date('2024-01-01T12:00:00');
      const result = validateTimeRange(checkIn, checkOut);
      expect(result.isValid).toBe(true);
    });

    it('should return invalid when check-out is before check-in', () => {
      const checkIn = new Date('2024-01-01T12:00:00');
      const checkOut = new Date('2024-01-01T10:00:00');
      const result = validateTimeRange(checkIn, checkOut);
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Check-out time must be after check-in time');
    });
  });

  describe('validateAadhar', () => {
    it('should return valid for 12-digit Aadhar', () => {
      const result = validateAadhar('123456789012');
      expect(result.isValid).toBe(true);
    });

    it('should return invalid for non-12-digit Aadhar', () => {
      const result = validateAadhar('12345');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Aadhar number must be 12 digits');
    });
  });

  describe('validateIDNumber', () => {
    it('should return valid for correct Aadhar', () => {
      const result = validateIDNumber('aadhar_card', '123456789012');
      expect(result.isValid).toBe(true);
    });

    it('should return invalid for incorrect Aadhar length', () => {
      const result = validateIDNumber('aadhar_card', '12345');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Aadhar number must be 12 digits');
    });

    it('should return valid for correct Driving License', () => {
      const result = validateIDNumber('driving_license', 'DL12345678');
      expect(result.isValid).toBe(true);
    });

    it('should return invalid for incorrect Driving License length', () => {
      const result = validateIDNumber('driving_license', 'DL123');
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Driving license number must be 8-16 characters');
    });
  });

  describe('validateGatePass', () => {
    it('should return valid for correct gate pass', () => {
      const outDate = new Date();
      outDate.setDate(outDate.getDate() + 1);
      const outTime = new Date(outDate);
      outTime.setHours(10, 0, 0, 0);
      
      const expectedInDate = new Date(outDate);
      const expectedInTime = new Date(expectedInDate);
      expectedInTime.setHours(12, 0, 0, 0);

      const result = validateGatePass('Purpose', outDate, outTime, expectedInDate, expectedInTime);
      expect(result.isValid).toBe(true);
    });

    it('should return invalid for empty purpose', () => {
      const result = validateGatePass('', new Date(), new Date(), new Date(), new Date());
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Purpose is required');
    });

    it('should return invalid when expected return is before departure', () => {
      const outDate = new Date();
      outDate.setDate(outDate.getDate() + 1);
      const outTime = new Date(outDate);
      outTime.setHours(12, 0, 0, 0);
      
      const expectedInDate = new Date(outDate);
      const expectedInTime = new Date(expectedInDate);
      expectedInTime.setHours(10, 0, 0, 0);

      const result = validateGatePass('Purpose', outDate, outTime, expectedInDate, expectedInTime);
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('Expected return date/time must be after departure date/time');
    });
  });

  describe('sanitizeText', () => {
    it('should trim whitespace', () => {
      const result = sanitizeText('  test  ');
      expect(result).toBe('test');
    });

    it('should handle empty string', () => {
      const result = sanitizeText('');
      expect(result).toBe('');
    });
  });

  describe('sanitizePhone', () => {
    it('should remove non-digits', () => {
      const result = sanitizePhone('123-456-7890');
      expect(result).toBe('1234567890');
    });

    it('should handle phone with spaces', () => {
      const result = sanitizePhone('123 456 7890');
      expect(result).toBe('1234567890');
    });
  });

  describe('sanitizeIDNumber', () => {
    it('should remove spaces', () => {
      const result = sanitizeIDNumber('1234 5678 9012');
      expect(result).toBe('123456789012');
    });

    it('should handle ID without spaces', () => {
      const result = sanitizeIDNumber('123456789012');
      expect(result).toBe('123456789012');
    });
  });
});

