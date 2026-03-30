<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class RoomConfigurationStep extends Component
{
    public array $floors = [];
    public int $hostelId;
    public string $tenantId;
    
    protected $listeners = ['addFloor', 'removeFloor', 'updateFloorConfig'];

    public function mount(int $hostelId, string $tenantId)
    {
        $this->hostelId = $hostelId;
        $this->tenantId = $tenantId;
        $this->floors = [];
    }

    public function addFloor()
    {
        $nextFloorNumber = count($this->floors) + 1;
        
        $this->floors[] = [
            'floor_number' => $nextFloorNumber,
            'name' => "Floor {$nextFloorNumber}",
            'room_configs' => [
                [
                    'capacity' => 2,
                    'room_count' => 10,
                    'numbering_mode' => 'auto',
                    'room_prefix' => null,
                ]
            ],
        ];
    }

    public function removeFloor(int $index)
    {
        unset($this->floors[$index]);
        $this->floors = array_values($this->floors);
        
        // Re-number floors
        foreach ($this->floors as $i => $floor) {
            $this->floors[$i]['floor_number'] = $i + 1;
            if ($this->floors[$i]['name'] === "Floor " . ($i + 2)) {
                $this->floors[$i]['name'] = "Floor " . ($i + 1);
            }
        }
    }

    public function addRoomConfig(int $floorIndex)
    {
        $this->floors[$floorIndex]['room_configs'][] = [
            'capacity' => 1,
            'room_count' => 1,
            'numbering_mode' => 'auto',
            'room_prefix' => null,
        ];
    }

    public function removeRoomConfig(int $floorIndex, int $configIndex)
    {
        unset($this->floors[$floorIndex]['room_configs'][$configIndex]);
        $this->floors[$floorIndex]['room_configs'] = array_values($this->floors[$floorIndex]['room_configs']);
    }

    public function getPreview(): array
    {
        $preview = [];
        
        foreach ($this->floors as $floor) {
            $floorPreview = [
                'floor_number' => $floor['floor_number'],
                'name' => $floor['name'],
                'rooms' => [],
                'total_rooms' => 0,
                'total_beds' => 0,
            ];

            $roomSequence = 1;
            foreach ($floor['room_configs'] as $config) {
                for ($i = 0; $i < $config['room_count']; $i++) {
                    $roomNo = $this->generateRoomNumber(
                        $floor['floor_number'],
                        $roomSequence,
                        $config['numbering_mode'],
                        $config['room_prefix']
                    );

                    $floorPreview['rooms'][] = [
                        'room_no' => $roomNo,
                        'capacity' => $config['capacity'],
                    ];

                    $floorPreview['total_rooms']++;
                    $floorPreview['total_beds'] += $config['capacity'];
                    $roomSequence++;
                }
            }

            $preview[] = $floorPreview;
        }

        return $preview;
    }

    private function generateRoomNumber(int $floorNumber, int $sequence, string $mode, ?string $prefix): string
    {
        if ($mode === 'manual' && $prefix) {
            return $prefix . str_pad($sequence, 2, '0', STR_PAD_LEFT);
        }

        return sprintf('F%dR%02d', $floorNumber, $sequence);
    }

    public function getTotalStats(): array
    {
        $totalFloors = count($this->floors);
        $totalRooms = 0;
        $totalBeds = 0;

        foreach ($this->floors as $floor) {
            foreach ($floor['room_configs'] as $config) {
                $totalRooms += $config['room_count'];
                $totalBeds += $config['room_count'] * $config['capacity'];
            }
        }

        return [
            'floors' => $totalFloors,
            'rooms' => $totalRooms,
            'beds' => $totalBeds,
        ];
    }

    public function render()
    {
        return view('livewire.admin.room-configuration-step', [
            'preview' => $this->getPreview(),
            'stats' => $this->getTotalStats(),
        ]);
    }
}

