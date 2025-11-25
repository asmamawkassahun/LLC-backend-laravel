<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::with(['user', 'order', 'messages', 'assignedTo'])
            ->latest()
            ->paginate(20);

        return response()->json(SupportTicketResource::collection($tickets));
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
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->update([
            'assigned_to' => $request->assigned_to,
            'status' => \App\Enums\TicketStatus::IN_PROGRESS,
        ]);

        return response()->json(new SupportTicketResource($ticket->fresh()));
    }

    public function reply(Request $request, $id): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'is_internal' => 'nullable|boolean',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->messages()->create([
            'staff_id' => $request->user()->id,
            'message' => $request->message,
            'is_internal' => $request->is_internal ?? false,
        ]);

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
