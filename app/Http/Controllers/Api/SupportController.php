<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\CreateTicketRequest;
use App\Http\Requests\Support\ReplyTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Http\Resources\SupportMessageResource;
use App\Models\SupportTicket;
use App\Events\SupportTicketMessageSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()
            ->with(['user', 'order', 'messages.user', 'messages.staff', 'assignedTo'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => SupportTicketResource::collection($tickets->items()),
            'current_page' => $tickets->currentPage(),
            'last_page' => $tickets->lastPage(),
            'per_page' => $tickets->perPage(),
            'total' => $tickets->total(),
        ]);
    }

    public function store(CreateTicketRequest $request): JsonResponse
    {
        $ticket = $request->user()->supportTickets()->create([
            'ticket_number' => 'TKT-' . strtoupper(Str::random(10)),
            'subject' => $request->subject,
            'message' => $request->message,
            'order_id' => $request->order_id,
            'priority' => $request->priority ?? 'medium',
        ]);

        return response()->json(new SupportTicketResource($ticket), 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $ticket = $request->user()->supportTickets()
            ->with(['user', 'order', 'messages.user', 'messages.staff', 'assignedTo'])
            ->findOrFail($id);

        return response()->json(new SupportTicketResource($ticket));
    }

    public function reply(ReplyTicketRequest $request, $id): JsonResponse
    {
        $ticket = $request->user()->supportTickets()->findOrFail($id);

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
                        Log::error('Support ticket file upload error', [
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
            'user_id' => $request->user()->id,
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
}
