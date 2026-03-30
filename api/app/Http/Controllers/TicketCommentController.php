<?php

namespace App\Http\Controllers;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketComment;
use App\Http\Requests\TicketCommentRequest;
use App\Http\Resources\TicketCommentResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TicketCommentController extends Controller
{
    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $comments = $ticket->comments()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => TicketCommentResource::collection($comments),
        ]);
    }

    public function store(TicketCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $request->body,
            'attachments' => $request->input('attachments', []),
            'is_internal' => $request->boolean('is_internal', false),
        ]);

        // Log audit
        AuditLogger::logEvent('ticket.comment_added', [
            'ticket_id' => $ticket->id,
            'tenant_id' => $ticket->tenant_id,
            'actor_user_id' => $user->id,
            'comment_id' => $comment->id,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
        ], $user);

        return response()->json([
            'data' => new TicketCommentResource($comment->load('user')),
            'message' => 'Comment added successfully',
        ], 201);
    }
}
