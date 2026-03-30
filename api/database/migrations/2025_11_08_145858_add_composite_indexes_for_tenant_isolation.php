<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add composite indexes for tenant isolation
 * 
 * This migration adds composite indexes (tenant_id, ...) to improve query performance
 * for tenant-scoped queries. These indexes are critical for the single shared database
 * architecture where all queries filter by tenant_id.
 */
return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $index = DB::selectOne(
                "SELECT 1 FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
                [$table, $indexName]
            );
            return (bool) $index;
        }

        $index = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
            [$table, $indexName]
        );
        return (bool) $index;
    }

    /**
     * Tables and their composite indexes to create
     * Format: ['table_name' => [['columns' => ['tenant_id', 'col1', 'col2'], 'name' => 'index_name']]]
     */
    private array $indexes = [
        'users' => [
            ['columns' => ['tenant_id', 'email'], 'name' => 'users_tenant_id_email_idx'],
            ['columns' => ['tenant_id', 'phone'], 'name' => 'users_tenant_id_phone_idx'],
            ['columns' => ['tenant_id', 'role'], 'name' => 'users_tenant_id_role_idx'],
        ],
        'students' => [
            ['columns' => ['tenant_id', 'student_uid'], 'name' => 'students_tenant_id_student_uid_idx'],
            ['columns' => ['tenant_id', 'hostel_id'], 'name' => 'students_tenant_id_hostel_id_idx'],
        ],
        'hostels' => [
            ['columns' => ['tenant_id', 'code'], 'name' => 'hostels_tenant_id_code_idx'],
            ['columns' => ['tenant_id', 'campus_id'], 'name' => 'hostels_tenant_id_campus_id_idx'],
        ],
        'rooms' => [
            ['columns' => ['tenant_id', 'hostel_id'], 'name' => 'rooms_tenant_id_hostel_id_idx'],
            ['columns' => ['tenant_id', 'hostel_id', 'number'], 'name' => 'rooms_tenant_id_hostel_id_number_idx'],
        ],
        'room_beds' => [
            ['columns' => ['tenant_id', 'room_id'], 'name' => 'room_beds_tenant_id_room_id_idx'],
            ['columns' => ['tenant_id', 'hostel_id'], 'name' => 'room_beds_tenant_id_hostel_id_idx'],
        ],
        'room_allocations' => [
            ['columns' => ['tenant_id', 'student_id'], 'name' => 'room_allocations_tenant_id_student_id_idx'],
            ['columns' => ['tenant_id', 'room_bed_id'], 'name' => 'room_allocations_tenant_id_room_bed_id_idx'],
            ['columns' => ['tenant_id', 'hostel_id'], 'name' => 'room_allocations_tenant_id_hostel_id_idx'],
        ],
        'tickets' => [
            ['columns' => ['tenant_id', 'status'], 'name' => 'tickets_tenant_id_status_idx'],
            ['columns' => ['tenant_id', 'created_by'], 'name' => 'tickets_tenant_id_created_by_idx'],
        ],
        'gate_entries' => [
            ['columns' => ['tenant_id', 'hostel_id'], 'name' => 'gate_entries_tenant_id_hostel_id_idx'],
            ['columns' => ['tenant_id', 'created_at'], 'name' => 'gate_entries_tenant_id_created_at_idx'],
        ],
        'attendance_sessions' => [
            ['columns' => ['tenant_id', 'hostel_id'], 'name' => 'attendance_sessions_tenant_id_hostel_id_idx'],
            ['columns' => ['tenant_id', 'date'], 'name' => 'attendance_sessions_tenant_id_date_idx'],
        ],
        'out_passes' => [
            ['columns' => ['tenant_id', 'student_id'], 'name' => 'out_passes_tenant_id_student_id_idx'],
            ['columns' => ['tenant_id', 'status'], 'name' => 'out_passes_tenant_id_status_idx'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexDefinitions) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexDefinitions as $indexDef) {
                $indexName = $indexDef['name'];
                $columns = $indexDef['columns'];

                // Skip if referenced columns are missing
                $allColumnsExist = collect($columns)->every(fn (string $column): bool => Schema::hasColumn($table, $column));
                if (! $allColumnsExist) {
                    continue;
                }

                if ($this->indexExists($table, $indexName)) {
                    continue;
                }

                // Create composite index
                DB::statement("
                    CREATE INDEX {$indexName} 
                    ON {$table} (" . implode(', ', $columns) . ")
                ");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexDefinitions) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexDefinitions as $indexDef) {
                $indexName = $indexDef['name'];

                if ($this->indexExists($table, $indexName)) {
                    DB::statement("DROP INDEX IF EXISTS {$indexName}");
                }
            }
        }
    }
};
