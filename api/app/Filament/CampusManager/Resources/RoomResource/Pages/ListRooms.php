<?php

namespace App\Filament\CampusManager\Resources\RoomResource\Pages;

use App\Filament\CampusManager\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListRooms extends ListRecords
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        $tenant = Auth::user()?->tenant;
        $canCreate = !$tenant || $tenant->status !== \App\Enums\TenantStatus::ACTIVE;
        
        return [
            Actions\CreateAction::make()
                ->visible($canCreate)
                ->disabled(!$canCreate),
        ];
    }

    public function table(Table $table): Table
    {
        $tenant = Auth::user()?->tenant;
        $canCreate = !$tenant || $tenant->status !== \App\Enums\TenantStatus::ACTIVE;
        
        return parent::table($table)
            ->query(fn (): Builder => RoomResource::getEloquentQuery())
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible($canCreate)
                    ->disabled(!$canCreate),
            ])
            ->recordUrl(null);
    }
}









