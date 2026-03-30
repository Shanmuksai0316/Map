import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import { SvgXml } from 'react-native-svg';
import type { ActionTile } from '../types';

interface ActionTilesGridProps {
  tiles: ActionTile[];
  columns?: 2 | 3;
}

export const ActionTilesGrid: React.FC<ActionTilesGridProps> = ({
  tiles,
  columns = 2,
}) => {
  const renderTile = (tile: ActionTile) => {
    const hasSvg = !!tile.iconSvgXml;
    const iconSize = 76; // SVGs include their own background circle
    const normalizedId = String(tile.id ?? '').toLowerCase().replace(/[^a-z0-9]/g, '');
    const normalizedTitle = String(tile.title ?? '').toLowerCase().replace(/[^a-z0-9]/g, '');
    const isCommBoxTile = normalizedId.includes('commbox') || normalizedTitle.includes('commbox');
    const showBadge = !isCommBoxTile && tile.badge !== undefined && tile.badge > 0;

    return (
    <TouchableOpacity
      key={tile.id}
      style={[styles.tile, { flex: 1 / columns }]}
      onPress={tile.onPress}
      activeOpacity={0.7}
    >
      <View
        style={[
          styles.iconContainer,
          hasSvg ? styles.iconContainerSvg : { backgroundColor: tile.color + '20' },
        ]}
      >
        {hasSvg ? (
          <SvgXml xml={tile.iconSvgXml!} width={iconSize} height={iconSize} />
        ) : (
          <Icon name={tile.icon ?? 'shape'} size={44} color={tile.color} />
        )}
        {showBadge && (
          <View style={styles.badge}>
            <Text style={styles.badgeText}>
              {tile.badge > 99 ? '99+' : tile.badge}
            </Text>
          </View>
        )}
      </View>
      <Text style={styles.tileTitle} numberOfLines={2}>
        {tile.title}
      </Text>
    </TouchableOpacity>
    );
  };

  // Group tiles into rows
  const rows: ActionTile[][] = [];
  for (let i = 0; i < tiles.length; i += columns) {
    rows.push(tiles.slice(i, i + columns));
  }

  return (
    <View style={styles.container}>
      {rows.map((row, rowIndex) => (
        <View key={rowIndex} style={styles.row}>
          {row.map(renderTile)}
          {/* Fill empty spaces in the last row */}
          {row.length < columns &&
            Array(columns - row.length)
              .fill(null)
              .map((_, i) => <View key={`empty-${i}`} style={{ flex: 1 / columns }} />)}
        </View>
      ))}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: 0,
    paddingVertical: 16,
  },
  row: {
    flexDirection: 'row',
    marginBottom: 20,
    gap: 20,
  },
  tile: {
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 10,
    backgroundColor: '#FFFFFF',
    borderRadius: 26,
    borderWidth: 1,
    borderColor: 'rgba(255, 165, 0, 0.16)',
    shadowColor: '#FFA500',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.16,
    shadowRadius: 8,
    elevation: 4,
  },
  iconContainer: {
    width: 104,
    height: 104,
    borderRadius: 28,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
    position: 'relative',
  },
  iconContainerSvg: {
    backgroundColor: 'transparent',
  },
  badge: {
    position: 'absolute',
    top: -4,
    right: -4,
    backgroundColor: '#EF4444',
    borderRadius: 10,
    minWidth: 20,
    height: 20,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 6,
  },
  badgeText: {
    color: '#FFFFFF',
    fontSize: 11,
    fontWeight: '700',
  },
  tileTitle: {
    fontSize: 16,
    fontWeight: '500',
    color: '#374151',
    textAlign: 'center',
    lineHeight: 22,
  },
});

export default ActionTilesGrid;
