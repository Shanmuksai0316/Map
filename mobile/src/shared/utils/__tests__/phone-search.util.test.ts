import { matchesPhoneQuery, normalizePhoneDigits } from '../phone-search.util';

describe('phone-search.util', () => {
  it('normalizes phone digits by stripping formatting', () => {
    expect(normalizePhoneDigits('+91 98765-43210')).toBe('919876543210');
  });

  it('matches digit-only query against formatted phone value', () => {
    expect(matchesPhoneQuery('+91 98765 43210', '987654')).toBe(true);
  });

  it('matches formatted query against formatted phone value', () => {
    expect(matchesPhoneQuery('+91 98765 43210', '+91 9876')).toBe(true);
  });

  it('returns false for empty queries', () => {
    expect(matchesPhoneQuery('+91 98765 43210', '   ')).toBe(false);
  });
});
