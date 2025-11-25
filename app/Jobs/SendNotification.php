<?php

namespace App\Jobs;

use App\Mail\OrderStatusUpdate;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNotification implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public Notification $notification
    ) {}

    public function handle(): void
    {
        $user = $this->notification->user;
        
        if ($this->notification->type === 'order_status') {
            Mail::to($user->email)->send(new OrderStatusUpdate($this->notification));
        }
    }
}
