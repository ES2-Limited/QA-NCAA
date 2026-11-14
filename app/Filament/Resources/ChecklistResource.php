<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChecklistResource\Pages;
use App\Models\Checklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChecklistResource extends Resource
{
    protected static ?string $model = Checklist::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Audit Standards';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Ground Operations & Ramp Safety Protocol'),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('e.g. CHK-GRD-2026'),
                Forms\Components\Select::make('category')
                    ->options([
                        'flight_safety' => 'Flight Operations Safety',
                        'airworthiness' => 'Continuous Airworthiness & Maintenance',
                        'security' => 'Aviation Security (AVSEC)',
                        'ground_ops' => 'Ground & Ramp Handling',
                        'aerodrome' => 'Aerodromes & Airspace Safety',
                        'records' => 'Quality Records & Certifications',
                    ])
                    ->required()
                    ->default('flight_safety'),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Forms\Components\TextInput::make('item_code')
                            ->required()
                            ->placeholder('e.g. DSD-OPS-01')
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('regulatory_reference')
                            ->placeholder('e.g. Nig.CARs Part 8.4.1.2')
                            ->columnSpan(4),
                        Forms\Components\Textarea::make('requirement')
                            ->required()
                            ->columnSpan(6)
                            ->rows(2)
                            ->placeholder('Detailed standard requirement or verification checklist item'),
                        Forms\Components\TextInput::make('guidance')
                            ->placeholder('Auditor notes or compliance indicators')
                            ->columnSpan(6),
                    ])
                    ->columns(6)
                    ->columnSpanFull()
                    ->defaultItems(1)
                    ->orderColumn('order_index'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('secondary')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'flight_safety' => 'Flight Safety',
                        'airworthiness' => 'Airworthiness',
                        'security' => 'AVSEC',
                        'ground_ops' => 'Ground Ops',
                        'aerodrome' => 'Aerodrome',
                        'records' => 'Records',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items Count')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->actions([
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
            'index' => Pages\ListChecklists::route('/'),
            'create' => Pages\CreateChecklist::route('/create'),
            'edit' => Pages\EditChecklist::route('/{record}/edit'),
        ];
    }
}
