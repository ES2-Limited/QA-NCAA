<?php

namespace App\Filament\Resources\CorrectiveActionPlanResource\Pages;

use App\Filament\Resources\CorrectiveActionPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCorrectiveActionPlan extends EditRecord
{
    protected static string $resource = CorrectiveActionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
