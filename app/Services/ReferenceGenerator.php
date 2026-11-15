<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Finding;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class ReferenceGenerator
{
    /**
     * Generate an NCR reference based on dynamic setting format (default: NCR-{YYYY}-{####}).
     */
    public static function generateNcrReference(?int $sequenceNumber = null): string
    {
        $format = (string) setting('audit.reference_format.ncr', 'NCR-{YYYY}-{####}');
        $seq = $sequenceNumber ?? (Finding::count() + 1);

        return self::renderFormat($format, [
            'YYYY' => date('Y'),
            'YY' => date('y'),
            'MM' => date('m'),
            '####' => str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            '###' => str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            '##' => str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Generate a Certificate reference based on dynamic setting format (default: DSD-QA-{YYYY}-{####}).
     */
    public static function generateCertificateReference(Audit $audit): string
    {
        $format = (string) setting('audit.reference_format.certificate', 'DSD-QA-{YYYY}-{####}');
        $seq = $audit->id;

        return self::renderFormat($format, [
            'YYYY' => date('Y'),
            'YY' => date('y'),
            'MM' => date('m'),
            'CODE' => $audit->directorate?->code ?? 'DSD',
            '####' => str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            '###' => str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            '##' => str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Generate an Audit reference based on dynamic setting format (default: AUD-{YYYY}-{CODE}-{###}).
     */
    public static function generateAuditReference(?string $directorateCode = null, ?int $seq = null): string
    {
        $format = (string) setting('audit.reference_format.audit', 'AUD-{YYYY}-{CODE}-{###}');
        $seq = $seq ?? (Audit::count() + 1);
        $code = $directorateCode ?: 'DSD';

        return self::renderFormat($format, [
            'YYYY' => date('Y'),
            'YY' => date('y'),
            'MM' => date('m'),
            'CODE' => $code,
            '####' => str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            '###' => str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            '##' => str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
            'RAND' => strtoupper(Str::random(3)),
        ]);
    }

    /**
     * Compute CAP submission deadline based on Finding Level settings.
     */
    public static function getCapSubmissionDeadline(int|string $level, ?CarbonInterface $startDate = null): CarbonInterface
    {
        $levels = setting('audit.finding_levels', []);
        $startDate = $startDate ?? now();

        $days = 14; // Default fallback
        foreach ($levels as $l) {
            if ((int) ($l['level'] ?? 0) === (int) $level) {
                $days = (int) ($l['cap_submission_days'] ?? 14);
                break;
            }
        }

        return $startDate->addDays($days);
    }

    /**
     * Compute CAP closure verification deadline based on Finding Level settings.
     */
    public static function getCapClosureDeadline(int|string $level, ?CarbonInterface $startDate = null): CarbonInterface
    {
        $levels = setting('audit.finding_levels', []);
        $startDate = $startDate ?? now();

        $days = 30; // Default fallback
        foreach ($levels as $l) {
            if ((int) ($l['level'] ?? 0) === (int) $level) {
                $days = (int) ($l['closure_days'] ?? 30);
                break;
            }
        }

        return $startDate->addDays($days);
    }

    private static function renderFormat(string $format, array $replacements): string
    {
        $output = $format;
        foreach ($replacements as $search => $replace) {
            $output = str_replace('{'.$search.'}', (string) $replace, $output);
        }

        return $output;
    }
}
