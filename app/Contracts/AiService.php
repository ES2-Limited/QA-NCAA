<?php

namespace App\Contracts;

interface AiService
{
    /**
     * Generate realistic aviation QA checklist questions based on scope and requested quantity.
     *
     * @return array<int, array{
     *     question: string,
     *     type: string,
     *     options: array<string>|null,
     *     requirement_ref: string|null,
     *     guidance: string|null
     * }>
     */
    public function generateChecklist(string $scope, int $count = 8): array;
}
