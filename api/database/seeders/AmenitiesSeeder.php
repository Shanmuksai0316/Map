<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Amenity;
use Illuminate\Support\Facades\DB;

class AmenitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $amenities = [
            ['key' => 'wifi', 'label' => 'WiFi'],
            ['key' => 'gym', 'label' => 'Gym'],
            ['key' => 'laundry', 'label' => 'Laundry'],
            ['key' => 'ac', 'label' => 'Air Conditioning'],
            ['key' => 'mess', 'label' => 'Mess/Canteen'],
            ['key' => 'study_room', 'label' => 'Study Room'],
            ['key' => 'common_room', 'label' => 'Common Room'],
            ['key' => 'security', 'label' => '24/7 Security'],
            ['key' => 'library', 'label' => 'Library'],
            ['key' => 'parking', 'label' => 'Parking'],
        ];

        foreach ($amenities as $amenity) {
            Amenity::firstOrCreate(
                ['key' => $amenity['key']],
                ['label' => $amenity['label']]
            );
        }

        $this->command->info('✅ Amenities seeded successfully!');
    }
}

