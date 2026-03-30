<?php

use App\Domain\Checklists\Models\ChecklistTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $canonicalRole = function (string $role): string {
            $role = trim($role);
            $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', $role) ?? '');
            return match ($key) {
                'campusmanager', 'campusmanagerdashboard', 'campusmanagerpanel' => 'CampusManager',
                'warden' => 'Warden',
                'hksupervisor', 'housekeeping', 'housekeepingsupervisor' => 'HKSupervisor',
                'rmsupervisor', 'repairsupervisor', 'maintenancesupervisor', 'roomsupervisor' => 'RMSupervisor',
                'guard', 'security', 'securityguard' => 'Guard',
                'laundrymanager', 'laundry' => 'LaundryManager',
                'sportsmanager', 'sports' => 'SportsManager',
                default => $role,
            };
        };

        // 1) Add requirement flags to checklist_items so mobile/web can enforce them.
        Schema::table('checklist_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('checklist_items', 'require_photo')) {
                $table->boolean('require_photo')->default(false)->after('label');
            }
            if (! Schema::hasColumn('checklist_items', 'require_comment')) {
                $table->boolean('require_comment')->default(false)->after('require_photo');
            }
        });

        // 2) Normalize + dedupe templates so each tenant has ONE template per role.
        // We preserve history by "archiving" extra templates (do NOT delete, instances FK would cascade).
        DB::transaction(function () use ($canonicalRole): void {
            // First normalize existing role values to canonical keys where possible.
            $templatesToNormalize = DB::table('checklist_templates')
                ->select('id', 'role')
                ->where('role', 'not like', '%__ARCHIVED__%')
                ->get();

            foreach ($templatesToNormalize as $row) {
                $newRole = ($canonicalRole)((string) $row->role);
                if ($newRole !== (string) $row->role) {
                    DB::table('checklist_templates')->where('id', $row->id)->update(['role' => $newRole]);
                }
            }

            /** @var \Illuminate\Support\Collection<int, object> $pairs */
            $pairs = DB::table('checklist_templates')
                ->select('tenant_id', 'role', DB::raw('COUNT(*) as c'))
                ->groupBy('tenant_id', 'role')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($pairs as $pair) {
                $tenantId = $pair->tenant_id;
                $role = ($canonicalRole)((string) $pair->role);

                /** @var \Illuminate\Support\Collection<int, ChecklistTemplate> $templates */
                $templates = ChecklistTemplate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('role', $role)
                    ->orderBy('id')
                    ->get();

                if ($templates->isEmpty()) {
                    continue;
                }

                $keeper = $templates->first();

                // Merge tasks from all templates, normalize to {code,label,require_photo,require_comment}, keep max 10.
                $merged = [];
                $seenCodes = [];

                foreach ($templates as $t) {
                    $rawTasks = is_array($t->tasks) ? $t->tasks : [];
                    foreach ($rawTasks as $idx => $task) {
                        if (is_string($task)) {
                            $label = $task;
                            $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $label));
                            $code = trim(substr($code, 0, 30), '_');
                            if ($code === '') {
                                $code = 'TASK_' . ($idx + 1);
                            }
                            $normalized = [
                                'code' => $code,
                                'label' => $label,
                                'require_photo' => false,
                                'require_comment' => false,
                            ];
                        } elseif (is_array($task)) {
                            $label = (string) ($task['label'] ?? $task['title'] ?? ('Task ' . ($idx + 1)));
                            $code = (string) ($task['code'] ?? '');
                            if ($code === '') {
                                $code = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $label));
                                $code = trim(substr($code, 0, 30), '_');
                                if ($code === '') {
                                    $code = 'TASK_' . ($idx + 1);
                                }
                            }
                            $normalized = [
                                'code' => $code,
                                'label' => $label,
                                'require_photo' => (bool) ($task['require_photo'] ?? false),
                                'require_comment' => (bool) ($task['require_comment'] ?? false),
                            ];
                        } else {
                            continue;
                        }

                        if (isset($seenCodes[$normalized['code']])) {
                            continue;
                        }

                        $seenCodes[$normalized['code']] = true;
                        $merged[] = $normalized;

                        if (count($merged) >= 10) {
                            break 2;
                        }
                    }
                }

                $keeper->forceFill([
                    'title' => "{$role} Daily Checklist",
                    'tasks' => $merged,
                    'active' => true,
                ])->save();

                foreach ($templates->slice(1) as $extra) {
                    $extra->forceFill([
                        'active' => false,
                        // Avoid unique (tenant_id, role) collisions.
                        'role' => $role . '__ARCHIVED__' . $extra->id,
                        'title' => ($extra->title ?: "{$role} Checklist") . ' (Archived)',
                    ])->save();
                }
            }
        }, 3);

        // 3) Enforce one template per role per tenant (after dedupe).
        // (We keep role strings unique per tenant; archived templates have role suffixed.)
        Schema::table('checklist_templates', function (Blueprint $table): void {
            // Laravel can't easily check index existence across DBs; wrap in try/catch.
            try {
                $table->unique(['tenant_id', 'role'], 'checklist_templates_tenant_role_unique');
            } catch (\Throwable $e) {
                // ignore if already exists
            }
        });
    }

    public function down(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table): void {
            try {
                $table->dropUnique('checklist_templates_tenant_role_unique');
            } catch (\Throwable $e) {
                // ignore
            }
        });

        Schema::table('checklist_items', function (Blueprint $table): void {
            if (Schema::hasColumn('checklist_items', 'require_comment')) {
                $table->dropColumn('require_comment');
            }
            if (Schema::hasColumn('checklist_items', 'require_photo')) {
                $table->dropColumn('require_photo');
            }
        });
    }
};

