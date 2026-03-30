<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;

class AdminDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.admin.pages.admin-dashboard';
}
