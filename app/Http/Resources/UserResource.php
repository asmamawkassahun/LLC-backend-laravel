<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Crypt;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'profile' => new UserProfileResource($this->whenLoaded('profile')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        // Include decrypted password for admin requests
        // Check if this is an admin route by checking the route path
        $isAdminRoute = $request->is('admin/*') || str_starts_with($request->path(), 'admin/');
        
        if ($isAdminRoute && $this->password) {
            try {
                $data['password'] = Crypt::decryptString($this->password);
            } catch (\Exception $e) {
                $data['password'] = '';
            }
        }

        return $data;
    }
}
