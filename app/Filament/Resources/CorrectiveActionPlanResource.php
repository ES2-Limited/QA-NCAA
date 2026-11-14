<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CorrectiveActionPlanResource\Pages;
use App\Models\CorrectiveActionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CorrectiveActionPlanResource extends Resource
{
    protected static ?string $model = CorrectiveActionPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Internal Audit Operations';

    protected static ?string $modelLabel = 'Corrective Action Plan (CAP)';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Remediation Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('finding_id')
                            ->relationship('finding', 'title')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('responsible_person')
                            ->required()
                            ->placeholder('e.g. Head of Airworthiness / Station Lead'),
                        Forms\Components\DatePicker::make('target_completion_date')
                            ->required()
                            ->default(now()->addDays(21)),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending_approval' => 'Pending DSD Approval',
                                'approved' => 'Approved (In Execution)',
                                'rejected' => 'Rejected (Revision Needed)',
                                'implemented' => 'Implemented (Ready for Verification)',
                                'verified_closed' => 'Verified & Closed by DSD',
                            ])
                            ->default('pending_approval')
                            ->required(),
                        Forms\Components\Textarea::make('root_cause_analysis')
                            ->required()
                            ->columnSpanFull()
                            ->rows(2),
                        Forms\Components\Textarea::make('proposed_action')
                            ->required()
                            ->columnSpanFull()
                            ->rows(3),
                    ]),

                Forms\Components\Section::make('DSD QA Verification & Closure')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('verification_notes')
                            ->columnSpanFull()
                            ->rows(2)
                            ->placeholder('Auditor notes upon physical or documentary re-inspection.'),
                        Forms\Components\Select::make('verified_by')
                            ->relationship('verifier', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\DateTimePicker::make('verified_at'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('finding.finding_number')
                    ->label('Finding #')
                    ->badge()
                    ->color('danger')
                    ->searchable(),
                Tables\Columns\TextColumn::make('finding.title')
                    ->label('Deficiency')
                    ->weight('bold')
                    ->limit(35),
                Tables\Columns\TextColumn::make('responsible_person')
                    ->searchable(),
                Tables\Columns\TextColumn::make('target_completion_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending_approval' => 'warning',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'implemented' => 'primary',
                        'verified_closed' => 'success',
                        default => 'secondary',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve CAP')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn (CorrectiveActionPlan $record) => $record->status === 'pending_approval')
                    ->action(fn (CorrectiveActionPlan $record) => $record->update(['status' => 'approved'])),

                Tables\Actions\Action::make('verifyClose')
                    ->label('Verify & Close')
                    ->icon('heroicon-m-lock-closed')
                    ->color('primary')
                    ->visible(fn (CorrectiveActionPlan $record) => in_array($record->status, ['approved', 'implemented']))
                    ->action(function (CorrectiveActionPlan $record) {
                        $record->update([
                            'status' => 'verified_closed',
                            'verified_at' => now(),
                            'verified_by' => auth()->id() ?? 1,
                        ]);
                        $record->finding()->update(['status' => 'verified_closed']);
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCorrectiveActionPlans::route('/'),
            'create' => Pages\CreateCorrectiveActionPlan::route('/create'),
            'edit' => Pages\EditCorrectiveActionPlan::route('/{record}/edit'),
        ];
    }
}
