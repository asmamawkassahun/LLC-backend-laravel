<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageData;
    public $ticketId;
    public $ticketUserId;
    public $assignedAdminId;

    public function __construct($messageData, $ticketId, $ticketUserId, $assignedAdminId = null)
    {
        $this->messageData = $messageData;
        $this->ticketId = $ticketId;
        $this->ticketUserId = $ticketUserId;
        $this->assignedAdminId = $assignedAdminId;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('user.' . $this->ticketUserId),
        ];
        
        // Broadcast to assigned admin if exists
        if ($this->assignedAdminId) {
            $channels[] = new PrivateChannel('admin.' . $this->assignedAdminId);
        }
        
        // Also broadcast to a general admin channel so all admins can receive user replies
        // This ensures any admin viewing the ticket gets real-time updates
        // Using admin-support instead of admin.support to avoid dot matching issues
        $channels[] = new PrivateChannel('admin-support');
        
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ticket.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->messageData,
            'ticket_id' => $this->ticketId,
        ];
    }
}

