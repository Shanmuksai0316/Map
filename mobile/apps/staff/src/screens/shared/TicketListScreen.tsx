/**
 * Ticket List Screen
 * 
 * Allows Supervisors to view, filter, and manage tickets.
 * Supports offline mode with optimistic updates.
 */

import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
  ActivityIndicator,
  TextInput,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { useOfflineQueue } from '../../hooks/useOfflineQueue';
import { OfflineIndicator } from '../../components/shared/OfflineIndicator';
import { colors } from '../../theme/colors';
import { format } from 'date-fns';

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
}

interface FilterOptions {
  status: string;
  priority: string;
  category: string;
  assigned: string;
}

export const TicketListScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const { addAction, isOnline } = useOfflineQueue();
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [showFilters, setShowFilters] = useState(false);
  const [filters, setFilters] = useState<FilterOptions>({
    status: 'all',
    priority: 'all',
    category: 'all',
    assigned: 'all',
  });

  const fetchTickets = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams();
      
      if (filters.status !== 'all') params.append('status', filters.status);
      if (filters.priority !== 'all') params.append('priority', filters.priority);
      if (filters.category !== 'all') params.append('category', filters.category);
      if (filters.assigned !== 'all') params.append('assigned', filters.assigned);
      if (searchQuery) params.append('search', searchQuery);

      const response = await apiService.get<{ data: Ticket[] }>(`/tickets?${params.toString()}`);
      setTickets(response.data);
    } catch (error) {
      console.error('Error fetching tickets:', error);
      // Mock data for demo
      setTickets([
        {
          id: 1,
          title: 'Broken door lock in Room 101',
          description: 'The door lock is not working properly, students cannot lock their room',
          category: 'maintenance',
          priority: 'high',
          status: 'open',
          created_by: 'Student John Doe',
          created_at: '2025-10-29T10:00:00Z',
          updated_at: '2025-10-29T10:00:00Z',
          due_date: '2025-10-30T18:00:00Z',
          location: 'Hostel A - Room 101',
          tags: ['door', 'lock', 'urgent'],
        },
        {
          id: 2,
          title: 'Cleaning supplies needed',
          description: 'We are running low on cleaning supplies for the common areas',
          category: 'supplies',
          priority: 'medium',
          status: 'in_progress',
          created_by: 'HK Staff',
          assigned_to: 'Cleaning Team',
          created_at: '2025-10-28T14:30:00Z',
          updated_at: '2025-10-29T09:15:00Z',
          location: 'Common Areas',
          tags: ['cleaning', 'supplies'],
        },
        {
          id: 3,
          title: 'WiFi connectivity issues',
          description: 'Students reporting poor WiFi signal in the study room',
          category: 'technical',
          priority: 'medium',
          status: 'resolved',
          created_by: 'Student Jane Smith',
          assigned_to: 'IT Support',
          created_at: '2025-10-27T16:45:00Z',
          updated_at: '2025-10-28T11:20:00Z',
          location: 'Study Room',
          tags: ['wifi', 'network'],
        },
      ]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchTickets();
  }, [filters, searchQuery]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchTickets();
  };

  const handleTicketPress = (ticket: Ticket) => {
    navigation.navigate('TicketDetail', { ticketId: ticket.id });
  };

  const handleAssignTicket = async (ticket: Ticket) => {
    Alert.alert(
      'Assign Ticket',
      `Assign ticket #${ticket.id} to yourself?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Assign',
          onPress: async () => {
            try {
              if (isOnline) {
                await apiService.post(`/tickets/tickets/${ticket.id}/assign`, {
                  assigned_to: user?.id
                });
                Alert.alert('Success', 'Ticket assigned successfully');
                fetchTickets();
              } else {
                await addAction('ticket_assign', {
                  ticket_id: ticket.id,
                  assigned_to: user?.id
                });
                Alert.alert('Offline', 'Ticket assignment queued for sync when online');
                // Update local state optimistically
                setTickets(prev => prev.map(t => 
                  t.id === ticket.id 
                    ? { ...t, assigned_to: user?.name || 'You', status: 'in_progress' }
                    : t
                ));
              }
            } catch (error) {
              console.error('Assign ticket error:', error);
              Alert.alert('Error', 'Failed to assign ticket');
            }
          },
        },
      ]
    );
  };

  const handleStatusChange = async (ticket: Ticket, newStatus: string) => {
    try {
      if (isOnline) {
        await apiService.put(`/tickets/${ticket.id}`, { status: newStatus });
        Alert.alert('Success', 'Ticket status updated successfully');
        fetchTickets();
      } else {
        await addAction('ticket_update', {
          ticket_id: ticket.id,
          status: newStatus
        });
        Alert.alert('Offline', 'Status update queued for sync when online');
        // Update local state optimistically
        setTickets(prev => prev.map(t => 
          t.id === ticket.id 
            ? { ...t, status: newStatus as any, updated_at: new Date().toISOString() }
            : t
        ));
      }
    } catch (error) {
      console.error('Update ticket status error:', error);
      Alert.alert('Error', 'Failed to update ticket status');
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
      case 'resolved': return colors.success;
      case 'closed': return colors.gray;
      default: return colors.gray;
    }
  };

  const filteredTickets = tickets.filter(ticket => {
    if (searchQuery) {
      const query = searchQuery.toLowerCase();
      return (
        ticket.title.toLowerCase().includes(query) ||
        ticket.description.toLowerCase().includes(query) ||
        ticket.location?.toLowerCase().includes(query) ||
        ticket.tags.some(tag => tag.toLowerCase().includes(query))
      );
    }
    return true;
  });

  return (
    <View style={styles.container}>
      <OfflineIndicator />
      
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.backButton}
          onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={24} color={colors.primary} />
        </TouchableOpacity>
        <Text style={styles.title}>Tickets</Text>
        <TouchableOpacity
          style={styles.filterButton}
          onPress={() => setShowFilters(!showFilters)}>
          <Ionicons name="filter" size={24} color={colors.primary} />
        </TouchableOpacity>
      </View>

      {/* Search Bar */}
      <View style={styles.searchSection}>
        <View style={styles.searchContainer}>
          <Ionicons name="search" size={20} color={colors.gray} style={styles.searchIcon} />
          <TextInput
            style={styles.searchInput}
            placeholder="Search tickets..."
            value={searchQuery}
            onChangeText={setSearchQuery}
            autoCapitalize="none"
            autoCorrect={false}
          />
        </View>
      </View>

      {/* Filters */}
      {showFilters && (
        <View style={styles.filtersSection}>
          <Text style={styles.filtersTitle}>Filters</Text>
          
          {/* Status Filter */}
          <View style={styles.filterRow}>
            <Text style={styles.filterLabel}>Status:</Text>
            <View style={styles.filterOptions}>
              {['all', 'open', 'in_progress', 'resolved', 'closed'].map((status) => (
                <TouchableOpacity
                  key={status}
                  style={[
                    styles.filterOption,
                    filters.status === status && styles.filterOptionActive
                  ]}
                  onPress={() => setFilters(prev => ({ ...prev, status }))}>
                  <Text style={[
                    styles.filterOptionText,
                    filters.status === status && styles.filterOptionTextActive
                  ]}>
                    {status.charAt(0).toUpperCase() + status.slice(1)}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>

          {/* Priority Filter */}
          <View style={styles.filterRow}>
            <Text style={styles.filterLabel}>Priority:</Text>
            <View style={styles.filterOptions}>
              {['all', 'low', 'medium', 'high', 'urgent'].map((priority) => (
                <TouchableOpacity
                  key={priority}
                  style={[
                    styles.filterOption,
                    filters.priority === priority && styles.filterOptionActive
                  ]}
                  onPress={() => setFilters(prev => ({ ...prev, priority }))}>
                  <Text style={[
                    styles.filterOptionText,
                    filters.priority === priority && styles.filterOptionTextActive
                  ]}>
                    {priority.charAt(0).toUpperCase() + priority.slice(1)}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>
        </View>
      )}

      {/* Tickets List */}
      <ScrollView
        style={styles.ticketsList}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }>
        {loading ? (
          <ActivityIndicator size="large" color={colors.primary} style={styles.loader} />
        ) : filteredTickets.length > 0 ? (
          filteredTickets.map((ticket) => (
            <TouchableOpacity
              key={ticket.id}
              style={styles.ticketCard}
              onPress={() => handleTicketPress(ticket)}>
              
              {/* Ticket Header */}
              <View style={styles.ticketHeader}>
                <View style={styles.ticketTitleRow}>
                  <Text style={styles.ticketTitle} numberOfLines={1}>
                    #{ticket.id} {ticket.title}
                  </Text>
                  <View style={styles.ticketBadges}>
                    <View style={[styles.priorityBadge, { backgroundColor: getPriorityColor(ticket.priority) }]}>
                      <Text style={styles.priorityText}>{ticket.priority.toUpperCase()}</Text>
                    </View>
                    <View style={[styles.statusBadge, { backgroundColor: getStatusColor(ticket.status) }]}>
                      <Text style={styles.statusText}>{ticket.status.replace('_', ' ').toUpperCase()}</Text>
                    </View>
                  </View>
                </View>
                
                <Text style={styles.ticketDescription} numberOfLines={2}>
                  {ticket.description}
                </Text>
              </View>

              {/* Ticket Details */}
              <View style={styles.ticketDetails}>
                <View style={styles.ticketDetailRow}>
                  <Ionicons name="person" size={16} color={colors.gray} />
                  <Text style={styles.ticketDetailText}>By: {ticket.created_by}</Text>
                </View>
                
                {ticket.assigned_to && (
                  <View style={styles.ticketDetailRow}>
                    <Ionicons name="person-circle" size={16} color={colors.gray} />
                    <Text style={styles.ticketDetailText}>Assigned: {ticket.assigned_to}</Text>
                  </View>
                )}
                
                <View style={styles.ticketDetailRow}>
                  <Ionicons name="time" size={16} color={colors.gray} />
                  <Text style={styles.ticketDetailText}>
                    {format(new Date(ticket.created_at), 'MMM dd, HH:mm')}
                  </Text>
                </View>
                
                {ticket.location && (
                  <View style={styles.ticketDetailRow}>
                    <Ionicons name="location" size={16} color={colors.gray} />
                    <Text style={styles.ticketDetailText}>{ticket.location}</Text>
                  </View>
                )}
              </View>

              {/* Ticket Actions */}
              <View style={styles.ticketActions}>
                {!ticket.assigned_to && (
                  <TouchableOpacity
                    style={styles.actionButton}
                    onPress={() => handleAssignTicket(ticket)}>
                    <Ionicons name="person-add" size={16} color={colors.primary} />
                    <Text style={styles.actionButtonText}>Assign</Text>
                  </TouchableOpacity>
                )}
                
                {ticket.status === 'open' && (
                  <TouchableOpacity
                    style={styles.actionButton}
                    onPress={() => handleStatusChange(ticket, 'in_progress')}>
                    <Ionicons name="play" size={16} color={colors.warning} />
                    <Text style={styles.actionButtonText}>Start</Text>
                  </TouchableOpacity>
                )}
                
                {ticket.status === 'in_progress' && (
                  <TouchableOpacity
                    style={styles.actionButton}
                    onPress={() => handleStatusChange(ticket, 'resolved')}>
                    <Ionicons name="checkmark" size={16} color={colors.success} />
                    <Text style={styles.actionButtonText}>Resolve</Text>
                  </TouchableOpacity>
                )}
              </View>
            </TouchableOpacity>
          ))
        ) : (
          <View style={styles.emptyState}>
            <Ionicons name="ticket" size={48} color={colors.gray} />
            <Text style={styles.emptyTitle}>No Tickets Found</Text>
            <Text style={styles.emptySubtitle}>
              {searchQuery ? 'Try adjusting your search or filters' : 'No tickets match your current filters'}
            </Text>
          </View>
        )}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: colors.white,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  backButton: {
    padding: 8,
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.text,
  },
  filterButton: {
    padding: 8,
  },
  searchSection: {
    padding: 16,
    backgroundColor: colors.white,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.background,
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  searchIcon: {
    marginRight: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: 16,
    color: colors.text,
  },
  filtersSection: {
    backgroundColor: colors.white,
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  filtersTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    marginBottom: 12,
  },
  filterRow: {
    marginBottom: 12,
  },
  filterLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.text,
    marginBottom: 8,
  },
  filterOptions: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  filterOption: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: colors.background,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
  },
  filterOptionActive: {
    backgroundColor: colors.primary,
    borderColor: colors.primary,
  },
  filterOptionText: {
    fontSize: 12,
    color: colors.gray,
  },
  filterOptionTextActive: {
    color: colors.white,
  },
  ticketsList: {
    flex: 1,
    padding: 16,
  },
  loader: {
    padding: 20,
  },
  ticketCard: {
    backgroundColor: colors.white,
    borderRadius: 8,
    padding: 16,
    marginBottom: 12,
    shadowColor: colors.black,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  ticketHeader: {
    marginBottom: 12,
  },
  ticketTitleRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  ticketTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.text,
    flex: 1,
    marginRight: 8,
  },
  ticketBadges: {
    flexDirection: 'row',
    gap: 4,
  },
  priorityBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  priorityText: {
    fontSize: 10,
    color: colors.white,
    fontWeight: '600',
  },
  statusBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  statusText: {
    fontSize: 10,
    color: colors.white,
    fontWeight: '600',
  },
  ticketDescription: {
    fontSize: 14,
    color: colors.gray,
    lineHeight: 20,
  },
  ticketDetails: {
    marginBottom: 12,
  },
  ticketDetailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  ticketDetailText: {
    fontSize: 12,
    color: colors.gray,
    marginLeft: 8,
  },
  ticketActions: {
    flexDirection: 'row',
    gap: 8,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: colors.background,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: colors.border,
  },
  actionButtonText: {
    fontSize: 12,
    color: colors.primary,
    marginLeft: 4,
    fontWeight: '500',
  },
  emptyState: {
    alignItems: 'center',
    padding: 40,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.text,
    marginTop: 16,
    marginBottom: 8,
  },
  emptySubtitle: {
    fontSize: 14,
    color: colors.gray,
    textAlign: 'center',
  },
});
