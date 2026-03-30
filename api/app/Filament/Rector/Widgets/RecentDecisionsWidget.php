<?php

namespace App\Filament\Rector\Widgets;

use App\Models\Domain\OutPass\OutPass;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecentDecisionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Your Recent Decisions';

    protected int|string|array $columnSpan = [
        'md' => 1,
    ];

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $tenantId = $user?->tenant_id;

        return $table
            ->query(
                OutPass::query()
                    ->with(['student.user'])
                    ->where('tenant_id', $tenantId)
                    ->where('decision_by', $user->id)
                    ->whereIn('status', ['approved', 'declined'])
                    ->orderByDesc('decided_at')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->size('sm'),
                BadgeColumn::make('status')
                    ->label('Decision')
                    ->colors([
                        'success' => 'approved',
                        'danger' => 'declined',
                    ])
                    ->formatStateUsing(function ($state): string {
                        $status = $state instanceof \BackedEnum
                            ? (string) $state->value
                            : (string) $state;

                        return ucfirst(str_replace('_', ' ', $status));
                    }),
                TextColumn::make('decided_at')
                    ->label('When')
                    ->since()
                    ->size('sm'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No Decisions Yet')
            ->emptyStateDescription('Your approval history will appear here.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}

