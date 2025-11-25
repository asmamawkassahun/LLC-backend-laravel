<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\CompanyFormationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCompanyFormation implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public Company $company
    ) {}

    public function handle(CompanyFormationService $service): void
    {
        $service->processFormation($this->company);
    }
}
