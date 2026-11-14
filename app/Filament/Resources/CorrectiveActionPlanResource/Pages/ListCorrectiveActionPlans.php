<?php

namespace App\Filament\Resources\CorrectiveActionPlanResource\Pages;

use App\Filament\Resources\CorrectiveActionPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCorrectiveActionPlans extends ListRecords
{
    protected static string $resource = CorrectiveActionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
