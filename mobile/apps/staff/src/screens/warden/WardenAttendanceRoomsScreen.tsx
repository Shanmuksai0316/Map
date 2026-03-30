import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Alert,
} from 'react-native';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { HostelRoom } from '../../types';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { useOfflineQueue } from '../../hooks/useOfflineQueue';
import { OfflineIndicator } from '../../components/shared/OfflineIndicator';
import { format } from 'date-fns';

export const WardenAttendanceRoomsScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const { addAction, isOnline } = useOfflineQueue();
  const [rooms, setRooms] = useState<HostelRoom[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);

  const fetchRooms = async () => {
    try {
      const response = await apiService.get<{ data: HostelRoom[] }>(APP_CONFIG.ENDPOINTS.WARDEN_ROOMS);
      setRooms(response.data);
    } catch (error) {
      console.error('Rooms fetch error:', error);
      // Mock data for demo
      setRooms([
        {
          id: 1,
          room_number: '101',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          capacity: 4,
          allocated_students: 4,
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 2,
          room_number: '102',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          capacity: 4,
          allocated_students: 3,
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 3,
          room_number: '201',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          capacity: 4,
          allocated_students: 4,
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
        {
          id: 4,
          room_number: '202',
          hostel_id: 1,
          hostel_name: 'Hostel A',
          capacity: 4,
          allocated_students: 2,
          tenant_id: 'tenant_1',
          created_at: '2025-01-01T00:00:00Z',
          updated_at: '2025-01-01T00:00:00Z',
        },
      ]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchRooms();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchRooms();
  };

  const handleRoomPress = (room: HostelRoom) => {
    navigation.navigate('WardenAttendanceDetail', { roomId: room.id, room });
  };

  const RoomCard = ({ room }: { room: HostelRoom }) => {
    const occupancyRate = Math.round((room.allocated_students / room.capacity) * 100);

    return (
      <TouchableOpacity
        style={styles.roomCard}
        onPress={() => handleRoomPress(room)}>
        <View style={styles.roomHeader}>
          <View>
            <Text style={styles.roomNumber}>{room.room_number}</Text>
            <Text style={styles.hostelName}>{room.hostel_name}</Text>
          </View>
          <View style={styles.occupancyBadge}>
            <Text style={styles.occupancyText}>{occupancyRate}%</Text>
          </View>
        </View>

        <View style={styles.roomStats}>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{room.allocated_students}</Text>
            <Text style={styles.statLabel}>Students</Text>
          </View>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{room.capacity}</Text>
            <Text style={styles.statLabel}>Capacity</Text>
          </View>
        </View>

        <View style={styles.progressBar}>
          <View
            style={[
              styles.progressFill,
              {
                width: `${occupancyRate}%`,
                backgroundColor: occupancyRate >= 90 ? colors.warning : colors.success,
              },
            ]}
          />
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }>
      <OfflineIndicator />
      
      {/* Header */}
      <View style={styles.header}>
        <View>
          <Text style={styles.title}>Attendance</Text>
          <Text style={styles.date}>{format(new Date(), 'EEEE, MMMM dd, yyyy')}</Text>
          <Text style={styles.subGreeting}>Select a room to mark attendance</Text>
        </View>
      </View>

      {/* Room List */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Rooms ({rooms.length})</Text>
        {rooms.map((room) => (
          <RoomCard key={room.id} room={room} />
        ))}
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  title: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  date: {
    color: colors.surface,
    fontSize: 16,
    fontWeight: '500',
    marginTop: 4,
    opacity: 0.9,
  },
  subGreeting: {
    color: colors.surface,
    fontSize: 14,
    opacity: 0.8,
    marginTop: 8,
  },
  section: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: colors.textPrimary,
    marginBottom: 16,
  },
  roomCard: {
    backgroundColor: colors.surface,
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  roomHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  roomNumber: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.textPrimary,
  },
  hostelName: {
    fontSize: 14,
    color: colors.textMuted,
    marginTop: 2,
  },
  occupancyBadge: {
    backgroundColor: colors.info,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  occupancyText: {
    color: colors.surface,
    fontSize: 12,
    fontWeight: '600',
  },
  roomStats: {
    flexDirection: 'row',
    marginBottom: 12,
  },
  statItem: {
    flex: 1,
    alignItems: 'center',
  },
  statValue: {
    fontSize: 18,
    fontWeight: 'bold',
    color: colors.textPrimary,
  },
  statLabel: {
    fontSize: 12,
    color: colors.textMuted,
    marginTop: 2,
  },
  progressBar: {
    height: 4,
    backgroundColor: colors.border,
    borderRadius: 2,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    borderRadius: 2,
  },
});
