import { AxiosError } from 'axios';
import NetInfo from '@react-native-community/netinfo';

export interface ErrorDetails {
  message: string;
  code?: string;
  isNetworkError: boolean;
  isApiError: boolean;
  isValidationError: boolean;
  statusCode?: number;
  originalError?: any;
}

class ErrorHandler {
  /**
   * Check if error is a network error
   */
  isNetworkError(error: any): boolean {
    if (error instanceof AxiosError) {
      return !error.response && (error.code === 'ECONNABORTED' || error.code === 'ERR_NETWORK');
    }
    return error.message?.includes('Network') || error.message?.includes('network');
  }

  /**
   * Check if error is an API error (has response)
   */
  isApiError(error: any): boolean {
    return error instanceof AxiosError && !!error.response;
  }

  /**
   * Check if error is a validation error (400)
   */
  isValidationError(error: any): boolean {
    return this.isApiError(error) && error.response?.status === 400;
  }

  /**
   * Check if error is unauthorized (401)
   */
  isUnauthorized(error: any): boolean {
    return this.isApiError(error) && error.response?.status === 401;
  }

  /**
   * Check if error is forbidden (403)
   */
  isForbidden(error: any): boolean {
    return this.isApiError(error) && error.response?.status === 403;
  }

  /**
   * Check if error is not found (404)
   */
  isNotFound(error: any): boolean {
    return this.isApiError(error) && error.response?.status === 404;
  }

  /**
   * Check if error is server error (500+)
   */
  isServerError(error: any): boolean {
    return this.isApiError(error) && error.response?.status >= 500;
  }

  /**
   * Get user-friendly error message
   */
  getUserMessage(error: any): string {
    // Network errors
    if (this.isNetworkError(error)) {
      return 'No internet connection. Please check your network and try again.';
    }

    // API errors with response
    if (this.isApiError(error)) {
      const response = error.response;
      const status = response?.status;
      const data = response?.data;

      // Try to get message from API response
      if (data?.message) {
        return data.message;
      }

      if (data?.detail) {
        return data.detail;
      }

      if (data?.error) {
        return typeof data.error === 'string' ? data.error : data.error.message || 'An error occurred';
      }

      // Status code based messages
      switch (status) {
        case 400:
          return 'Invalid request. Please check your input and try again.';
        case 401:
          return 'Your session has expired. Please log in again.';
        case 403:
          return 'You do not have permission to perform this action.';
        case 404:
          return 'The requested resource was not found.';
        case 422:
          return 'Validation failed. Please check your input.';
        case 429:
          return 'Too many requests. Please try again later.';
        case 500:
        case 502:
        case 503:
        case 504:
          return 'Server error. Please try again later.';
        default:
          return 'An error occurred. Please try again.';
      }
    }

    // Generic error message
    if (error?.message) {
      return error.message;
    }

    return 'Something went wrong. Please try again.';
  }

  /**
   * Get error code from API response
   */
  getErrorCode(error: any): string | undefined {
    if (this.isApiError(error)) {
      return error.response?.data?.code || error.response?.data?.error?.code;
    }
    return undefined;
  }

  /**
   * Get full error details
   */
  getErrorDetails(error: any): ErrorDetails {
    return {
      message: this.getUserMessage(error),
      code: this.getErrorCode(error),
      isNetworkError: this.isNetworkError(error),
      isApiError: this.isApiError(error),
      isValidationError: this.isValidationError(error),
      statusCode: this.isApiError(error) ? error.response?.status : undefined,
      originalError: error,
    };
  }

  /**
   * Check network connectivity
   */
  async checkNetworkConnectivity(): Promise<boolean> {
    const netInfo = await NetInfo.fetch();
    return netInfo.isConnected ?? false;
  }

  /**
   * Handle error and return user-friendly message
   * This is the main method to use in components
   */
  handleError(error: any): ErrorDetails {
    const details = this.getErrorDetails(error);
    
    // Log error in development
    if (__DEV__) {
      console.error('Error handled:', {
        message: details.message,
        code: details.code,
        statusCode: details.statusCode,
        isNetworkError: details.isNetworkError,
        isApiError: details.isApiError,
        originalError: error,
      });
    }

    return details;
  }
}

export const errorHandler = new ErrorHandler();

