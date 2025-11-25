<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\CreateTicketRequest;
use App\Http\Requests\Support\ReplyTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()
            ->with(['order', 'messages'])
            ->latest()
            ->paginate(15);

        return response()->json(SupportTicketResource::collection($tickets));
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
            ->with(['order', 'messages.user', 'messages.staff', 'assignedTo'])
            ->findOrFail($id);

        return response()->json(new SupportTicketResource($ticket));
    }

    public function reply(ReplyTicketRequest $request, $id): JsonResponse
    {
        $ticket = $request->user()->supportTickets()->findOrFail($id);

        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'attachments' => $request->attachments ?? [],
            'is_internal' => $request->is_internal ?? false,
        ]);

        return response()->json(['message' => 'Reply sent successfully'], 201);
    }
}
