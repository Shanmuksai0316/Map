/**
 * Ticket Create Screen
 * 
 * Allows Supervisors to create new tickets for various issues.
 * Supports offline mode with optimistic creation.
 */

import React, { useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { useOfflineQueue } from '../../../shared/hooks/useOfflineQueue';
import { OfflineIndicator } from '../../../shared/components/shared/OfflineIndicator';
import { apiService } from '../../../shared/services/api.service';
import { colors } from '../../../shared/theme/colors';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';
import { StaffPrimaryButton } from '../../components/StaffPrimaryButton';

interface TicketForm {
  title: string;
  description: string;
  category: string;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  location: string;
  due_date: string;
  tags: string[];
  parts_cost?: string; // For RM Supervisor only
}

const CATEGORIES = [
  'maintenance',
  'cleaning',
  'security',
  'technical',
  'supplies',
  'furniture',
  'plumbing',
  'electrical',
  'other',
];

const PRIORITIES = [
  { value: 'low', label: 'Low', color: colors.success },
  { value: 'medium', label: 'Medium', color: colors.info },
  { value: 'high', label: 'High', color: colors.warning },
  { value: 'urgent', label: 'Urgent', color: colors.error },
];

const COMMON_TAGS = [
  'urgent',
  'broken',
  'repair',
  'replacement',
  'cleaning',
  'security',
  'electrical',
  'plumbing',
  'furniture',
  'door',
  'window',
  'lock',
  'wifi',
  'heating',
  'cooling',
];

export const TicketCreateScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const { addAction, isOnline } = useOfflineQueue();
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState<TicketForm>({
    title: '',
    description: '',
    category: 'maintenance',
    priority: 'medium',
    location: '',
    due_date: '',
    tags: [],
    parts_cost: '',
  });

  const handleSubmit = async () => {
    // Validation
    if (!form.title.trim()) {
      Alert.alert('Error', 'Please enter a title for the ticket');
      return;
    }

    if (!form.description.trim() || form.description.trim().length < 10) {
      Alert.alert('Error', 'Please enter a description (at least 10 characters)');
      return;
    }

    if (!form.location.trim()) {
      Alert.alert('Error', 'Please enter a location');
      return;
    }

    // Confirm submission
    Alert.alert(
      'Create Ticket',
      `Title: ${form.title}\nCategory: ${form.category}\nPriority: ${form.priority}\nLocation: ${form.location}\n\nCreate this ticket?`,
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Create', onPress: () => processSubmit() },
      ]
    );
  };

  const processSubmit = async () => {
    setSubmitting(true);

    try {
      const payload: any = {
        title: form.title.trim(),
        description: form.description.trim(),
        category: form.category,
        priority: form.priority,
        location: form.location.trim(),
        due_date: form.due_date || null,
        tags: form.tags,
        created_by: user?.id,
      };

      // Add parts_cost for RM Supervisor
      if (user?.role === 'rm_supervisor' && form.parts_cost?.trim()) {
        const cost = parseFloat(form.parts_cost.trim());
        if (!isNaN(cost) && cost >= 0) {
          payload.parts_cost = cost;
        }
      }

      if (isOnline) {
        // Try online submission
        const response = await apiService.post('/tickets', payload);
        Alert.alert(
          'Success',
          `Ticket #${response.data.id} created successfully`,
          [{ text: 'OK', onPress: () => navigation.goBack() }]
        );
      } else {
        // Queue for offline sync
        await addAction('ticket_create', payload);
        Alert.alert(
          'Offline',
          'Ticket created and queued for sync when online',
          [{ text: 'OK', onPress: () => navigation.goBack() }]
        );
      }
    } catch (error) {
      console.error('Create ticket error:', error);
      // Queue for offline sync even on error
      await addAction('ticket_create', {
        title: form.title.trim(),
        description: form.description.trim(),
        category: form.category,
        priority: form.priority,
        location: form.location.trim(),
        due_date: form.due_date || null,
        tags: form.tags,
        created_by: user?.id,
      });
      Alert.alert(
        'Queued',
        'Failed to create ticket online. Added to offline queue.',
        [{ text: 'OK', onPress: () => navigation.goBack() }]
      );
    } finally {
      setSubmitting(false);
    }
  };

  const handleTagToggle = (tag: string) => {
    setForm(prev => ({
      ...prev,
      tags: prev.tags.includes(tag)
        ? prev.tags.filter(t => t !== tag)
        : [...prev.tags, tag]
    }));
  };

  const getPriorityColor = (priority: string) => {
    const priorityObj = PRIORITIES.find(p => p.value === priority);
    return priorityObj?.color || colors.gray;
  };

  return (
    <View style={styles.container}>
      <OfflineIndicator />
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Create Ticket" />

      <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
        
        {/* Title */}
        <View style={styles.section}>
          <Text style={styles.label}>Title *</Text>
          <TextInput
            style={styles.input}
            placeholder="Enter ticket title..."
            value={form.title}
            onChangeText={(text) => setForm(prev => ({ ...prev, title: text }))}
            maxLength={100}
          />
        </View>

        {/* Description */}
        <View style={styles.section}>
          <Text style={styles.label}>Description *</Text>
          <TextInput
            style={[styles.input, styles.textArea]}
            placeholder="Describe the issue in detail..."
            value={form.description}
            onChangeText={(text) => setForm(prev => ({ ...prev, description: text }))}
            multiline
            numberOfLines={4}
            textAlignVertical="top"
            maxLength={500}
          />
          <Text style={styles.characterCount}>
            {form.description.length}/500 characters
          </Text>
        </View>

        {/* Category */}
        <View style={styles.section}>
          <Text style={styles.label}>Category *</Text>
          <View style={styles.categoryGrid}>
            {CATEGORIES.map((category) => (
              <TouchableOpacity
                key={category}
                style={[
                  styles.categoryOption,
                  form.category === category && styles.categoryOptionSelected
                ]}
                onPress={() => setForm(prev => ({ ...prev, category }))}>
                <Text style={[
                  styles.categoryText,
                  form.category === category && styles.categoryTextSelected
                ]}>
                  {category.charAt(0).toUpperCase() + category.slice(1)}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        {/* Priority */}
        <View style={styles.section}>
          <Text style={styles.label}>Priority *</Text>
          <View style={styles.priorityGrid}>
            {PRIORITIES.map((priority) => (
              <TouchableOpacity
                key={priority.value}
                style={[
                  styles.priorityOption,
                  form.priority === priority.value && styles.priorityOptionSelected,
                  { borderColor: priority.color }
                ]}
                onPress={() => setForm(prev => ({ ...prev, priority: priority.value as any }))}>
                <View style={[styles.priorityIndicator, { backgroundColor: priority.color }]} />
                <Text style={[
                  styles.priorityText,
                  form.priority === priority.value && styles.priorityTextSelected
                ]}>
                  {priority.label}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        {/* Location */}
        <View style={styles.section}>
          <Text style={styles.label}>Location *</Text>
          <TextInput
            style={styles.input}
            placeholder="e.g., Hostel A - Room 101, Common Area, etc."
            value={form.location}
            onChangeText={(text) => setForm(prev => ({ ...prev, location: text }))}
            maxLength={100}
          />
        </View>

        {/* Due Date */}
        <View style={styles.section}>
          <Text style={styles.label}>Due Date (Optional)</Text>
          <TextInput
            style={styles.input}
            placeholder="YYYY-MM-DD (e.g., 2025-10-30)"
            value={form.due_date}
            onChangeText={(text) => setForm(prev => ({ ...prev, due_date: text }))}
            keyboardType="numeric"
          />
        </View>

        {/* Parts Cost (RM Supervisor only) */}
        {user?.role === 'rm_supervisor' && (
          <View style={styles.section}>
            <Text style={styles.label}>Parts Cost (Optional)</Text>
            <View style={styles.partsCostContainer}>
              <Text style={styles.currencySymbol}>₹</Text>
              <TextInput
                style={[styles.input, styles.partsCostInput]}
                placeholder="0.00"
                value={form.parts_cost}
                onChangeText={(text) => {
                  // Only allow numbers and decimal point
                  const cleaned = text.replace(/[^0-9.]/g, '');
                  setForm(prev => ({ ...prev, parts_cost: cleaned }));
                }}
                keyboardType="decimal-pad"
              />
            </View>
            <Text style={styles.helperText}>Enter the estimated cost of parts for this maintenance ticket</Text>
          </View>
        )}

        {/* Tags */}
        <View style={styles.section}>
          <Text style={styles.label}>Tags (Optional)</Text>
          <View style={styles.tagsGrid}>
            {COMMON_TAGS.map((tag) => (
              <TouchableOpacity
                key={tag}
                style={[
                  styles.tagOption,
                  form.tags.includes(tag) && styles.tagOptionSelected
                ]}
                onPress={() => handleTagToggle(tag)}>
                <Text style={[
                  styles.tagText,
                  form.tags.includes(tag) && styles.tagTextSelected
                ]}>
                  {tag}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
          {form.tags.length > 0 && (
            <View style={styles.selectedTags}>
              <Text style={styles.selectedTagsLabel}>Selected: </Text>
              <Text style={styles.selectedTagsText}>{form.tags.join(', ')}</Text>
            </View>
          )}
        </View>

        {/* Submit Button */}
        <View style={styles.submitSection}>
          <StaffPrimaryButton
            label="Create Ticket"
            onPress={handleSubmit}
            disabled={submitting}
            loading={submitting}
          />
        </View>
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    flex: 1,
    padding: 16,
  },
  section: {
    marginBottom: 24,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 8,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    color: colors.text,
    backgroundColor: colors.white,
  },
  textArea: {
    height: 100,
    textAlignVertical: 'top',
  },
  characterCount: {
    fontSize: 12,
    color: colors.gray,
    textAlign: 'right',
    marginTop: 4,
  },
  categoryGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  categoryOption: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    backgroundColor: colors.background,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: colors.border,
  },
  categoryOptionSelected: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  categoryText: {
    fontSize: 14,
    color: colors.gray,
  },
  categoryTextSelected: {
    color: colors.white,
  },
  priorityGrid: {
    gap: 8,
  },
  priorityOption: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    backgroundColor: colors.white,
    borderRadius: 8,
    borderWidth: 2,
    borderColor: colors.border,
  },
  priorityOptionSelected: {
    backgroundColor: colors.primary + '10',
  },
  priorityIndicator: {
    width: 12,
    height: 12,
    borderRadius: 6,
    marginRight: 12,
  },
  priorityText: {
    fontSize: 16,
    color: colors.text,
  },
  priorityTextSelected: {
    color: colors.primary,
    fontWeight: '600',
  },
  tagsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  tagOption: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: colors.background,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
  },
  tagOptionSelected: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  tagText: {
    fontSize: 12,
    color: colors.gray,
  },
  tagTextSelected: {
    color: colors.white,
  },
  selectedTags: {
    flexDirection: 'row',
    marginTop: 8,
    flexWrap: 'wrap',
  },
  selectedTagsLabel: {
    fontSize: 14,
    color: colors.gray,
  },
  selectedTagsText: {
    fontSize: 14,
    color: colors.text,
    flex: 1,
  },
  submitSection: {
    marginTop: 16,
    marginBottom: 32,
  },
  partsCostContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    backgroundColor: colors.white,
    overflow: 'hidden',
  },
  currencySymbol: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    paddingHorizontal: 12,
    backgroundColor: colors.background,
    paddingVertical: 12,
  },
  partsCostInput: {
    flex: 1,
    borderWidth: 0,
    margin: 0,
  },
  helperText: {
    fontSize: 12,
    color: colors.gray,
    marginTop: 4,
    fontStyle: 'italic',
  },
});
