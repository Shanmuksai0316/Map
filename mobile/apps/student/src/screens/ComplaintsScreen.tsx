import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Modal,
  Alert,
} from 'react-native';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { Complaint } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { format } from 'date-fns';
import { StatusBadge, FormInput, Card, CardContent, ComplaintSkeleton, EmptyState } from '../../components';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { theme } from '../../theme/theme';
import { hapticService } from '../../services/haptic.service';
import { validateRequired, validateMinLength, sanitizeText } from '../../utils/validation';
import { errorHandler } from '../../utils/errorHandler';

export const ComplaintsScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [complaints, setComplaints] = useState<Complaint[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [newComplaint, setNewComplaint] = useState({
    category: '',
    description: '',
  });

  const complaintCategories = [
    'Maintenance',
    'Security',
    'Food',
    'Internet/WiFi',
    'Electricity',
    'Water',
    'Room Issues',
    'Common Areas',
    'Noise',
    'Other',
  ];

  const fetchComplaints = async () => {
    try {
      const response = await apiService.get<{ data: Complaint[] }>(
        APP_CONFIG.ENDPOINTS.COMPLAINTS
      );
      setComplaints(response.data);
    } catch (error) {
      console.error('Error fetching complaints:', error);
      // Mock data for demo
      setComplaints([
        {
          id: 1,
          student_id: user?.id || 1,
          student_name: user?.name || 'John Doe',
          hostel_name: 'Hostel A',
          category: 'Maintenance',
          description: 'Water leakage in bathroom',
          status: 'pending',
          priority: 'high',
          created_at: '2025-10-15T10:30:00Z',
        },
        {
          id: 2,
          student_id: user?.id || 1,
          student_name: user?.name || 'John Doe',
          hostel_name: 'Hostel A',
          category: 'Internet/WiFi',
          description: 'WiFi is very slow in my room',
          status: 'in_progress',
          priority: 'medium',
          created_at: '2025-10-14T15:20:00Z',
        },
      ]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchComplaints();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchComplaints();
  };

  const handleCreateComplaint = async () => {
    // Validate category
    if (!newComplaint.category) {
      hapticService.onError();
      Alert.alert('Validation Error', 'Please select a category');
      return;
    }
    
    // Validate description
    const descriptionValidation = validateRequired(newComplaint.description, 'Description');
    if (!descriptionValidation.isValid) {
      hapticService.onError();
      Alert.alert('Validation Error', descriptionValidation.error);
      return;
    }
    
    const minLengthValidation = validateMinLength(newComplaint.description, 20, 'Description');
    if (!minLengthValidation.isValid) {
      hapticService.onError();
      Alert.alert('Validation Error', minLengthValidation.error);
      return;
    }

    try {
      await apiService.post(APP_CONFIG.ENDPOINTS.COMPLAINTS, {
        category: newComplaint.category,
        description: sanitizeText(newComplaint.description),
      });

      hapticService.onSuccess();
      Alert.alert('Success', 'Complaint submitted successfully');
      setShowCreateModal(false);
      setNewComplaint({
        category: '',
        description: '',
      });
      fetchComplaints();
    } catch (err) {
      hapticService.onError();
      const errorDetails = errorHandler.handleError(err);
      Alert.alert('Error', errorDetails.message);
    }
  };


  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => {
            hapticService.onButtonPress();
            navigation.goBack();
          }}>
          <Ionicons name="arrow-back" size={20} color={theme.colors.white} />
          <Text style={styles.backButtonText}>Back</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Complaints</Text>
        <TouchableOpacity
          style={styles.createButton}
          onPress={() => {
            hapticService.onButtonPress();
            setShowCreateModal(true);
          }}>
          <Ionicons name="add" size={18} color={theme.colors.primary} />
          <Text style={styles.createButtonText}>New</Text>
        </TouchableOpacity>
      </View>

      {/* Complaints List */}
      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <ComplaintSkeleton count={3} />
        ) : complaints.length === 0 ? (
          <EmptyState
            variant="no-data"
            title="No Complaints"
            subtitle="You haven't submitted any complaints yet"
            actionLabel="Submit First Complaint"
            actionIcon="add"
            onActionPress={() => setShowCreateModal(true)}
          />
        ) : (
          complaints.map((complaint) => (
            <Card key={complaint.id} style={styles.complaintCard} variant="default">
              <CardContent>
                <View style={styles.complaintHeader}>
                  <View style={styles.categoryContainer}>
                    <View style={styles.categoryRow}>
                      <Ionicons name="folder-outline" size={16} color={theme.colors.textSecondary} />
                      <Text style={styles.categoryText}>{complaint.category}</Text>
                    </View>
                    <StatusBadge
                      status={complaint.priority}
                      size="small"
                      variant="filled"
                    />
                  </View>
                  <StatusBadge
                    status={complaint.status}
                    size="small"
                    variant="filled"
                  />
                </View>

                <Text style={styles.description}>{complaint.description}</Text>

                <View style={styles.complaintFooter}>
                  <View style={styles.footerRow}>
                    <Ionicons name="business-outline" size={14} color={theme.colors.textMuted} />
                    <Text style={styles.hostelText}>
                      {complaint.hostel_name}
                    </Text>
                  </View>
                  <View style={styles.footerRow}>
                    <Ionicons name="time-outline" size={14} color={theme.colors.textMuted} />
                    <Text style={styles.dateText}>
                      {format(new Date(complaint.created_at), 'MMM dd, yyyy HH:mm')}
                    </Text>
                  </View>
                </View>

                {complaint.resolved_at && (
                  <View style={styles.resolvedContainer}>
                    <Ionicons name="checkmark-circle-outline" size={16} color={theme.colors.success} />
                    <Text style={styles.resolvedText}>
                      Resolved: {format(new Date(complaint.resolved_at), 'MMM dd, yyyy')}
                    </Text>
                  </View>
                )}
              </CardContent>
            </Card>
          ))
        )}
      </ScrollView>

      {/* Create Complaint Modal */}
      <Modal
        visible={showCreateModal}
        animationType="slide"
        presentationStyle="pageSheet">
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Submit Complaint</Text>
            <TouchableOpacity onPress={() => {
              hapticService.onButtonPress();
              setShowCreateModal(false);
            }}>
              <Ionicons name="close" size={24} color={theme.colors.textSecondary} />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.modalContent}>
            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>Category *</Text>
              <View style={styles.categoryGrid}>
                {complaintCategories.map((category) => (
                  <TouchableOpacity
                    key={category}
                    style={[
                      styles.categoryOption,
                      newComplaint.category === category && styles.categorySelected,
                    ]}
                    onPress={() =>
                      setNewComplaint({ ...newComplaint, category })
                    }>
                    <Text
                      style={[
                        styles.categoryOptionText,
                        newComplaint.category === category && styles.categorySelectedText,
                      ]}>
                      {category}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            </View>

            <FormInput
              label="Description"
              value={newComplaint.description}
              onChangeText={(text) =>
                setNewComplaint({ ...newComplaint, description: text })
              }
              placeholder="Describe your complaint in detail..."
              multiline
              numberOfLines={6}
              required
              variant="outlined"
            />

            <TouchableOpacity
              style={styles.submitButton}
              onPress={() => {
                hapticService.onButtonPress();
                handleCreateComplaint();
              }}>
              <Text style={styles.submitButtonText}>Submit Complaint</Text>
            </TouchableOpacity>
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  header: {
    backgroundColor: theme.colors.primary,
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  backButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    padding: theme.spacing.sm,
  },
  backButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  headerTitle: {
    color: theme.colors.white,
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
  },
  createButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
    backgroundColor: theme.colors.white,
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.md,
  },
  createButtonText: {
    color: theme.colors.primary,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
  content: {
    flex: 1,
    padding: theme.spacing.md,
  },
  complaintCard: {
    backgroundColor: theme.colors.card,
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.md,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    ...theme.shadows.small,
  },
  complaintHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: theme.spacing.sm,
  },
  categoryContainer: {
    flex: 1,
  },
  categoryRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  categoryText: {
    fontSize: theme.fontSize.lg,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginLeft: theme.spacing.xs,
  },
  description: {
    fontSize: theme.fontSize.md,
    color: theme.colors.textSecondary,
    lineHeight: 20,
    marginBottom: theme.spacing.sm,
  },
  complaintFooter: {
    marginBottom: theme.spacing.sm,
  },
  footerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  hostelText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
    fontWeight: theme.fontWeight.medium,
  },
  dateText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textMuted,
  },
  resolvedContainer: {
    marginTop: theme.spacing.sm,
    paddingTop: theme.spacing.sm,
    borderTopWidth: 1,
    borderTopColor: theme.colors.divider,
  },
  resolvedText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.success,
    fontWeight: theme.fontWeight.semibold,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: theme.colors.card,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl * 2,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.divider,
  },
  modalTitle: {
    fontSize: theme.fontSize.xl,
    fontWeight: theme.fontWeight.bold,
    color: theme.colors.text,
  },
  modalClose: {
    fontSize: theme.fontSize.xxl,
    color: theme.colors.textSecondary,
  },
  modalContent: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  inputGroup: {
    marginBottom: theme.spacing.lg,
  },
  inputLabel: {
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
    color: theme.colors.text,
    marginBottom: theme.spacing.sm,
  },
  categoryGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
  },
  categoryOption: {
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.xl,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
  },
  categorySelected: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  categoryOptionText: {
    fontSize: theme.fontSize.sm,
    color: theme.colors.textSecondary,
    fontWeight: theme.fontWeight.medium,
  },
  categorySelectedText: {
    color: theme.colors.white,
    fontWeight: theme.fontWeight.semibold,
  },
  submitButton: {
    backgroundColor: theme.colors.primary,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    alignItems: 'center',
    marginTop: theme.spacing.lg,
  },
  submitButtonText: {
    color: theme.colors.white,
    fontSize: theme.fontSize.md,
    fontWeight: theme.fontWeight.semibold,
  },
});
