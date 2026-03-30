/**
 * MAP-HMS Design System v1.0
 * Theme configuration for React Native mobile apps (Student & Staff)
 * 
 * Brand Colors:
 * - Primary: Military Green (#2F4F2F) - Discipline, trust, operations, safety
 * - Accent: Golden Yellow (#D4AF37) - Excellence, value, leadership
 * - Background: Pure White (#FFFFFF) - Clean, readable, professional
 */

export const theme = {
  colors: {
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
    // BACKGROUNDS - Pure White (Client Brand Color)
    // ═══════════════════════════════════════════════════════════════
    background: '#FFFFFF',        // Main page background
    surface: '#FFFFFF',           // Card backgrounds
    surfaceElevated: '#FAFAFA',   // Elevated cards
    surfaceMuted: '#F3F4F6',      // Section separators, zebra rows
    card: '#FFFFFF',              // Card component background
    
    // ═══════════════════════════════════════════════════════════════
    // TEXT COLORS (Optimized for readability)
    // ═══════════════════════════════════════════════════════════════
    textHeading: '#2F4F2F',       // Military Green for headings ✓
    text: '#374151',              // Neutral gray for body (better readability)
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
    // INTERACTIVE STATES
    // ═══════════════════════════════════════════════════════════════
    hover: 'rgba(47, 79, 47, 0.08)',    // Military Green tint
    pressed: 'rgba(47, 79, 47, 0.12)',  // Pressed state
    disabled: '#E5E7EB',                 // Disabled elements
    
    // ═══════════════════════════════════════════════════════════════
    // UTILITY COLORS
    // ═══════════════════════════════════════════════════════════════
    white: '#FFFFFF',
    black: '#000000',
    transparent: 'transparent',
  },
  
  // ═══════════════════════════════════════════════════════════════
  // SPACING (8px base unit)
  // ═══════════════════════════════════════════════════════════════
  spacing: {
    xxs: 2,    // Micro spacing (icon gaps)
    xs: 4,     // Tight spacing
    sm: 8,     // Element margins
    md: 16,    // Section padding
    lg: 24,    // Card padding
    xl: 32,    // Section gaps
    xxl: 48,   // Page sections
    xxxl: 64,  // Hero sections
  },
  
  // ═══════════════════════════════════════════════════════════════
  // BORDER RADIUS
  // ═══════════════════════════════════════════════════════════════
  borderRadius: {
    xs: 6,      // Small badges
    sm: 10,     // Buttons, inputs
    md: 14,     // Cards
    lg: 20,     // Modals, large cards
    xl: 24,     // Hero sections
    full: 9999, // Pills, avatars
  },
  
  // ═══════════════════════════════════════════════════════════════
  // FONT SIZES
  // ═══════════════════════════════════════════════════════════════
  fontSize: {
    xxs: 10,
    xs: 12,
    sm: 14,
    md: 16,
    lg: 18,
    xl: 20,
    xxl: 24,
    xxxl: 30,
    huge: 36,
    massive: 48,
  },

  // ═══════════════════════════════════════════════════════════════
  // FONT WEIGHTS
  // ═══════════════════════════════════════════════════════════════
  fontWeight: {
    light: '300' as const,
    regular: '400' as const,
    medium: '500' as const,
    semibold: '600' as const,
    bold: '700' as const,
    extrabold: '800' as const,
    black: '900' as const,
  },

  // ═══════════════════════════════════════════════════════════════
  // LINE HEIGHTS
  // ═══════════════════════════════════════════════════════════════
  lineHeight: {
    tight: 1.2,
    normal: 1.5,
    relaxed: 1.6,
    loose: 1.8,
  },

  // ═══════════════════════════════════════════════════════════════
  // TYPOGRAPHY VARIANTS
  // ═══════════════════════════════════════════════════════════════
  typography: {
    h1: {
      fontSize: 36,
      fontWeight: 'bold' as const,
      lineHeight: 1.2,
      letterSpacing: -0.5,
      color: '#2F4F2F', // Military Green for headings
    },
    h2: {
      fontSize: 30,
      fontWeight: 'bold' as const,
      lineHeight: 1.3,
      letterSpacing: -0.25,
      color: '#2F4F2F',
    },
    h3: {
      fontSize: 24,
      fontWeight: 'semibold' as const,
      lineHeight: 1.4,
      letterSpacing: 0,
      color: '#2F4F2F',
    },
    h4: {
      fontSize: 20,
      fontWeight: 'semibold' as const,
      lineHeight: 1.4,
      letterSpacing: 0.15,
      color: '#2F4F2F',
    },
    h5: {
      fontSize: 18,
      fontWeight: 'semibold' as const,
      lineHeight: 1.5,
      letterSpacing: 0,
      color: '#2F4F2F',
    },
    h6: {
      fontSize: 16,
      fontWeight: 'semibold' as const,
      lineHeight: 1.5,
      letterSpacing: 0.15,
      color: '#2F4F2F',
    },
    body1: {
      fontSize: 16,
      fontWeight: 'regular' as const,
      lineHeight: 1.6,
      letterSpacing: 0.5,
      color: '#374151', // Gray for body text (better readability)
    },
    body2: {
      fontSize: 14,
      fontWeight: 'regular' as const,
      lineHeight: 1.5,
      letterSpacing: 0.25,
      color: '#374151',
    },
    caption: {
      fontSize: 12,
      fontWeight: 'regular' as const,
      lineHeight: 1.4,
      letterSpacing: 0.4,
      color: '#6B7280',
    },
    overline: {
      fontSize: 12,
      fontWeight: 'medium' as const,
      lineHeight: 1.5,
      letterSpacing: 1.5,
      textTransform: 'uppercase' as const,
      color: '#6B7280',
    },
    button: {
      fontSize: 14,
      fontWeight: 'semibold' as const,
      lineHeight: 1.5,
      letterSpacing: 1.25,
      textTransform: 'uppercase' as const,
    },
    label: {
      fontSize: 16,
      fontWeight: 'medium' as const,
      lineHeight: 1.5,
      letterSpacing: 0.5,
      color: '#2F4F2F', // Military Green for labels
    },
  },
  
  // ═══════════════════════════════════════════════════════════════
  // SHADOWS
  // ═══════════════════════════════════════════════════════════════
  shadows: {
    none: {
      shadowColor: 'transparent',
      shadowOffset: { width: 0, height: 0 },
      shadowOpacity: 0,
      shadowRadius: 0,
      elevation: 0,
    },
    small: {
      shadowColor: '#000',
      shadowOffset: { width: 0, height: 2 },
      shadowOpacity: 0.1,
      shadowRadius: 4,
      elevation: 2,
    },
    medium: {
      shadowColor: '#000',
      shadowOffset: { width: 0, height: 4 },
      shadowOpacity: 0.15,
      shadowRadius: 8,
      elevation: 4,
    },
    large: {
      shadowColor: '#000',
      shadowOffset: { width: 0, height: 8 },
      shadowOpacity: 0.2,
      shadowRadius: 12,
      elevation: 8,
    },
  },
  
  // ═══════════════════════════════════════════════════════════════
  // COMPONENT-SPECIFIC CONFIGURATIONS
  // ═══════════════════════════════════════════════════════════════
  
  // Header/Navigation Bar
  header: {
    backgroundColor: '#2F4F2F',  // Military Green
    tintColor: '#FFFFFF',
    titleStyle: {
      fontWeight: 'bold' as const,
      fontSize: 20,
      color: '#FFFFFF',
    },
  },

  // Tab Bar
  tabBar: {
    backgroundColor: '#FFFFFF',
    activeTintColor: '#2F4F2F',      // Military Green
    inactiveTintColor: '#9CA3AF',
    label: {
      fontSize: 12,
      fontWeight: 'semibold' as const,
      letterSpacing: 0.5,
    },
    activeLabel: {
      fontSize: 12,
      fontWeight: 'bold' as const,
      letterSpacing: 0.5,
    },
  },
  
  // Cards
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  
  // Buttons
  button: {
    primary: {
      backgroundColor: '#2F4F2F',
      textColor: '#FFFFFF',
      borderRadius: 10,
      height: 48,
    },
    secondary: {
      backgroundColor: 'transparent',
      borderColor: '#2F4F2F',
      borderWidth: 1,
      textColor: '#2F4F2F',
      borderRadius: 10,
      height: 48,
    },
    accent: {
      backgroundColor: '#D4AF37',
      textColor: '#1F2937',
      borderRadius: 10,
      height: 48,
    },
  },
  
  // Inputs
  input: {
    height: 48,
    borderRadius: 10,
    borderColor: '#E5E7EB',
    borderColorFocused: '#2F4F2F',
    backgroundColor: '#FFFFFF',
    placeholderColor: '#9CA3AF',
    textColor: '#374151',
    labelColor: '#2F4F2F',
  },
  
  // Status Badges
  statusBadge: {
    approved: {
      backgroundColor: '#D1FAE5',
      textColor: '#059669',
      iconColor: '#059669',
    },
    pending: {
      backgroundColor: '#FEF3C7',
      textColor: '#D97706',
      iconColor: '#D97706',
    },
    rejected: {
      backgroundColor: '#FEE2E2',
      textColor: '#DC2626',
      iconColor: '#DC2626',
    },
    active: {
      backgroundColor: '#E0F2FE',
      textColor: '#0284C7',
      iconColor: '#0284C7',
    },
  },
};

export type Theme = typeof theme;
export type ThemeColors = typeof theme.colors;
export type ThemeSpacing = typeof theme.spacing;
