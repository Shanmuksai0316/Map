import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  Modal,
} from 'react-native';
import Ionicons from 'react-native-vector-icons/Ionicons';
import { useAuthStore } from '../../store/auth.store';
import { apiService } from '../../services/api.service';
import { APP_CONFIG } from '../../config/app.config';
import { colors } from '../../theme/colors';
import { format } from 'date-fns';

interface Announcement {
  id: number;
  title: string;
  description: string;
  date: string;
  created_at: string;
}

export const AnnouncementsScreen = ({ navigation }: any) => {
  const { user } = useAuthStore();
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedAnnouncement, setSelectedAnnouncement] = useState<Announcement | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);

  const fetchAnnouncements = async () => {
    try {
      const response = await apiService.get<{ data: Announcement[] }>(
        `${APP_CONFIG.ENDPOINTS.NOTICES}?type=announcement`
      );
      setAnnouncements(response.data);
    } catch (error) {
      console.error('Announcements fetch error:', error);
      // Mock data
      setAnnouncements([
        {
          id: 1,
          title: 'Campus Maintenance Notice',
          description: 'The campus will undergo maintenance work this weekend.',
          date: new Date().toISOString(),
          created_at: new Date().toISOString(),
        },
      ]);
    } finally {
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchAnnouncements();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchAnnouncements();
  };

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Announcements</Text>
      </View>

      <ScrollView
        style={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}>
        {announcements.map((announcement) => (
          <TouchableOpacity
            key={announcement.id}
            style={styles.announcementCard}
            onPress={() => {
              setSelectedAnnouncement(announcement);
              setShowDetailModal(true);
            }}>
            <View style={styles.announcementHeader}>
              <Text style={styles.announcementTitle}>{announcement.title}</Text>
              <Text style={styles.announcementDate}>
                {format(new Date(announcement.date), 'MMM dd, yyyy')}
              </Text>
            </View>
            <Text style={styles.announcementDescription} numberOfLines={2}>
              {announcement.description}
            </Text>
          </TouchableOpacity>
        ))}
      </ScrollView>

      {/* Detail Modal */}
      <Modal
        visible={showDetailModal}
        animationType="slide"
        transparent={false}
        onRequestClose={() => setShowDetailModal(false)}>
        <View style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>Announcement</Text>
            <TouchableOpacity
              onPress={() => setShowDetailModal(false)}
              style={styles.closeButton}>
              <Ionicons name="close" size={24} color={colors.surface} />
            </TouchableOpacity>
          </View>

          <ScrollView style={styles.modalContent}>
            {selectedAnnouncement && (
              <>
                <Text style={styles.modalAnnouncementTitle}>{selectedAnnouncement.title}</Text>
                <Text style={styles.modalAnnouncementDate}>
                  {format(new Date(selectedAnnouncement.date), 'MMM dd, yyyy')}
                </Text>
                <Text style={styles.modalAnnouncementDescription}>
                  {selectedAnnouncement.description}
                </Text>
              </>
            )}
          </ScrollView>
        </View>
      </Modal>
    </View>
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
  headerTitle: {
    color: colors.surface,
    fontSize: 24,
    fontWeight: 'bold',
  },
  content: {
    flex: 1,
    padding: 20,
  },
  announcementCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  announcementHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  announcementTitle: {
    flex: 1,
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginRight: 12,
  },
  announcementDate: {
    fontSize: 12,
    color: colors.textMuted,
  },
  announcementDescription: {
    fontSize: 14,
    color: colors.textMuted,
    lineHeight: 20,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: colors.background,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: colors.primary,
    padding: 20,
    paddingTop: 60,
  },
  modalTitle: {
    color: colors.surface,
    fontSize: 20,
    fontWeight: 'bold',
  },
  closeButton: {
    padding: 8,
  },
  modalContent: {
    flex: 1,
    padding: 20,
  },
  modalAnnouncementTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  modalAnnouncementDate: {
    fontSize: 14,
    color: colors.textMuted,
    marginBottom: 20,
  },
  modalAnnouncementDescription: {
    fontSize: 16,
    color: colors.textPrimary,
    lineHeight: 24,
  },
});

