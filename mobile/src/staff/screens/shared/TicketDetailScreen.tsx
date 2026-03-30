/**
 * Ticket Detail Screen
 * 
 * Shows detailed information about a specific ticket.
 * Allows Supervisors to update status, add comments, and manage assignments.
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Alert,
  RefreshControl,
  ActivityIndicator,
  TextInput,
} from 'react-native';
import { GradientButton } from '../../../shared/components/GradientButton';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../../shared/store/auth.store';
import { apiService } from '../../../shared/services/api.service';
import { useOfflineQueue } from '../../../shared/hooks/useOfflineQueue';
import { OfflineIndicator } from '../../../shared/components/shared/OfflineIndicator';
import { colors } from '../../../shared/theme/colors';
import { format } from 'date-fns';
import { StaffScreenHeader } from '../../components/StaffScreenHeader';

interface Ticket {
  id: number;
  title: string;
  description: string;
  category: string;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  status: 'open' | 'in_progress' | 'resolved' | 'closed';
  created_by: string;
  assigned_to?: string;
  created_at: string;
  updated_at: string;
  due_date?: string;
  location?: string;
  tags: string[];
  comments: Comment[];
  parts_cost?: number;
}

interface Comment {
  id: number;
  content: string;
  author: string;
  created_at: string;
  is_internal: boolean;
}

export const TicketDetailScreen = ({ navigation, route }: any) => {
  const { user } = useAuthStore();
  const { addAction, isOnline } = useOfflineQueue();
  const { ticketId } = route.params;
  const [ticket, setTicket] = useState<Ticket | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [newComment, setNewComment] = useState('');
  const [submittingComment, setSubmittingComment] = useState(false);
  const [updatingStatus, setUpdatingStatus] = useState(false);
  const [assigning, setAssigning] = useState(false);

  const fetchTicketDetails = async () => {
    try {
      setLoading(true);
      const response = await apiService.get<{ data: Ticket }>(`/tickets/${ticketId}`);
      setTicket(response.data);
    } catch (error) {
      console.error('Error fetching ticket details:', error);
      // Show error - no mock data in production
      Alert.alert('Error', 'Failed to load ticket details');
      setTicket(null);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchTicketDetails();
  }, [ticketId]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchTicketDetails();
  };

  const handleStatusChange = async (newStatus: string) => {
    if (!ticket) return;

    setUpdatingStatus(true);
    try {
      if (isOnline) {
        await apiService.post(`/tickets/${ticket.id}/status`, { status: newStatus });
        Alert.alert('Success', 'Ticket status updated successfully');
        fetchTicketDetails();
      } else {
        await addAction('ticket_update', {
          ticket_id: ticket.id,
          status: newStatus
        });
        Alert.alert('Offline', 'Status update queued for sync when online');
        // Update local state optimistically
        setTicket(prev => prev ? {
          ...prev,
          status: newStatus as any,
          updated_at: new Date().toISOString()
        } : null);
      }
    } catch (error: any) {
      console.error('Update ticket status error:', error);
      const errorMessage = error?.response?.data?.message || error?.message || 'Failed to update ticket status';
      Alert.alert('Error', errorMessage);
    } finally {
      setUpdatingStatus(false);
    }
  };

  const handleAssignTicket = async () => {
    if (!ticket) return;

    setAssigning(true);
    try {
      if (isOnline) {
        await apiService.post(`/tickets/tickets/${ticket.id}/assign`, {
          assigned_to: user?.id
        });
        Alert.alert('Success', 'Ticket assigned to you successfully');
        fetchTicketDetails();
      } else {
        await addAction('ticket_assign', {
          ticket_id: ticket.id,
          assigned_to: user?.id
        });
        Alert.alert('Offline', 'Ticket assignment queued for sync when online');
        // Update local state optimistically
        setTicket(prev => prev ? {
          ...prev,
          assigned_to: user?.name || 'You',
          status: 'in_progress' as any
        } : null);
      }
    } catch (error: any) {
      console.error('Assign ticket error:', error);
      const errorMessage = error?.response?.data?.message || error?.message || 'Failed to assign ticket';
      Alert.alert('Error', errorMessage);
    } finally {
      setAssigning(false);
    }
  };

  const handleAddComment = async () => {
    if (!ticket || !newComment.trim()) return;

    setSubmittingComment(true);
    try {
      if (isOnline) {
        await apiService.post(`/tickets/tickets/${ticket.id}/comments`, {
          content: newComment.trim(),
          is_internal: false
        });
        Alert.alert('Success', 'Comment added successfully');
        setNewComment('');
        fetchTicketDetails();
      } else {
        await addAction('ticket_comment', {
          ticket_id: ticket.id,
          content: newComment.trim(),
          is_internal: false
        });
        Alert.alert('Offline', 'Comment queued for sync when online');
        setNewComment('');
        // Update local state optimistically
        setTicket(prev => prev ? {
          ...prev,
          comments: [
            ...prev.comments,
            {
              id: Date.now(),
              content: newComment.trim(),
              author: user?.name || 'You',
              created_at: new Date().toISOString(),
              is_internal: false,
            }
          ]
        } : null);
      }
    } catch (error) {
      console.error('Add comment error:', error);
      Alert.alert('Error', 'Failed to add comment');
    } finally {
      setSubmittingComment(false);
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'urgent': return colors.error;
      case 'high': return colors.warning;
      case 'medium': return colors.info;
      case 'low': return colors.success;
      default: return colors.gray;
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'open': return colors.error;
      case 'in_progress': return colors.warning;
      case 'on_hold': return '#FF9800';
      case 'resolved': return colors.success;
      case 'closed': return colors.gray;
      default: return colors.gray;
    }
  };

  const getAllowedNextStatuses = (currentStatus: string): string[] => {
    const allowedTransitions: Record<string, string[]> = {
      'open': ['in_progress', 'on_hold', 'closed'],
      'in_progress': ['on_hold', 'resolved', 'closed'],
      'on_hold': ['in_progress', 'closed'],
      'resolved': ['closed', 'open'],
      'closed': ['open'],
    };
    return allowedTransitions[currentStatus] || [];
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'open': return 'alert-circle-outline';
      case 'in_progress': return 'hourglass-outline';
      case 'on_hold': return 'pause-circle-outline';
      case 'resolved': return 'checkmark-circle-outline';
      case 'closed': return 'close-circle-outline';
      default: return 'help-circle-outline';
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Loading ticket details...</Text>
      </View>
    );
  }

  if (!ticket) {
    return (
      <View style={styles.errorContainer}>
        <Ionicons name="alert-circle" size={48} color={colors.error} />
        <Text style={styles.errorText}>Ticket not found</Text>
        <GradientButton
          style={styles.retryButton}
          onPress={() => navigation.goBack()}>
          <Text style={styles.retryButtonText}>Go Back</Text>
        </GradientButton>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <OfflineIndicator />
      <StaffScreenHeader onBack={() => navigation.goBack()} showBell={false}  title="Ticket Details" />

      <ScrollView
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        
        {/* Ticket Info Card */}
        <View style={styles.ticketCard}>
          <View style={styles.ticketHeader}>
            <Text style={styles.ticketTitle}>{ticket.title}</Text>
            <View style={styles.ticketBadges}>
              <View style={[styles.priorityBadge, { backgroundColor: getPriorityColor(ticket.priority) }]}>
                <Text style={styles.priorityText}>{ticket.priority.toUpperCase()}</Text>
              </View>
              <View style={[styles.statusBadge, { backgroundColor: getStatusColor(ticket.status) }]}>
                <Text style={styles.statusText}>{ticket.status.replace('_', ' ').toUpperCase()}</Text>
              </View>
            </View>
          </View>

          <Text style={styles.ticketDescription}>{ticket.description}</Text>

          {/* Ticket Details */}
          <View style={styles.ticketDetails}>
            <View style={styles.detailRow}>
              <Ionicons name="person" size={16} color={colors.gray} />
              <Text style={styles.detailLabel}>Created by:</Text>
              <Text style={styles.detailValue}>{ticket.created_by}</Text>
            </View>
            
            {ticket.assigned_to && (
              <View style={styles.detailRow}>
                <Ionicons name="person-circle" size={16} color={colors.gray} />
                <Text style={styles.detailLabel}>Assigned to:</Text>
                <Text style={styles.detailValue}>{ticket.assigned_to}</Text>
              </View>
            )}
            
            <View style={styles.detailRow}>
              <Ionicons name="time" size={16} color={colors.gray} />
              <Text style={styles.detailLabel}>Created:</Text>
              <Text style={styles.detailValue}>
                {format(new Date(ticket.created_at), 'MMM dd, yyyy HH:mm')}
              </Text>
            </View>
            
            <View style={styles.detailRow}>
              <Ionicons name="time" size={16} color={colors.gray} />
              <Text style={styles.detailLabel}>Updated:</Text>
              <Text style={styles.detailValue}>
                {format(new Date(ticket.updated_at), 'MMM dd, yyyy HH:mm')}
              </Text>
            </View>
            
            {ticket.due_date && (
              <View style={styles.detailRow}>
                <Ionicons name="calendar" size={16} color={colors.gray} />
                <Text style={styles.detailLabel}>Due date:</Text>
                <Text style={styles.detailValue}>
                  {format(new Date(ticket.due_date), 'MMM dd, yyyy HH:mm')}
                </Text>
              </View>
            )}
            
            {ticket.location && (
              <View style={styles.detailRow}>
                <Ionicons name="location" size={16} color={colors.gray} />
                <Text style={styles.detailLabel}>Location:</Text>
                <Text style={styles.detailValue}>{ticket.location}</Text>
              </View>
            )}
            
            <View style={styles.detailRow}>
              <Ionicons name="pricetag" size={16} color={colors.gray} />
              <Text style={styles.detailLabel}>Category:</Text>
              <Text style={styles.detailValue}>{ticket.category}</Text>
            </View>

            {/* Parts Cost (RM Supervisor only) */}
            {user?.role === 'rm_supervisor' && ticket.parts_cost !== undefined && (
              <View style={styles.detailRow}>
                <Ionicons name="cash-outline" size={16} color={colors.gray} />
                <Text style={styles.detailLabel}>Parts Cost:</Text>
                <Text style={styles.detailValue}>₹{ticket.parts_cost.toFixed(2)}</Text>
              </View>
            )}
          </View>

          {/* Tags */}
          {ticket.tags.length > 0 && (
            <View style={styles.tagsContainer}>
              <Text style={styles.tagsLabel}>Tags:</Text>
              <View style={styles.tagsList}>
                {ticket.tags.map((tag, index) => (
                  <View key={index} style={styles.tag}>
                    <Text style={styles.tagText}>{tag}</Text>
                  </View>
                ))}
              </View>
            </View>
          )}
        </View>

        {/* Actions */}
        <View style={styles.actionsCard}>
          <Text style={styles.actionsTitle}>Actions</Text>
          
          {/* Assign to Me Button */}
          {!ticket.assigned_to && (
            <GradientButton
              style={[styles.assignButton, assigning && styles.buttonDisabled]}
              onPress={handleAssignTicket}
              disabled={assigning}>
              {assigning ? (
                <ActivityIndicator size="small" color={colors.white} />
              ) : (
                <>
                  <Ionicons name="person-add" size={20} color={colors.white} />
                  <Text style={styles.assignButtonText}>Assign to Me</Text>
                </>
              )}
            </GradientButton>
          )}

          {/* Status Update Buttons */}
          {getAllowedNextStatuses(ticket.status).length > 0 && (
            <View style={styles.statusButtonsContainer}>
              <Text style={styles.statusButtonsTitle}>Update Status</Text>
              <View style={styles.statusButtons}>
                {getAllowedNextStatuses(ticket.status).map((status) => (
                  <GradientButton
                    key={status}
                    style={[
                      styles.statusButton,
                      { backgroundColor: getStatusColor(status) + '20', borderColor: getStatusColor(status) },
                      updatingStatus && styles.buttonDisabled,
                    ]}
                    onPress={() => handleStatusChange(status)}
                    disabled={updatingStatus}>
                    <Ionicons name={getStatusIcon(status)} size={18} color={getStatusColor(status)} />
                    <Text style={[styles.statusButtonText, { color: getStatusColor(status) }]}>
                      {status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ')}
                    </Text>
                  </GradientButton>
                ))}
              </View>
            </View>
          )}
        </View>

        {/* Comments */}
        <View style={styles.commentsCard}>
          <Text style={styles.commentsTitle}>Comments ({ticket.comments.length})</Text>
          
          {ticket.comments.map((comment) => (
            <View key={comment.id} style={styles.comment}>
              <View style={styles.commentHeader}>
                <Text style={styles.commentAuthor}>{comment.author}</Text>
                <Text style={styles.commentTime}>
                  {format(new Date(comment.created_at), 'MMM dd, HH:mm')}
                </Text>
              </View>
              <Text style={styles.commentContent}>{comment.content}</Text>
              {comment.is_internal && (
                <View style={styles.internalBadge}>
                  <Text style={styles.internalText}>Internal Note</Text>
                </View>
              )}
            </View>
          ))}
          
          {/* Add Comment */}
          <View style={styles.addCommentContainer}>
            <TextInput
              style={styles.commentInput}
              placeholder="Add a comment..."
              value={newComment}
              onChangeText={setNewComment}
              multiline
              numberOfLines={3}
            />
            <GradientButton
              style={[styles.addCommentButton, submittingComment && styles.addCommentButtonDisabled]}
              onPress={handleAddComment}
              disabled={submittingComment || !newComment.trim()}>
              {submittingComment ? (
                <ActivityIndicator size="small" color={colors.white} />
              ) : (
                <>
                  <Ionicons name="send" size={16} color={colors.white} />
                  <Text style={styles.addCommentButtonText}>Add Comment</Text>
                </>
              )}
            </GradientButton>
          </View>
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
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: colors.background,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: colors.gray,
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: colors.background,
    padding: 20,
  },
  errorText: {
    fontSize: 18,
    color: colors.error,
    marginTop: 16,
    textAlign: 'center',
  },
  retryButton: {
    marginTop: 20,
    paddingHorizontal: 24,
    paddingVertical: 12,
    backgroundColor: colors.primary,
    borderRadius: 8,
  },
  retryButtonText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '500',
  },
  content: {
    flex: 1,
    padding: 16,
  },
  ticketCard: {
    backgroundColor: colors.white,
    borderRadius: 8,
    padding: 16,
    marginBottom: 16,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  ticketHeader: {
    marginBottom: 12,
  },
  ticketTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 8,
  },
  ticketBadges: {
    flexDirection: 'row',
    gap: 8,
  },
  priorityBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
  },
  priorityText: {
    fontSize: 12,
    color: colors.white,
    fontWeight: '600',
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
  },
  statusText: {
    fontSize: 12,
    color: colors.white,
    fontWeight: '600',
  },
  ticketDescription: {
    fontSize: 14,
    color: colors.text,
    lineHeight: 20,
    marginBottom: 16,
  },
  ticketDetails: {
    marginBottom: 16,
  },
  detailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  detailLabel: {
    fontSize: 14,
    color: colors.gray,
    marginLeft: 8,
    marginRight: 8,
    minWidth: 80,
  },
  detailValue: {
    fontSize: 14,
    color: colors.text,
    flex: 1,
  },
  tagsContainer: {
    marginTop: 8,
  },
  tagsLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.text,
    marginBottom: 8,
  },
  tagsList: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  tag: {
    backgroundColor: colors.primary + '20',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
  },
  tagText: {
    fontSize: 12,
    color: colors.primary,
    fontWeight: '500',
  },
  actionsCard: {
    backgroundColor: colors.white,
    borderRadius: 8,
    padding: 16,
    marginBottom: 16,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  actionsTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 12,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: colors.background,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    flex: 1,
    justifyContent: 'center',
  },
  actionButtonText: {
    fontSize: 14,
    color: colors.primary,
    marginLeft: 8,
    fontWeight: '500',
  },
  assignButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.primary,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    marginBottom: 16,
    gap: 8,
  },
  assignButtonText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '600',
  },
  statusButtonsContainer: {
    marginTop: 8,
  },
  statusButtonsTitle: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.text,
    marginBottom: 12,
  },
  statusButtons: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  statusButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 8,
    borderWidth: 1,
    gap: 6,
    minWidth: 120,
  },
  statusButtonText: {
    fontSize: 13,
    fontWeight: '600',
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  commentsCard: {
    backgroundColor: colors.white,
    borderRadius: 8,
    padding: 16,
    marginBottom: 16,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  commentsTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 16,
  },
  comment: {
    marginBottom: 16,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  commentHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  commentAuthor: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.text,
  },
  commentTime: {
    fontSize: 12,
    color: colors.gray,
  },
  commentContent: {
    fontSize: 14,
    color: colors.text,
    lineHeight: 20,
  },
  internalBadge: {
    backgroundColor: colors.warning + '20',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
    alignSelf: 'flex-start',
    marginTop: 8,
  },
  internalText: {
    fontSize: 10,
    color: colors.warning,
    fontWeight: '600',
  },
  addCommentContainer: {
    marginTop: 16,
  },
  commentInput: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    padding: 12,
    fontSize: 14,
    color: colors.text,
    textAlignVertical: 'top',
    marginBottom: 12,
  },
  addCommentButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.primary,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
  },
  addCommentButtonDisabled: {
    backgroundColor: colors.gray,
  },
  addCommentButtonText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '500',
    marginLeft: 8,
  },
  modalOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1000,
  },
  modalContent: {
    backgroundColor: colors.white,
    borderRadius: 8,
    padding: 20,
    margin: 20,
    minWidth: 280,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 16,
    textAlign: 'center',
  },
  modalDescription: {
    fontSize: 14,
    color: colors.gray,
    textAlign: 'center',
    marginBottom: 20,
  },
  modalOption: {
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  modalOptionText: {
    fontSize: 16,
    color: colors.text,
    textAlign: 'center',
  },
  modalButtons: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 16,
  },
  modalButton: {
    flex: 1,
    backgroundColor: colors.primary,
    paddingVertical: 12,
    borderRadius: 8,
  },
  modalButtonText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '500',
    textAlign: 'center',
  },
  modalCancel: {
    flex: 1,
    backgroundColor: colors.background,
    paddingVertical: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  modalCancelText: {
    color: colors.text,
    fontSize: 16,
    fontWeight: '500',
    textAlign: 'center',
  },
});
