import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { ActionTilesGrid } from '../../shared/components/ActionTilesGrid';
import type { ActionTile } from '../../shared/types';

describe('ActionTilesGrid', () => {
  const mockTiles: ActionTile[] = [
    {
      id: 'tile-1',
      title: 'Test Tile 1',
      icon: 'home',
      color: '#3B82F6',
      onPress: jest.fn(),
    },
    {
      id: 'tile-2',
      title: 'Test Tile 2',
      icon: 'settings',
      color: '#10B981',
      badge: 5,
      onPress: jest.fn(),
    },
  ];

  it('renders all tiles', () => {
    const { getByText } = render(<ActionTilesGrid tiles={mockTiles} columns={2} />);
    expect(getByText('Test Tile 1')).toBeTruthy();
    expect(getByText('Test Tile 2')).toBeTruthy();
  });

  it('displays badge when provided', () => {
    const { getByText } = render(<ActionTilesGrid tiles={mockTiles} columns={2} />);
    expect(getByText('5')).toBeTruthy();
  });

  it('calls onPress when tile is pressed', () => {
    const { getByText } = render(<ActionTilesGrid tiles={mockTiles} columns={2} />);
    fireEvent.press(getByText('Test Tile 1'));
    expect(mockTiles[0].onPress).toHaveBeenCalled();
  });

  it('renders with different column counts', () => {
    const { rerender, getByTestId } = render(
      <ActionTilesGrid tiles={mockTiles} columns={4} />
    );
    expect(getByTestId('action-tiles-grid')).toBeTruthy();

    rerender(<ActionTilesGrid tiles={mockTiles} columns={2} />);
    expect(getByTestId('action-tiles-grid')).toBeTruthy();
  });
});

