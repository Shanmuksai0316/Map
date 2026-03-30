import { debounce, throttle } from '../performance';

describe('Performance Utilities', () => {
  describe('debounce', () => {
    jest.useFakeTimers();

    it('should delay function execution', () => {
      const func = jest.fn();
      const debouncedFunc = debounce(func, 100);

      debouncedFunc();
      expect(func).not.toHaveBeenCalled();

      jest.advanceTimersByTime(100);
      expect(func).toHaveBeenCalledTimes(1);
    });

    it('should cancel previous calls', () => {
      const func = jest.fn();
      const debouncedFunc = debounce(func, 100);

      debouncedFunc();
      debouncedFunc();
      debouncedFunc();

      jest.advanceTimersByTime(100);
      expect(func).toHaveBeenCalledTimes(1);
    });

    afterEach(() => {
      jest.clearAllTimers();
    });
  });

  describe('throttle', () => {
    jest.useFakeTimers();

    it('should limit function calls', () => {
      const func = jest.fn();
      const throttledFunc = throttle(func, 100);

      throttledFunc();
      expect(func).toHaveBeenCalledTimes(1);

      throttledFunc();
      expect(func).toHaveBeenCalledTimes(1);

      jest.advanceTimersByTime(100);
      throttledFunc();
      expect(func).toHaveBeenCalledTimes(2);
    });

    afterEach(() => {
      jest.clearAllTimers();
    });
  });
});

