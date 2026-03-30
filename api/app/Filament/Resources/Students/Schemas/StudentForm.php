<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Forms;
use Filament\Forms\Form;

class StudentForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                // A. Identity & Academic
                Forms\Components\Section::make('Identity & Academic')
                    ->description('Student academic information')
                    ->schema([
                        Forms\Components\TextInput::make('full_name')
                            ->label('Full Name')
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('map_id')
                            ->label('MAP ID')
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('erp_number')
                            ->label('ERP Number')
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('department')
                            ->label('Department'),

                        Forms\Components\Select::make('year_of_study')
                            ->label('Year of Study')
                            ->options([
                                '1' => 'First Year',
                                '2' => 'Second Year',
                                '3' => 'Third Year',
                                '4' => 'Fourth Year',
                                '5' => 'Fifth Year',
                            ]),

                    ])
                    ->columns(3)
                    ->columnSpan(3),

                // B. Contact
                Forms\Components\Section::make('Contact Information')
                    ->description('Student contact details')
                    ->schema([
                        Forms\Components\TextInput::make('mobile_number')
                            ->label('Mobile Number')
                            ->tel()
                            ->required(),

                        Forms\Components\TextInput::make('email_address')
                            ->label('Email Address')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->required(),

                    ])
                    ->columns(2)
                    ->columnSpan(3),

                // C. Parent Information
                Forms\Components\Section::make('Parent Information')
                    ->description('Parents contact details')
                    ->schema([
                        // Father Information
                        Forms\Components\TextInput::make('father_name')
                            ->label('Father Name'),

                        Forms\Components\TextInput::make('father_mobile_number')
                            ->label('Father Mobile Number')
                            ->tel(),

                        // Mother Information
                        Forms\Components\TextInput::make('mother_name')
                            ->label('Mother Name'),

                        Forms\Components\TextInput::make('mother_mobile_number')
                            ->label('Mother Mobile Number')
                            ->tel(),

                    ])
                    ->columns(2)
                    ->columnSpan(3),

                // D. Local Guardian
                Forms\Components\Section::make('Local Guardian Information')
                    ->description('Local guardian contact details')
                    ->schema([
                        Forms\Components\TextInput::make('local_guardian_name')
                            ->label('Local Guardian Name'),

                        Forms\Components\TextInput::make('local_guardian_contact')
                            ->label('Contact Number')
                            ->tel(),

                        Forms\Components\TextInput::make('local_relationship')
                            ->label('Relationship'),

                        Forms\Components\Textarea::make('local_address')
                            ->label('Local Address')
                            ->rows(2)
                            ->columnSpan(3),

                    ])
                    ->columns(3)
                    ->columnSpan(3),

                // E. Medical
                Forms\Components\Section::make('Medical Information')
                    ->description('Health and medical details')
                    ->schema([
                        Forms\Components\Select::make('blood_group')
                            ->label('Blood Group')
                            ->options([
                                'A+' => 'A+',
                                'A-' => 'A-',
                                'B+' => 'B+',
                                'B-' => 'B-',
                                'O+' => 'O+',
                                'O-' => 'O-',
                                'AB+' => 'AB+',
                                'AB-' => 'AB-',
                            ])
                            ->searchable(),

                        Forms\Components\Textarea::make('medical_information')
                            ->label('Medical Information')
                            ->placeholder('Enter any medical conditions, allergies, disabilities, or medications')
                            ->rows(4)
                            ->columnSpan(2),

                    ])
                    ->columns(3)
                    ->columnSpan(3),

                // F. Hostel Allocation
                Forms\Components\Section::make('Hostel Allocation')
                    ->description('Room assignment and occupancy details')
                    ->schema([
                        Forms\Components\TextInput::make('assigned_hostel')
                            ->label('Assigned Hostel'),

                        Forms\Components\DatePicker::make('check_in_date')
                            ->label('Check-in Date'),

                        Forms\Components\DatePicker::make('check_out_date')
                            ->label('Check-out Date'),

                    ])
                    ->columns(3)
                    ->columnSpan(3),

            ]);
    }
}
