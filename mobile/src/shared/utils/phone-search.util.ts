export const normalizePhoneDigits = (value?: string | null): string =>
  (value || '').replace(/\D/g, '');

export const matchesPhoneQuery = (phone: string | undefined, query: string): boolean => {
  if (!query.trim()) return false;
  const normalizedQuery = normalizePhoneDigits(query);
  if (!normalizedQuery) return false;
  return normalizePhoneDigits(phone).includes(normalizedQuery);
};
