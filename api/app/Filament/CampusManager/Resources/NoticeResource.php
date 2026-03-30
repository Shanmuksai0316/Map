<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\NoticeResource\Pages;
use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Notice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NoticeResource extends Resource
{
    protected static ?string $model = Notice::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Communications';

    protected static ?string $navigationLabel = 'Notice Board';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Notice';

    protected static ?string $pluralModelLabel = 'Notice Board';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notice Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('body')
                            ->label('Content')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'bulletList',
                                'h2',
                                'h3',
                                'italic',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ]),
                    ]),

                Forms\Components\Section::make('Targeting')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('campus_id')
                            ->label('Campus')
                            ->options(function () {
                                $tenantId = null;
                                try {
                                    if (function_exists('tenant') && tenant()) {
                                        $tenantId = tenant()->id;
                                    }
                                } catch (\Exception $e) {}
                                
                                if (!$tenantId) {
                                    $user = auth()->user();
                                    $tenantId = $user?->tenant_id;
                                }
                                
                                $query = Campus::query();
                                if ($tenantId) {
                                    $query->where('tenant_id', $tenantId);
                                }
                                return $query->orderBy('name')->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('hostel_id')
                            ->label('Hostel')
                            ->options(function () {
                                $tenantId = null;
                                try {
                                    if (function_exists('tenant') && tenant()) {
                                        $tenantId = tenant()->id;
                                    }
                                } catch (\Exception $e) {}
                                
                                if (!$tenantId) {
                                    $user = auth()->user();
                                    $tenantId = $user?->tenant_id;
                                }
                                
                                $query = Hostel::query();
                                if ($tenantId) {
                                    $query->where('tenant_id', $tenantId);
                                }
                                return $query->orderBy('name')->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to target all hostels'),
                        Forms\Components\Select::make('audience')
                            ->label('Audience')
                            ->options([
                                'students' => 'Students Only',
                                'staff' => 'Staff Only',
                                'both' => 'Both Students & Staff',
                            ])
                            ->default('students')
                            ->required()
                            ->native(false),
                        Forms\Components\CheckboxList::make('channels')
                            ->label('Notification Channels')
                            ->options([
                                'push' => 'Push Notification',
                                'email' => 'Email',
                            ])
                            ->default(['push'])
                            ->columns(2)
                            ->helperText('Select channels to send notification'),
                    ]),

                Forms\Components\Section::make('Scheduling')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('publish_at')
                            ->label('Publish At')
                            ->helperText('Leave empty to publish immediately')
                            ->native(false)
                            ->seconds(false)
                            ->timezone('Asia/Kolkata'),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->helperText('Notice will be hidden after this date')
                            ->native(false)
                            ->seconds(false)
                            ->timezone('Asia/Kolkata')
                            ->after('publish_at'),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'scheduled' => 'Scheduled',
                                'published' => 'Published',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->status === 'published'),
                    ]),

                Forms\Components\Section::make('Attachments')
                    ->description('Upload documents or images (optional)')
                    ->schema([
                        Forms\Components\FileUpload::make('attachment')
                            ->label('Attachment')
                            ->disk('public')
                            ->directory('notices')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(5120) // 5MB
                            ->helperText('PDF or images only, max 5MB'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('audience')
                    ->label('Audience')
                    ->badge()
                    ->colors([
                        'primary' => 'students',
                        'warning' => 'staff',
                        'success' => 'both',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->searchable()
                    ->sortable()
                    ->placeholder('All Hostels'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'scheduled',
                        'success' => 'published',
                    ])
                    ->sortable(),
                Tables\Columns\IconColumn::make('push_notification')
                    ->label('Push')
                    ->boolean()
                    ->getStateUsing(function ($record) {
                        if (!$record) return false;
                        $channels = $record->channels ?? [];
                        return is_array($channels) && in_array('push', $channels);
                    })
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('email_notification')
                    ->label('Email')
                    ->boolean()
                    ->getStateUsing(function ($record) {
                        if (!$record) return false;
                        $channels = $record->channels ?? [];
                        return is_array($channels) && in_array('email', $channels);
                    })
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('publish_at')
                    ->label('Publish Date')
                    ->sortable()
                    ->formatStateUsing(function ($state): string {
                        return $state
                            ? \Carbon\Carbon::parse($state)->timezone('Asia/Kolkata')->format('d M Y H:i')
                            : 'Immediate';
                    }),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                    ]),
                Tables\Filters\SelectFilter::make('audience')
                    ->options([
                        'students' => 'Students',
                        'staff' => 'Staff',
                        'both' => 'Both',
                    ]),
                Tables\Filters\SelectFilter::make('hostel_id')
                    ->label('Hostel')
                    ->options(function () {
                        $tenantId = null;
                        try {
                            if (function_exists('tenant') && tenant()) {
                                $tenantId = tenant()->id;
                            }
                        } catch (\Exception $e) {}
                        
                        if (!$tenantId) {
                            $user = auth()->user();
                            $tenantId = $user?->tenant_id;
                        }
                        
                        $query = Hostel::query();
                        if ($tenantId) {
                            $query->where('tenant_id', $tenantId);
                        }
                        return $query->orderBy('name')->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('active')
                    ->label('Active Only')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'published')
                        ->where(function ($q) {
                            $q->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        })),
            ])
            ->actions([
                Tables\Actions\Action::make('publish')
                    ->label('Publish Now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Notice $record) {
                        $record->publish();
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Notice Published')
                            ->body('The notice has been published and notifications are being sent.')
                            ->send();
                    })
                    ->visible(fn (Notice $record) => $record->status !== 'published'),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotices::route('/'),
            'create' => Pages\CreateNotice::route('/create'),
            'view' => Pages\ViewNotice::route('/{record}'),
            'edit' => Pages\EditNotice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        try {
            // Get tenant ID - prioritize tenant context from subdomain, then user's tenant_id
            $tenantId = null;
            try {
                if (function_exists('tenant') && tenant()) {
                    $tenantId = tenant()->id;
                }
            } catch (\Exception $e) {
                // tenant() might not be available
                \Log::warning('NoticeResource: tenant() call failed', ['error' => $e->getMessage()]);
            }
            
            if (!$tenantId) {
                $user = auth()->user();
                $tenantId = $user?->tenant_id;
            }
            
            $query = parent::getEloquentQuery()
                ->with(['campus', 'hostel']);

            // Filter by tenant_id if available
            // Simply try to filter - if column doesn't exist, the query will fail gracefully
            // and we'll catch it in the outer try-catch
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            return $query;
        } catch (\Exception $e) {
            \Log::error('NoticeResource: Error in getEloquentQuery', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            
            // Return safe fallback
            return parent::getEloquentQuery()->with(['campus', 'hostel']);
        }
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return Auth::check() && $user && $user->hasAnyRole(['Campus Manager', 'Super Admin']);
    }
}
