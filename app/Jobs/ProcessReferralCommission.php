<?php

namespace App\Jobs;

use App\Models\Affiliate;
use App\Models\Order;
use App\Services\ReferralService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessReferralCommission implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public Order $order,
        public Affiliate $affiliate
    ) {}

    public function handle(ReferralService $service): void
    {
        $service->processCommission($this->order, $this->affiliate);
    }
}
