import { errorHandler } from '../errorHandler';
import { AxiosError } from 'axios';

// Create proper AxiosError instances for testing
// Note: We need to use Object.create to properly mock AxiosError
function createAxiosError(response?: any, code?: string): any {
  const error = Object.create(AxiosError.prototype);
  error.message = 'Axios Error';
  error.isAxiosError = true;
  error.response = response;
  error.code = code;
  return error;
}

describe('Error Handler', () => {
  describe('handleError', () => {
    it('should handle network errors', () => {
      const error = {
        message: 'Network Error',
        code: 'ERR_NETWORK',
      };
      const result = errorHandler.handleError(error);
      expect(result.message).toContain('network');
      expect(result.isNetworkError).toBe(true);
    });

    it('should handle API errors with status code', () => {
      const error = createAxiosError({
        status: 400,
        data: {
          message: 'Validation failed',
        },
      });
      const result = errorHandler.handleError(error);
      expect(result.message).toBe('Validation failed');
      expect(result.isNetworkError).toBe(false);
      expect(result.isApiError).toBe(true);
    });

    it('should handle API errors with default message', () => {
      const error = createAxiosError({
        status: 500,
        data: {},
      });
      const result = errorHandler.handleError(error);
      expect(result.message).toContain('server');
      expect(result.isNetworkError).toBe(false);
      expect(result.isApiError).toBe(true);
    });

    it('should handle validation errors', () => {
      const error = createAxiosError({
        status: 422,
        data: {
          errors: {
            email: ['Email is required'],
          },
        },
      });
      const result = errorHandler.handleError(error);
      expect(result.message).toContain('Validation failed');
      expect(result.isNetworkError).toBe(false);
      expect(result.isApiError).toBe(true);
    });

    it('should handle unknown errors', () => {
      const error = new Error('Unknown error');
      const result = errorHandler.handleError(error);
      expect(result.message).toBeTruthy();
      expect(typeof result.message).toBe('string');
    });
  });

  describe('isNetworkError', () => {
    it('should identify network errors', () => {
      const error = {
        message: 'Network Error',
        code: 'ERR_NETWORK',
      };
      const result = errorHandler.handleError(error);
      expect(result.isNetworkError).toBe(true);
    });

    it('should identify non-network errors', () => {
      const error = createAxiosError({
        status: 400,
      });
      const result = errorHandler.handleError(error);
      expect(result.isNetworkError).toBe(false);
      expect(result.isApiError).toBe(true);
    });
  });

  describe('isAPIError', () => {
    it('should identify API errors', () => {
      const error = createAxiosError({
        status: 400,
      });
      const result = errorHandler.handleError(error);
      expect(result.isApiError).toBe(true);
      expect(result.isNetworkError).toBe(false);
    });

    it('should identify non-API errors', () => {
      const error = {
        message: 'Network Error',
      };
      const result = errorHandler.handleError(error);
      expect(result.isNetworkError).toBe(true);
      expect(result.isApiError).toBe(false);
    });
  });

  describe('isValidationError', () => {
    it('should identify validation errors', () => {
      const error = createAxiosError({
        status: 400,
        data: {
          errors: {},
        },
      });
      const result = errorHandler.handleError(error);
      expect(result.message).toBeTruthy();
      expect(result.isValidationError).toBe(true);
      expect(result.isApiError).toBe(true);
    });

    it('should identify non-validation errors', () => {
      const error = createAxiosError({
        status: 500,
      });
      const result = errorHandler.handleError(error);
      expect(result.isValidationError).toBe(false);
      expect(result.isApiError).toBe(true);
    });
  });
});

