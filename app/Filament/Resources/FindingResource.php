<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FindingResource\Pages;
use App\Models\Finding;
use App\Services\AiServiceInterface;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FindingResource extends Resource
{
    protected static ?string $model = Finding::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Internal Audit Operations';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Deficiency Identification')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('audit_id')
                            ->relationship('audit', 'title')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('finding_number')
                            ->required()
                            ->default(fn () => 'FND-'.date('Y').'-'.rand(100, 999)),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\Select::make('severity')
                            ->options([
                                'critical' => 'Critical (Immediate Hazard/Grounding)',
                                'major' => 'Major (Systemic Non-Conformance)',
                                'minor' => 'Minor (Procedural Discrepancy)',
                                'observation' => 'Observation (Improvement Opportunity)',
                            ])
                            ->default('major')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'cap_submitted' => 'CAP Submitted',
                                'cap_approved' => 'CAP Approved',
                                'verified_closed' => 'Verified & Closed',
                            ])
                            ->default('open')
                            ->required(),
                        Forms\Components\DatePicker::make('due_date')
                            ->default(now()->addDays(30)),
                    ]),

                Forms\Components\Section::make('Audit Observation Elements (Condition, Criteria, Cause, Effect)')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('condition')
                            ->label('Condition (What was found?)')
                            ->required()
                            ->rows(3),
                        Forms\Components\Textarea::make('criteria')
                            ->label('Criteria (Regulatory Standard Violated)')
                            ->required()
                            ->placeholder('e.g. Nig.CARs Part 8.4, ICAO Annex 6 SMS')
                            ->rows(3),
                        Forms\Components\Textarea::make('cause')
                            ->label('Root Cause')
                            ->rows(2),
                        Forms\Components\Textarea::make('effect')
                            ->label('Safety / Compliance Effect')
                            ->rows(2),
                        Forms\Components\Textarea::make('recommendation')
                            ->columnSpanFull()
                            ->rows(2),
                        Forms\Components\KeyValue::make('ai_analysis')
                            ->label('AI Root Cause & Safety Risk Analysis')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('finding_number')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight('bold')
                    ->limit(35),
                Tables\Columns\TextColumn::make('audit.reference_number')
                    ->label('Audit')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'major' => 'warning',
                        'minor' => 'info',
                        'observation' => 'gray',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'cap_submitted' => 'warning',
                        'cap_approved' => 'info',
                        'verified_closed' => 'success',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('analyzeAi')
                    ->label('AI Analysis')
                    ->icon('heroicon-m-sparkles')
                    ->color('warning')
                    ->action(function (Finding $record, AiServiceInterface $ai) {
                        $analysis = $ai->analyzeFinding(
                            $record->title,
                            $record->condition,
                            $record->criteria
                        );

                        $record->update([
                            'ai_analysis' => $analysis,
                            'cause' => $record->cause ?: ($analysis['root_cause_analysis'] ?? null),
                            'effect' => $record->effect ?: ($analysis['safety_impact'] ?? null),
                        ]);

                        Notification::make()
                            ->title('AI Analysis Updated')
                            ->body('Root cause & safety impact populated.')
                            ->success()
                            ->send();
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
            'index' => Pages\ListFindings::route('/'),
            'create' => Pages\CreateFinding::route('/create'),
            'edit' => Pages\EditFinding::route('/{record}/edit'),
        ];
    }
}
