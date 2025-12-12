<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Filter out internal messages for regular users (not admins)
        $user = $request->user();
        $isAdmin = $user && ($user instanceof \App\Models\Admin);
        
        $messages = null;
        if ($this->relationLoaded('messages')) {
            $messages = $this->messages;
            // Filter out internal messages for non-admin users
            if (!$isAdmin) {
                $messages = $messages->filter(function ($message) {
                    return !$message->is_internal;
                });
            }
        }

        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'message' => $this->message,
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'user' => new UserResource($this->whenLoaded('user')),
            'order' => new OrderResource($this->whenLoaded('order')),
            'messages' => $messages ? SupportMessageResource::collection($messages) : $this->whenLoaded('messages'),
            'assigned_to' => $this->whenLoaded('assignedTo') ? new \App\Http\Resources\AdminResource($this->assignedTo) : null,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
