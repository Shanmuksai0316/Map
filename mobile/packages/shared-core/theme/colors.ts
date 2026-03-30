/**
 * MAP-HMS Design System v1.0
 * Color definitions for React Native mobile apps
 * 
 * Brand Colors:
 * - Primary: Military Green (#2F4F2F) - Discipline, trust, operations, safety
 * - Accent: Golden Yellow (#D4AF37) - Excellence, value, leadership
 * - Background: Light Gray (#F2F2F2) - Soft, readable, professional
 */

export const colors = {
  // ═══════════════════════════════════════════════════════════════
  // PRIMARY - Military Green (Client Brand Color)
  // ═══════════════════════════════════════════════════════════════
  primary: '#2F4F2F',           // Main branding, headers, primary buttons
  primaryDark: '#1A3A1A',       // Hover/pressed states
  primaryLight: '#4A6B4A',      // Secondary elements, borders
  primaryMuted: '#5C7A5C',      // Disabled states
  
  // ═══════════════════════════════════════════════════════════════
  // ACCENT - Golden Yellow (Client Brand Color)
  // ═══════════════════════════════════════════════════════════════
  accent: '#D4AF37',            // CTAs, highlights, key metrics, badges
  accentDark: '#B8962D',        // Hover state for accent
  accentLight: '#E8C859',       // Light accent badges
  accentMuted: '#F5E6B8',       // Light accent backgrounds
  
  // ═══════════════════════════════════════════════════════════════
  // BACKGROUNDS - Light Gray (#F2F2F2) app background, white cards
  // ═══════════════════════════════════════════════════════════════
  background: '#F2F2F2',       // Main page background
  surface: '#FFFFFF',           // Card backgrounds
  surfaceElevated: '#FAFAFA',   // Elevated cards
  surfaceMuted: '#F3F4F6',      // Section separators, zebra rows
  
  // ═══════════════════════════════════════════════════════════════
  // TEXT COLORS (Optimized for readability)
  // ═══════════════════════════════════════════════════════════════
  textHeading: '#2F4F2F',       // Military Green for headings ✓
  text: '#374151',              // Neutral gray for body (better readability)
  textPrimary: '#2F4F2F',       // Alias for heading text
  textSecondary: '#6B7280',     // Secondary content, hints
  textMuted: '#9CA3AF',         // Placeholder text, disabled
  textOnPrimary: '#FFFFFF',     // Text on green backgrounds
  textOnAccent: '#1F2937',      // Text on golden backgrounds
  
  // ═══════════════════════════════════════════════════════════════
  // BORDERS & DIVIDERS
  // ═══════════════════════════════════════════════════════════════
  border: '#E5E7EB',            // Default borders
  borderFocused: '#2F4F2F',     // Focus rings (Military Green)
  divider: '#F3F4F6',           // Subtle dividers
  
  // ═══════════════════════════════════════════════════════════════
  // STATUS COLORS (Distinct from brand colors)
  // ═══════════════════════════════════════════════════════════════
  success: '#059669',           // Teal-green (approved, completed)
  successLight: '#D1FAE5',      // Success backgrounds
  warning: '#D97706',           // Orange (pending - NOT golden yellow)
  warningLight: '#FEF3C7',      // Warning backgrounds
  error: '#DC2626',             // Red (rejected, errors)
  errorLight: '#FEE2E2',        // Error backgrounds
  info: '#0284C7',              // Blue (information)
  infoLight: '#E0F2FE',         // Info backgrounds
  
  // ═══════════════════════════════════════════════════════════════
  // UTILITY COLORS
  // ═══════════════════════════════════════════════════════════════
  white: '#FFFFFF',
  black: '#000000',
  gray: '#6B7280',              // General gray
  transparent: 'transparent',
};

/**
 * Typography configuration
 */
export const typography = {
  title: {
    fontSize: 24,
    lineHeight: 32,
    fontWeight: '700' as const,
    color: colors.textHeading,
  },
  subtitle: {
    fontSize: 20,
    lineHeight: 28,
    fontWeight: '600' as const,
    color: colors.textHeading,
  },
  body: {
    fontSize: 16,
    lineHeight: 24,
    fontWeight: '400' as const,
    color: colors.text,
  },
  small: {
    fontSize: 14,
    lineHeight: 20,
    fontWeight: '500' as const,
    color: colors.text,
  },
  caption: {
    fontSize: 12,
    lineHeight: 16,
    fontWeight: '500' as const,
    color: colors.textSecondary,
  },
};

export type ColorKey = keyof typeof colors;
export type Colors = typeof colors;
