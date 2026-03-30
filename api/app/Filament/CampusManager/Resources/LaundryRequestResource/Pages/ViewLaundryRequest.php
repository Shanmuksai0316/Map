<?php

namespace App\Filament\CampusManager\Resources\LaundryRequestResource\Pages;

use App\Filament\CampusManager\Resources\LaundryRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewLaundryRequest extends ViewRecord
{
    protected static string $resource = LaundryRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Request Information')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Request ID'),
                        Infolists\Components\TextEntry::make('student.user.name')
                            ->label('Student'),
                        Infolists\Components\TextEntry::make('hostel.name')
                            ->label('Hostel'),
                        Infolists\Components\TextEntry::make('service_type')
                            ->label('Service Type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match($state) {
                                'wash_iron' => 'Wash & Iron',
                                'dry_clean' => 'Dry Clean',
                                'iron_only' => 'Iron Only',
                                default => ucwords(str_replace('_', ' ', $state)),
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'requested' => 'warning',
                                'processing' => 'primary',
                                'ready' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('bag_count')
                            ->label('Number of Bags'),
                        Infolists\Components\TextEntry::make('weight_kg')
                            ->label('Weight')
                            ->suffix(' kg'),
                        Infolists\Components\TextEntry::make('special_instructions')
                            ->label('Special Instructions')
                            ->columnSpanFull()
                            ->default('None'),
                    ]),

                Infolists\Components\Section::make('Timeline')
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('requested_at')
                            ->label('Requested')
                            ->dateTime('d M Y H:i'),
                        Infolists\Components\TextEntry::make('estimated_completion_at')
                            ->label('Est. Completion')
                            ->dateTime('d M Y H:i')
                            ->default('Not set'),
                        Infolists\Components\TextEntry::make('ready_at')
                            ->label('Ready At')
                            ->dateTime('d M Y H:i')
                            ->visible(fn ($record) => $record->ready_at !== null),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Completed')
                            ->dateTime('d M Y H:i')
                            ->visible(fn ($record) => $record->completed_at !== null),
                    ]),

                Infolists\Components\Section::make('Processing Notes')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('collection_notes')
                            ->label('Collection Notes')
                            ->default('None'),
                        Infolists\Components\TextEntry::make('delivery_notes')
                            ->label('Delivery Notes')
                            ->default('None'),
                        Infolists\Components\TextEntry::make('manual_verify_notes')
                            ->label('Manual Verification Notes')
                            ->columnSpanFull()
                            ->default('None')
                            ->visible(fn ($record) => !empty($record->manual_verify_notes)),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Payment Information')
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_status')
                            ->label('Payment Status')
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'pending' => 'warning',
                                'paid' => 'success',
                                'failed' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('payment_amount')
                            ->label('Amount')
                            ->money('INR'),
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->default('N/A'),
                        Infolists\Components\TextEntry::make('payment_reference')
                            ->label('Payment Reference')
                            ->default('N/A'),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('System Information')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('d M Y H:i'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('d M Y H:i'),
                    ]),
            ]);
    }
}

