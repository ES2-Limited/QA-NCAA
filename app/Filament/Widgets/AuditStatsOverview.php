<?php

namespace App\Filament\Widgets;

use App\Models\Audit;
use App\Models\CorrectiveActionPlan;
use App\Models\Finding;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AuditStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalAudits = Audit::count();
        $inProgressAudits = Audit::where('status', 'in_progress')->count();
        $criticalFindings = Finding::where('severity', 'critical')->where('status', '!=', 'verified_closed')->count();
        $openFindings = Finding::where('status', '!=', 'verified_closed')->count();
        $pendingCaps = CorrectiveActionPlan::where('status', 'pending_approval')->count();

        return [
            Stat::make('Active Engagements', $inProgressAudits)
                ->description("{$totalAudits} total scheduled / completed")
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('primary'),

            Stat::make('Open Findings', $openFindings)
                ->description("{$criticalFindings} Critical severity items")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($criticalFindings > 0 ? 'danger' : 'warning'),

            Stat::make('Pending CAP Approvals', $pendingCaps)
                ->description('Corrective action plans awaiting review')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingCaps > 0 ? 'warning' : 'success'),
        ];
    }
}
