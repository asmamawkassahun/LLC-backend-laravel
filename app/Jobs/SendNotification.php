<?php

namespace App\Jobs;

use App\Mail\MarketplaceFileUploaded;
use App\Mail\MarketplaceOrderCreated;
use App\Mail\OrderCreated;
use App\Mail\OrderStatusUpdate;
use App\Mail\PaymentSuccessful;
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
        } elseif ($this->notification->type === 'order_created') {
            Mail::to($user->email)->send(new OrderCreated($this->notification));
        } elseif ($this->notification->type === 'order_paid') {
            Mail::to($user->email)->send(new PaymentSuccessful($this->notification));
        } elseif ($this->notification->type === 'marketplace_order_created') {
            Mail::to($user->email)->send(new MarketplaceOrderCreated($this->notification));
        } elseif ($this->notification->type === 'marketplace_file_uploaded') {
            Mail::to($user->email)->send(new MarketplaceFileUploaded($this->notification));
        }
    }
}
