/**
 * MAP-HMS Shared UI Package
 * Exports all shared UI components, theme, and configurations
 */

// ═══════════════════════════════════════════════════════════════
// THEME
// ═══════════════════════════════════════════════════════════════
export * from './theme';

// ═══════════════════════════════════════════════════════════════
// ICONS
// ═══════════════════════════════════════════════════════════════
export * from './components/Icon';
export * from './components/icons';
export * from './config/icons.config';

// ═══════════════════════════════════════════════════════════════
// COMPONENTS
// ═══════════════════════════════════════════════════════════════
export { AnimatedTabIcon } from './components/AnimatedTabIcon';
export { BadgeIndicator } from './components/BadgeIndicator';
export { Calendar } from './components/Calendar';
export { Card, CardHeader, CardContent, CardFooter, CardActions } from './components/Card';
export { DatePicker } from './components/DatePicker';
export { EmptyState } from './components/EmptyState';
export { ErrorBoundary } from './components/ErrorBoundary';
export { ErrorState } from './components/ErrorState';
export { FormInput } from './components/FormInput';
export { LoadingState } from './components/LoadingState';
export { ScreenHeader } from './components/ScreenHeader';
export { SkeletonLoader } from './components/SkeletonLoader';
export { SLACountdownBadge } from './components/SLACountdownBadge';
export { SplashScreen } from './components/SplashScreen';
export { StatusBadge } from './components/StatusBadge';
export { OtpInputModal } from './components/OtpInputModal';
export { LazyScreenWrapper } from './components/LazyScreenWrapper';
export { OfflineSyncBanner } from './components/OfflineSyncBanner';
export { TapToRevealField } from './components/TapToRevealField';

// Skeleton Components
export { ComplaintSkeleton } from './components/ComplaintSkeleton';
export { GatePassSkeleton } from './components/GatePassSkeleton';

// Shared Components
export { KebabMenu } from './components/shared/KebabMenu';
export { OfflineIndicator } from './components/shared/OfflineIndicator';
export { QueueStatusBadge } from './components/shared/QueueStatusBadge';
export { RevealablePII } from './components/shared/RevealablePII';
export { ErrorState as SharedErrorState } from './components/shared/ErrorState';

// ═══════════════════════════════════════════════════════════════
// TYPES
// ═══════════════════════════════════════════════════════════════
export type { StatusType } from './components/StatusBadge';
export type { EmptyStateVariant } from './components/EmptyState';
export type { CardVariant } from './components/Card';
export type { IconProps, IconName, IconSize } from './components/Icon';
