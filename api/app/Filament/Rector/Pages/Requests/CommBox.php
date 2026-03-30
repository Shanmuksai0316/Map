<?php

namespace App\Filament\Rector\Pages\Requests;

use App\Models\Notice;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CommBox extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationLabel = 'Notice Board';

    protected static ?string $navigationGroup = 'Communications';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.rector.pages.requests.comm-box';

    public function getHeading(): string
    {
        return 'Notice Board';
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id;
        $hasTargetTenantColumn = Schema::hasColumn('notices', 'target_tenant_id');
        $hasHostelColumn = Schema::hasColumn('notices', 'hostel_id');
        $hasTargetHostelColumn = Schema::hasColumn('notices', 'target_hostel_id');
        $assignedHostelIds = DB::table('staff_assignments')
            ->where('user_id', $user?->id)
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->whereNotNull('hostel_id')
            ->pluck('hostel_id')
            ->toArray();

        return $table
            ->query(function () use (
                $tenantId,
                $assignedHostelIds,
                $hasTargetTenantColumn,
                $hasHostelColumn,
                $hasTargetHostelColumn
            ): Builder {
                if (! $tenantId) {
                    return Notice::query()->whereRaw('1 = 0');
                }

                $query = Notice::query()
                    ->with(['hostel', 'createdBy']);

                if ($hasTargetHostelColumn) {
                    $query->with('targetHostel');
                }

                if ($hasTargetTenantColumn) {
                    $query->where(function (Builder $tenantScope) use ($tenantId): void {
                        $tenantScope
                            ->where('tenant_id', $tenantId)
                            ->orWhere('target_tenant_id', $tenantId);
                    });
                } else {
                    $query->where('tenant_id', $tenantId);
                }

                if (! empty($assignedHostelIds) && ($hasHostelColumn || $hasTargetHostelColumn)) {
                    $query->where(function (Builder $hostelScope) use ($assignedHostelIds, $hasHostelColumn, $hasTargetHostelColumn): void {
                        if ($hasHostelColumn && $hasTargetHostelColumn) {
                            $hostelScope
                                ->where(function (Builder $allHostels): void {
                                    $allHostels
                                        ->whereNull('hostel_id')
                                        ->whereNull('target_hostel_id');
                                })
                                ->orWhereIn('hostel_id', $assignedHostelIds)
                                ->orWhereIn('target_hostel_id', $assignedHostelIds);

                            return;
                        }

                        if ($hasHostelColumn) {
                            $hostelScope
                                ->whereNull('hostel_id')
                                ->orWhereIn('hostel_id', $assignedHostelIds);

                            return;
                        }

                        $hostelScope
                            ->whereNull('target_hostel_id')
                            ->orWhereIn('target_hostel_id', $assignedHostelIds);
                    });
                }

                return $query;
            })
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->formatStateUsing(fn ($state): string => 'CB-' . str_pad((string) $state, 4, '0', STR_PAD_LEFT))
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('hostel_label')
                    ->label('Hostel')
                    ->getStateUsing(fn (Notice $record): string => $record->hostel?->name ?? $record->targetHostel?->name ?? 'All Hostels'),
                BadgeColumn::make('audience')
                    ->label('Audience')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'students' => 'Students',
                        'staff' => 'Staff',
                        'both' => 'Both',
                        default => 'All',
                    })
                    ->colors([
                        'primary' => 'students',
                        'warning' => 'staff',
                        'success' => 'both',
                        'gray' => null,
                    ]),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) ($state ?: 'draft')))
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'scheduled',
                        'success' => 'published',
                    ]),
                TextColumn::make('publish_at')
                    ->label('Published')
                    ->formatStateUsing(function ($state): string {
                        if (! $state) {
                            return 'Immediate';
                        }

                        return \Carbon\Carbon::parse($state)
                            ->timezone('Asia/Kolkata')
                            ->format('d M Y, h:i A');
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                    ])
                    ->placeholder('All statuses'),
                SelectFilter::make('audience')
                    ->label('Audience')
                    ->options([
                        'students' => 'Students',
                        'staff' => 'Staff',
                        'both' => 'Both',
                    ])
                    ->placeholder('All audiences'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Communication Details')
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (Notice $record) {
                        $content = trim((string) ($record->body ?: $record->content ?: ''));
                        if ($content === '') {
                            $content = '—';
                        }

                        $channels = $record->channels;
                        if (is_string($channels) && $channels !== '') {
                            $decoded = json_decode($channels, true);
                            if (is_array($decoded)) {
                                $channels = $decoded;
                            }
                        }
                        $channelLabel = is_array($channels) && ! empty($channels)
                            ? implode(', ', array_map(static fn (string $channel): string => ucfirst($channel), $channels))
                            : 'Push';

                        $attachmentUrl = null;
                        if (! empty($record->attachment_url)) {
                            $attachmentUrl = str_starts_with($record->attachment_url, 'http')
                                ? $record->attachment_url
                                : Storage::disk('public')->url($record->attachment_url);
                        }

                        return view('filament.rector.pages.requests.comm-box-modal', [
                            'title' => $record->title,
                            'status' => ucfirst((string) ($record->status ?: 'draft')),
                            'audience' => match ($record->audience) {
                                'students' => 'Students',
                                'staff' => 'Staff',
                                'both' => 'Both',
                                default => 'All',
                            },
                            'hostel' => $record->hostel?->name ?? $record->targetHostel?->name ?? 'All Hostels',
                            'publishAt' => $record->publish_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') ?? 'Immediate',
                            'expiresAt' => $record->expires_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') ?? 'No expiry',
                            'createdBy' => $record->createdBy?->name ?? 'System',
                            'channels' => $channelLabel,
                            'content' => $content,
                            'attachmentUrl' => $attachmentUrl,
                        ]);
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('publish_at', 'desc')
            ->emptyStateHeading('No communication notices')
            ->emptyStateDescription('No notice board notices are available for your hostels.');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('Rector');
    }
}
