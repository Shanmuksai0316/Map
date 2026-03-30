import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
} from 'react-native';
import { GradientButton } from './GradientButton';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { Calendar } from './Calendar';

interface HistoryItem {
  id: number | string;
  title: string;
  subtitle?: string;
  status: string;
  statusColor: string;
  timestamp: string;
  type: string;
  metadata?: Record<string, string | number>;
}

interface StaffHistoryScreenProps {
  title: string;
  items: HistoryItem[];
  filters: { id: string; label: string }[];
  selectedFilter: string;
  onFilterChange: (filter: string) => void;
  onDateChange: (date: Date) => void;
  selectedDate: Date;
  onItemPress?: (item: HistoryItem) => void;
  isLoading?: boolean;
  onRefresh?: () => void;
  onLoadMore?: () => void;
  hasMore?: boolean;
}

export const StaffHistoryScreen: React.FC<StaffHistoryScreenProps> = ({
  title,
  items,
  filters,
  selectedFilter,
  onFilterChange,
  onDateChange,
  selectedDate,
  onItemPress,
  isLoading = false,
  onRefresh,
  onLoadMore,
  hasMore = false,
}) => {
  const [showCalendar, setShowCalendar] = useState(false);

  const formatTimestamp = (timestamp: string) => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
    });
  };

  const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  };

  const renderItem = useCallback(
    ({ item }: { item: HistoryItem }) => (
      <TouchableOpacity
        style={styles.itemContainer}
        onPress={() => onItemPress?.(item)}
        disabled={!onItemPress}
        activeOpacity={onItemPress ? 0.7 : 1}
      >
        <View style={styles.itemContent}>
          <View style={styles.itemHeader}>
            <Text style={styles.itemTitle} numberOfLines={1}>
              {item.title}
            </Text>
            <View
              style={[
                styles.statusBadge,
                { backgroundColor: item.statusColor + '20' },
              ]}
            >
              <Text style={[styles.statusText, { color: item.statusColor }]}>
                {item.status}
              </Text>
            </View>
          </View>
          {item.subtitle && (
            <Text style={styles.itemSubtitle}>{item.subtitle}</Text>
          )}
          <View style={styles.itemFooter}>
            <Icon name="clock-outline" size={14} color="#9CA3AF" />
            <Text style={styles.timestamp}>{formatTimestamp(item.timestamp)}</Text>
            <View style={styles.typeBadge}>
              <Text style={styles.typeText}>{item.type}</Text>
            </View>
          </View>
        </View>
        {onItemPress && (
          <Icon name="chevron-right" size={20} color="#D1D5DB" />
        )}
      </TouchableOpacity>
    ),
    [onItemPress]
  );

  const renderHeader = () => (
    <View style={styles.headerContainer}>
      {/* Date Selector */}
      <TouchableOpacity
        style={styles.dateSelector}
        onPress={() => setShowCalendar(true)}
      >
        <Icon name="calendar" size={20} color="#3B82F6" />
        <Text style={styles.dateText}>{formatDate(selectedDate)}</Text>
        <Icon name="chevron-down" size={20} color="#6B7280" />
      </TouchableOpacity>

      {/* Filter Pills */}
      <FlatList
        horizontal
        data={filters}
        keyExtractor={item => item.id}
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.filterContainer}
        renderItem={({ item }) => (
          <TouchableOpacity
            style={[
              styles.filterPill,
              selectedFilter === item.id && styles.filterPillActive,
            ]}
            onPress={() => onFilterChange(item.id)}
          >
            <Text
              style={[
                styles.filterText,
                selectedFilter === item.id && styles.filterTextActive,
              ]}
            >
              {item.label}
            </Text>
          </TouchableOpacity>
        )}
      />
    </View>
  );

  const renderEmpty = () => (
    <View style={styles.emptyContainer}>
      <Icon name="history" size={64} color="#D1D5DB" />
      <Text style={styles.emptyTitle}>No History</Text>
      <Text style={styles.emptySubtitle}>
        No records found for the selected date and filter
      </Text>
    </View>
  );

  const renderFooter = () => {
    if (!hasMore) return null;
    return (
      <GradientButton style={styles.loadMoreButton} onPress={onLoadMore}>
        <Text style={styles.loadMoreText}>Load More</Text>
      </GradientButton>
    );
  };

  return (
    <View style={styles.container}>
      <View style={styles.titleContainer}>
        <Text style={styles.title}>{title}</Text>
      </View>

      <FlatList
        data={items}
        keyExtractor={item => String(item.id)}
        renderItem={renderItem}
        ListHeaderComponent={renderHeader}
        ListEmptyComponent={renderEmpty}
        ListFooterComponent={renderFooter}
        contentContainerStyle={styles.listContent}
        refreshControl={
          onRefresh ? (
            <RefreshControl refreshing={isLoading} onRefresh={onRefresh} />
          ) : undefined
        }
      />

      {/* Calendar Modal */}
      {showCalendar && (
        <Calendar
          selectedDate={selectedDate}
          onDateSelect={(date) => {
            onDateChange(date);
            setShowCalendar(false);
          }}
          onClose={() => setShowCalendar(false)}
        />
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F9FAFB',
  },
  titleContainer: {
    padding: 20,
    backgroundColor: '#FFFFFF',
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
  },
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: '#1F2937',
  },
  headerContainer: {
    padding: 16,
    backgroundColor: '#FFFFFF',
    marginBottom: 8,
  },
  dateSelector: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F3F4F6',
    padding: 12,
    borderRadius: 12,
    marginBottom: 16,
  },
  dateText: {
    flex: 1,
    marginLeft: 10,
    fontSize: 16,
    fontWeight: '500',
    color: '#1F2937',
  },
  filterContainer: {
    paddingVertical: 4,
    gap: 8,
  },
  filterPill: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#F3F4F6',
    marginRight: 8,
  },
  filterPillActive: {
    backgroundColor: '#3B82F6',
  },
  filterText: {
    fontSize: 14,
    fontWeight: '500',
    color: '#6B7280',
  },
  filterTextActive: {
    color: '#FFFFFF',
  },
  listContent: {
    flexGrow: 1,
  },
  itemContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    padding: 16,
    marginHorizontal: 16,
    marginVertical: 4,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  itemContent: {
    flex: 1,
  },
  itemHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  itemTitle: {
    flex: 1,
    fontSize: 16,
    fontWeight: '600',
    color: '#1F2937',
    marginRight: 8,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
  },
  itemSubtitle: {
    fontSize: 14,
    color: '#6B7280',
    marginBottom: 8,
  },
  itemFooter: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  timestamp: {
    fontSize: 13,
    color: '#9CA3AF',
    marginLeft: 4,
    marginRight: 12,
  },
  typeBadge: {
    backgroundColor: '#F3F4F6',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
  },
  typeText: {
    fontSize: 11,
    fontWeight: '500',
    color: '#6B7280',
    textTransform: 'uppercase',
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
    marginTop: 48,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#6B7280',
    marginTop: 16,
  },
  emptySubtitle: {
    fontSize: 14,
    color: '#9CA3AF',
    textAlign: 'center',
    marginTop: 8,
  },
  loadMoreButton: {
    padding: 16,
    alignItems: 'center',
  },
  loadMoreText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#3B82F6',
  },
});

export default StaffHistoryScreen;

