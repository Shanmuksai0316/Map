import { getGreeting } from '../greeting.util';

describe('getGreeting', () => {
  it('returns Good Morning for IST mornings', () => {
    const sample = new Date(Date.UTC(2025, 0, 1, 2, 0)); // 07:30 IST
    expect(getGreeting(sample)).toBe('Good Morning');
  });

  it('returns Good Afternoon for IST afternoons', () => {
    const sample = new Date(Date.UTC(2025, 0, 1, 8, 0)); // 13:30 IST
    expect(getGreeting(sample)).toBe('Good Afternoon');
  });

  it('returns Good Evening for IST evenings', () => {
    const sample = new Date(Date.UTC(2025, 0, 1, 12, 30)); // 18:00 IST
    expect(getGreeting(sample)).toBe('Good Evening');
  });

  it('returns Good Night otherwise', () => {
    const sample = new Date(Date.UTC(2025, 0, 1, 19, 0)); // 00:30 IST next day
    expect(getGreeting(sample)).toBe('Good Night');
  });
});

