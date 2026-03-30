<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\StudentViewResource\Pages;
use App\Models\Student;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class StudentViewResource extends Resource
{
    /**
     * Use Student model directly instead of StudentView for single DB architecture
     */
    protected static ?string $model = \App\Models\Student::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    
    protected static ?string $navigationLabel = 'Students';
    
    protected static ?string $pluralLabel = 'All Students';
    
    protected static ?string $navigationGroup = 'Operations';
    
    protected static ?int $navigationSort = 3;
    
    protected static ?string $slug = 'students';
    
    public static function canAccess(): bool
    {
        // ENABLED: With single shared database architecture, students have tenant_id
        // and can be queried directly from the central database
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('map_student_id')
                    ->label('MAP ID')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('roll_no')
                    ->label('ERP Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.gender')
                    ->label('Gender')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'N/A'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([])
            ->defaultSort('created_at', 'desc');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Student Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('tenant.name')
                            ->label('Tenant'),
                        Infolists\Components\TextEntry::make('map_student_id')
                            ->label('Student ID'),
                        Infolists\Components\TextEntry::make('roll_no')
                            ->label('Roll Number'),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Email'),
                        Infolists\Components\TextEntry::make('user.phone')
                            ->label('Phone'),
                        Infolists\Components\TextEntry::make('hostel.name')
                            ->label('Hostel'),
                        Infolists\Components\TextEntry::make('correspondence_address')
                            ->label('Address')
                            ->state(function (Student $record) {
                                $address = $record->correspondence_address;
                                if (!is_array($address)) {
                                    return $address ?: '—';
                                }

                                return collect([
                                    $address['line1'] ?? null,
                                    $address['line2'] ?? null,
                                    $address['city'] ?? null,
                                    $address['state'] ?? null,
                                    $address['postal_code'] ?? null,
                                ])->filter()->implode(', ') ?: '—';
                            }),
                    ]),
                Infolists\Components\Section::make('Academic Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('program')
                            ->label('Program'),
                        Infolists\Components\TextEntry::make('year_of_study')
                            ->label('Year of Study'),
                        Infolists\Components\TextEntry::make('admission_year')
                            ->label('Admission Year'),
                    ]),
                Infolists\Components\Section::make('Parent & Guardian Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('father_name')
                            ->label('Father Name')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('father_mobile_number')
                            ->label('Father Contact')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('mother_name')
                            ->label('Mother Name')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('mother_mobile_number')
                            ->label('Mother Contact')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('local_guardian_name')
                            ->label('Local Guardian')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('local_guardian_contact')
                            ->label('Local Guardian Contact')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('local_address')
                            ->label('Local Guardian Address')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('blood_group')
                            ->label('Blood Group')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('user.id_number')
                            ->label('Aadhaar / ID Number')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentViews::route('/'),
            'view' => Pages\ViewStudentView::route('/{record}'),
        ];
    }
    
    // Read-only - no create/edit/delete
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function canEdit($record): bool
    {
        return false;
    }
    
    public static function canDelete($record): bool
    {
        return false;
    }
}
