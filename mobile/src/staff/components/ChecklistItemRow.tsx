/**
 * ChecklistItemRow Component
 * 
 * Displays a single checklist item with checkbox, notes, and photo upload
 */

import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  TextInput,
  Alert,
  Image,
  Linking,
} from 'react-native';
import { GradientButton } from '../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../shared/theme/theme';

interface ChecklistItem {
  id: number;
  code: string;
  label: string;
  state: 'pending' | 'done' | 'skipped';
  require_photo?: boolean;
  require_comment?: boolean;
  comment?: string;
  photo_url?: string;
}

interface ChecklistItemRowProps {
  item: ChecklistItem;
  onToggle: (itemCode: string, newState: 'pending' | 'done' | 'skipped') => void;
  onCommentChange: (itemCode: string, comment: string) => void;
  onPhotoUpload?: (itemCode: string) => void;
}

export const ChecklistItemRow: React.FC<ChecklistItemRowProps> = ({
  item,
  onToggle,
  onCommentChange,
  onPhotoUpload,
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const [comment, setComment] = useState(item.comment || '');

  const handleToggle = () => {
    // Backend accepts Done / NA only. "pending" is just the initial UI state.
    // If comment/photo is required, encourage providing it before marking done.
    if (item.require_comment && !comment.trim() && item.state !== 'done') {
      setIsExpanded(true);
      Alert.alert('Comment required', 'Please add notes before marking this item as done.');
      return;
    }

    if (item.require_photo && !item.photo_url && item.state !== 'done') {
      setIsExpanded(true);
      Alert.alert('Photo required', 'Please upload a photo (or mark as NA) before completing this item.');
      return;
    }

    const newState = item.state === 'done' ? 'skipped' : 'done';
    onToggle(item.code, newState);
  };

  const handleCommentChange = (text: string) => {
    setComment(text);
    onCommentChange(item.code, text);
  };

  const handlePhotoUpload = () => {
    if (onPhotoUpload) {
      onPhotoUpload(item.code);
    } else {
      Alert.alert('Photo Upload', 'Photo upload feature coming soon');
    }
  };

  const handleOpenPhoto = async () => {
    if (!item.photo_url) return;
    try {
      const canOpen = await Linking.canOpenURL(item.photo_url);
      if (canOpen) {
        await Linking.openURL(item.photo_url);
      } else {
        Alert.alert('Unable to open image', 'Photo URL is not supported on this device.');
      }
    } catch {
      Alert.alert('Unable to open image', 'Please try again.');
    }
  };

  const getStateColor = () => {
    switch (item.state) {
      case 'done':
        return theme.colors.success;
      case 'skipped':
        return theme.colors.warning;
      default:
        return theme.colors.textMuted;
    }
  };

  const getStateIcon = () => {
    switch (item.state) {
      case 'done':
        return 'checkmark-circle';
      case 'skipped':
        return 'remove-circle';
      default:
        return 'ellipse-outline';
    }
  };

  return (
    <View style={[styles.container, item.state === 'done' && styles.completedContainer]}>
      <TouchableOpacity
        style={styles.row}
        onPress={() => setIsExpanded(!isExpanded)}
        activeOpacity={0.7}
      >
        <TouchableOpacity
          style={styles.checkboxContainer}
          onPress={handleToggle}
          hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
        >
          <Ionicons
            name={getStateIcon()}
            size={28}
            color={getStateColor()}
          />
        </TouchableOpacity>

        <View style={styles.content}>
          <Text style={[styles.label, item.state === 'done' && styles.completedText]}>
            {item.label}
          </Text>
          {item.require_comment && !comment && item.state === 'done' && (
            <Text style={styles.requiredText}>Comment required</Text>
          )}
          {item.require_photo && !item.photo_url && item.state === 'done' && (
            <Text style={styles.requiredText}>Photo required</Text>
          )}
        </View>

        <Ionicons
          name={isExpanded ? 'chevron-up' : 'chevron-down'}
          size={20}
          color={theme.colors.textSecondary}
        />
      </TouchableOpacity>

      {isExpanded && (
        <View style={styles.expandedContent}>
          {(item.require_comment || comment) && (
            <View style={styles.commentContainer}>
              <Text style={styles.commentLabel}>
                Notes {item.require_comment && <Text style={styles.required}>*</Text>}
              </Text>
              <TextInput
                style={styles.commentInput}
                value={comment}
                onChangeText={handleCommentChange}
                placeholder="Add notes for this item..."
                placeholderTextColor={theme.colors.textMuted}
                multiline
                numberOfLines={3}
                editable={true}
              />
            </View>
          )}

          {item.require_photo && (
            <View>
              <GradientButton
                style={styles.photoButton}
                onPress={handlePhotoUpload}
              >
                <Ionicons
                  name={item.photo_url ? 'checkmark-circle' : 'camera-outline'}
                  size={20}
                  color={item.photo_url ? theme.colors.success : theme.colors.textSecondary}
                />
                <Text style={styles.photoButtonText}>
                  {item.photo_url ? 'Replace photo' : 'Upload photo'}
                </Text>
              </GradientButton>
              {item.photo_url ? (
                <GradientButton style={styles.photoPreviewButton} onPress={handleOpenPhoto}>
                  <Image source={{ uri: item.photo_url }} style={styles.photoPreview} />
                  <Text style={styles.photoPreviewText}>Tap to view full image</Text>
                </GradientButton>
              ) : null}
            </View>
          )}
        </View>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: theme.colors.white,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.lg,
    marginBottom: theme.spacing.sm,
    ...theme.shadows.medium,
  },
  completedContainer: {
    backgroundColor: '#F1F8E9',
    borderColor: theme.colors.success,
    borderWidth: 1,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.md,
  },
  checkboxContainer: {
    width: 32,
    height: 32,
    justifyContent: 'center',
    alignItems: 'center',
  },
  content: {
    flex: 1,
    marginRight: theme.spacing.sm,
  },
  label: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    lineHeight: 20,
  },
  completedText: {
    color: '#2E7D32',
    textDecorationLine: 'line-through',
  },
  requiredText: {
    fontSize: theme.fontSize.xs,
    color: theme.colors.error,
    marginTop: theme.spacing.xs,
    fontStyle: 'italic',
  },
  expandedContent: {
    marginTop: theme.spacing.md,
    paddingTop: theme.spacing.md,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  commentContainer: {
    marginBottom: theme.spacing.md,
  },
  commentLabel: {
    fontSize: theme.fontSize.sm,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.xs,
  },
  required: {
    color: theme.colors.error,
  },
  commentInput: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.borderRadius.md,
    padding: theme.spacing.sm,
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
    minHeight: 80,
    textAlignVertical: 'top',
    backgroundColor: theme.colors.white,
  },
  photoButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    padding: theme.spacing.sm,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  photoButtonText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.text,
  },
  photoPreviewButton: {
    marginTop: theme.spacing.sm,
    alignItems: 'center',
    borderRadius: theme.borderRadius.md,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.white,
  },
  photoPreview: {
    width: '100%',
    height: 140,
    resizeMode: 'cover',
  },
  photoPreviewText: {
    width: '100%',
    textAlign: 'center',
    paddingVertical: theme.spacing.xs,
    fontSize: theme.fontSize.xs,
    color: theme.colors.primary,
  },
});
