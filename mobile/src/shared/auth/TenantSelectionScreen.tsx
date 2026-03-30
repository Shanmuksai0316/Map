import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Alert,
  FlatList,
  SafeAreaView,
} from 'react-native';
import { RadialGradientButton } from '../components/RadialGradientButton';
import { tenantService, Tenant } from '../services/tenant.service';
import { APP_CONFIG } from '../config/app.config';

interface TenantSelectionScreenProps {
  onTenantSelected: (tenant: Tenant) => void;
}

export const TenantSelectionScreen: React.FC<TenantSelectionScreenProps> = ({
  onTenantSelected,
}) => {
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadTenants();
  }, []);

  const loadTenants = async () => {
    try {
      setLoading(true);
      setError(null);
      const tenantList = await tenantService.getTenantList();
      setTenants(tenantList);
    } catch (err: any) {
      setError(err.message || 'Failed to load tenants');
      console.error('Error loading tenants:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleTenantSelect = async (tenant: Tenant) => {
    await tenantService.setSelectedTenant(tenant);
    onTenantSelected(tenant);
  };

  const renderTenantItem = ({ item }: { item: Tenant }) => (
    <TouchableOpacity
      style={styles.tenantItem}
      onPress={() => handleTenantSelect(item)}
    >
      <View style={styles.tenantInfo}>
        <Text style={styles.tenantName}>{item.name}</Text>
        <Text style={styles.tenantCode}>{item.code}</Text>
        <Text style={styles.tenantDomain}>{item.domain}</Text>
      </View>
      <View style={styles.arrow}>
        <Text style={styles.arrowText}>→</Text>
      </View>
    </TouchableOpacity>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#1E56D9" />
          <Text style={styles.loadingText}>Loading tenants...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (error) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.errorContainer}>
          <Text style={styles.errorTitle}>Failed to Load Tenants</Text>
          <Text style={styles.errorMessage}>{error}</Text>
          <RadialGradientButton
            label="Retry"
            onPress={loadTenants}
            style={styles.retryButton}
            contentStyle={styles.retryButtonContent}
          />
        </View>
      </SafeAreaView>
    );
  }

  if (tenants.length === 0) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.emptyContainer}>
          <Text style={styles.emptyTitle}>No Tenants Available</Text>
          <Text style={styles.emptyMessage}>
            No institutions are currently available. Please contact support.
          </Text>
          <RadialGradientButton
            label="Refresh"
            onPress={loadTenants}
            style={styles.retryButton}
            contentStyle={styles.retryButtonContent}
          />
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Select Institution</Text>
        <Text style={styles.subtitle}>
          Choose your institution to continue
        </Text>
      </View>

      <FlatList
        data={tenants}
        renderItem={renderTenantItem}
        keyExtractor={(item) => item.id}
        style={styles.list}
        showsVerticalScrollIndicator={false}
      />
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  header: {
    padding: 24,
    paddingTop: 16,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#1e293b',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: '#64748b',
  },
  list: {
    flex: 1,
    padding: 16,
  },
  tenantItem: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    padding: 20,
    marginBottom: 12,
    flexDirection: 'row',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 1,
    },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  tenantInfo: {
    flex: 1,
  },
  tenantName: {
    fontSize: 18,
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: 4,
  },
  tenantCode: {
    fontSize: 14,
    color: '#64748b',
    marginBottom: 2,
  },
  tenantDomain: {
    fontSize: 12,
    color: '#94a3b8',
  },
  arrow: {
    marginLeft: 12,
  },
  arrowText: {
    fontSize: 20,
    color: '#64748b',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: '#64748b',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  errorTitle: {
    fontSize: 20,
    fontWeight: '600',
    color: '#dc2626',
    marginBottom: 8,
  },
  errorMessage: {
    fontSize: 16,
    color: '#64748b',
    textAlign: 'center',
    marginBottom: 24,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  emptyTitle: {
    fontSize: 20,
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: 8,
  },
  emptyMessage: {
    fontSize: 16,
    color: '#64748b',
    textAlign: 'center',
    marginBottom: 24,
  },
  retryButton: {
    minWidth: 180,
  },
  retryButtonContent: {
    minHeight: 44,
    paddingHorizontal: 24,
  },
});
