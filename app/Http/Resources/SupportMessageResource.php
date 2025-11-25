<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'attachments' => $this->attachments,
            'is_internal' => $this->is_internal,
            'user' => new UserResource($this->whenLoaded('user')),
            'staff' => new UserResource($this->whenLoaded('staff')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
