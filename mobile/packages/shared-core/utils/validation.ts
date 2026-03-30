/**
 * Form Validation Utilities
 * Provides comprehensive validation functions for form inputs
 */

export interface ValidationResult {
  isValid: boolean;
  error?: string;
}

/**
 * Validates email format
 */
export const validateEmail = (email: string): ValidationResult => {
  if (!email || !email.trim()) {
    return { isValid: false, error: 'Email is required' };
  }
  
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email.trim())) {
    return { isValid: false, error: 'Please enter a valid email address' };
  }
  
  return { isValid: true };
};

/**
 * Validates phone number (10 digits)
 */
export const validatePhone = (phone: string): ValidationResult => {
  if (!phone || !phone.trim()) {
    return { isValid: false, error: 'Phone number is required' };
  }
  
  const phoneRegex = /^[0-9]{10}$/;
  const cleaned = phone.replace(/\D/g, '');
  
  if (cleaned.length !== 10) {
    return { isValid: false, error: 'Phone number must be 10 digits' };
  }
  
  return { isValid: true };
};

/**
 * Validates required text field
 */
export const validateRequired = (value: string, fieldName: string): ValidationResult => {
  if (!value || !value.trim()) {
    return { isValid: false, error: `${fieldName} is required` };
  }
  
  return { isValid: true };
};

/**
 * Validates minimum length
 */
export const validateMinLength = (value: string, minLength: number, fieldName: string): ValidationResult => {
  if (!value || value.trim().length < minLength) {
    return { isValid: false, error: `${fieldName} must be at least ${minLength} characters` };
  }
  
  return { isValid: true };
};

/**
 * Validates maximum length
 */
export const validateMaxLength = (value: string, maxLength: number, fieldName: string): ValidationResult => {
  if (value && value.length > maxLength) {
    return { isValid: false, error: `${fieldName} must not exceed ${maxLength} characters` };
  }
  
  return { isValid: true };
};

/**
 * Validates date range (from_date must be before to_date)
 */
export const validateDateRange = (fromDate: Date, toDate: Date): ValidationResult => {
  if (fromDate >= toDate) {
    return { isValid: false, error: 'End date must be after start date' };
  }
  
  return { isValid: true };
};

/**
 * Validates future date
 */
export const validateFutureDate = (date: Date, fieldName: string): ValidationResult => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  
  if (date < today) {
    return { isValid: false, error: `${fieldName} must be a future date` };
  }
  
  return { isValid: true };
};

/**
 * Validates time range (check-in must be before check-out)
 */
export const validateTimeRange = (checkIn: Date, checkOut: Date): ValidationResult => {
  if (checkIn >= checkOut) {
    return { isValid: false, error: 'Check-out time must be after check-in time' };
  }
  
  return { isValid: true };
};

/**
 * Validates Aadhar number (12 digits)
 */
export const validateAadhar = (aadhar: string): ValidationResult => {
  if (!aadhar || !aadhar.trim()) {
    return { isValid: false, error: 'Aadhar number is required' };
  }
  
  const cleaned = aadhar.replace(/\D/g, '');
  if (cleaned.length !== 12) {
    return { isValid: false, error: 'Aadhar number must be 12 digits' };
  }
  
  return { isValid: true };
};

/**
 * Validates ID number based on type
 */
export const validateIDNumber = (idType: string, idNumber: string): ValidationResult => {
  if (!idNumber || !idNumber.trim()) {
    return { isValid: false, error: 'ID number is required' };
  }
  
  const cleaned = idNumber.replace(/\s/g, '');
  
  switch (idType) {
    case 'aadhar_card':
      if (cleaned.length !== 12) {
        return { isValid: false, error: 'Aadhar number must be 12 digits' };
      }
      break;
    case 'driving_license':
      if (cleaned.length < 8 || cleaned.length > 16) {
        return { isValid: false, error: 'Driving license number must be 8-16 characters' };
      }
      break;
    case 'passport':
      if (cleaned.length < 8 || cleaned.length > 9) {
        return { isValid: false, error: 'Passport number must be 8-9 characters' };
      }
      break;
    case 'voter_id':
      if (cleaned.length !== 10) {
        return { isValid: false, error: 'Voter ID must be 10 characters' };
      }
      break;
  }
  
  return { isValid: true };
};

/**
 * Sanitizes text input (removes leading/trailing whitespace)
 */
export const sanitizeText = (text: string): string => {
  return text.trim();
};

/**
 * Sanitizes phone number (removes non-digits)
 */
export const sanitizePhone = (phone: string): string => {
  return phone.replace(/\D/g, '');
};

/**
 * Sanitizes ID number (removes spaces)
 */
export const sanitizeIDNumber = (idNumber: string): string => {
  return idNumber.replace(/\s/g, '');
};

/**
 * Validates gate pass dates and times
 */
export const validateGatePass = (
  purpose: string,
  outDate: Date,
  outTime: Date,
  expectedInDate: Date,
  expectedInTime: Date
): ValidationResult => {
  // Validate purpose
  const purposeValidation = validateRequired(purpose, 'Purpose');
  if (!purposeValidation.isValid) {
    return purposeValidation;
  }
  
  // Validate dates
  const outDateTime = new Date(outDate);
  outDateTime.setHours(outTime.getHours(), outTime.getMinutes(), 0, 0);
  
  const expectedInDateTime = new Date(expectedInDate);
  expectedInDateTime.setHours(expectedInTime.getHours(), expectedInTime.getMinutes(), 0, 0);
  
  if (outDateTime >= expectedInDateTime) {
    return { isValid: false, error: 'Expected return date/time must be after departure date/time' };
  }
  
  // Validate future dates
  const now = new Date();
  if (outDateTime < now) {
    return { isValid: false, error: 'Departure date/time must be in the future' };
  }
  
  return { isValid: true };
};

