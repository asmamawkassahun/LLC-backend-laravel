<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use App\Http\Resources\SupportMessageResource;
use App\Models\SupportTicket;
use App\Models\Admin;
use App\Events\SupportTicketMessageSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AdminSupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::with(['user', 'order', 'messages.user', 'messages.staff', 'assignedTo'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => SupportTicketResource::collection($tickets->items()),
            'current_page' => $tickets->currentPage(),
            'last_page' => $tickets->lastPage(),
            'per_page' => $tickets->perPage(),
            'total' => $tickets->total(),
        ]);
    }

    public function show($id): JsonResponse
    {
        $ticket = SupportTicket::with(['user', 'order', 'messages.user', 'messages.staff', 'assignedTo'])
            ->findOrFail($id);

        return response()->json(new SupportTicketResource($ticket));
    }

    public function assign(Request $request, $id): JsonResponse
    {
        $request->validate([
            'assigned_to' => 'required|exists:admins,id',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->update([
            'assigned_to' => $request->assigned_to,
            'status' => \App\Enums\TicketStatus::IN_PROGRESS,
        ]);

        return response()->json(new SupportTicketResource($ticket->fresh()));
    }

    public function getAdmins(): JsonResponse
    {
        $admins = Admin::where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $admins->map(function ($admin) {
                return [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ];
            })
        ]);
    }

    public function reply(Request $request, $id): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'file|max:10240', // 10MB max per file
            'is_internal' => 'nullable|boolean',
        ]);

        $ticket = SupportTicket::findOrFail($id);

        $hasMessage = $request->filled('message') && trim($request->input('message')) !== '';
        $hasFiles = $request->hasFile('files') && count($request->file('files')) > 0;

        if (!$hasMessage && !$hasFiles) {
            return response()->json([
                'message' => 'Either a message or files must be provided'
            ], 422);
        }

        $attachments = [];
        
        // Handle file uploads
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                if ($file->isValid()) {
                    try {
                        $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs('support-tickets/' . $ticket->id, $fileName, 'minio');
                        $fileUrl = Storage::disk('minio')->url($filePath);
                        
                        $attachments[] = [
                            'file_path' => $filePath,
                            'file_name' => $file->getClientOriginalName(),
                            'file_url' => $fileUrl,
                            'file_size' => $file->getSize(),
                            'uploaded_at' => now()->toISOString(),
                        ];
                    } catch (\Exception $e) {
                        Log::error('Support ticket file upload error (admin)', [
                            'ticket_id' => $ticket->id,
                            'file_name' => $file->getClientOriginalName(),
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with other files even if one fails
                    }
                }
            }
        }

        $message = $ticket->messages()->create([
            'staff_id' => $request->user()->id,
            'message' => $request->message ?? '',
            'attachments' => $attachments,
            'is_internal' => $request->is_internal ?? false,
        ]);

        // Load relationships for the message
        $message->load('user', 'staff');

        // Broadcast the message to ticket owner and assigned admin
        event(new SupportTicketMessageSent(
            new SupportMessageResource($message),
            $ticket->id,
            $ticket->user_id,
            $ticket->assigned_to
        ));

        return response()->json(['message' => 'Reply sent successfully'], 201);
    }

    public function resolve($id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $ticket->update([
            'status' => \App\Enums\TicketStatus::RESOLVED,
            'resolved_at' => now(),
        ]);

        return response()->json(new SupportTicketResource($ticket->fresh()));
    }
}
