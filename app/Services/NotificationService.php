<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function sendOrderNotification(Order $order, string $type, array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $order->user_id,
            'type' => $type,
            'title' => $this->getOrderNotificationTitle($type, $order),
            'message' => $this->getOrderNotificationMessage($type, $order),
            'data' => array_merge(['order_id' => $order->id, 'order_number' => $order->order_number], $data),
        ]);
        
        // Dispatch job to send email notification
        // SendNotification::dispatch($notification);
        
        return $notification;
    }
    
    public function sendPaymentNotification(Order $order, string $type): Notification
    {
        return $this->sendOrderNotification($order, $type, [
            'payment_status' => $order->payment_status?->value,
            'total_amount' => $order->total_amount,
        ]);
    }
    
    public function sendCompanyFormedNotification(Order $order): Notification
    {
        return $this->sendOrderNotification($order, 'company_formed', [
            'company_id' => $order->company_id,
            'company_name' => $order->company?->name,
        ]);
    }
    
    private function getOrderNotificationTitle(string $type, Order $order): string
    {
        return match($type) {
            'order_created' => 'Order Created',
            'order_paid' => 'Payment Received',
            'order_processing' => 'Order Processing',
            'order_completed' => 'Order Completed',
            'company_formed' => 'Company Formation Complete',
            default => 'Order Update',
        };
    }
    
    private function getOrderNotificationMessage(string $type, Order $order): string
    {
        return match($type) {
            'order_created' => "Your order #{$order->order_number} has been created.",
            'order_paid' => "Payment for order #{$order->order_number} has been received.",
            'order_processing' => "Your order #{$order->order_number} is now being processed.",
            'order_completed' => "Your order #{$order->order_number} has been completed.",
            'company_formed' => "Your company formation for order #{$order->order_number} is complete.",
            default => "Your order #{$order->order_number} has been updated.",
        };
    }
}

