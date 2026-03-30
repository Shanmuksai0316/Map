<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,
            'photos' => $this->photos,
            'sla_due_at' => $this->sla_due_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'is_within_sla' => $this->isWithinSla(),
            'is_breached' => $this->isBreached(),
            'is_delayed' => $this->isDelayed(),
            'reporter_name' => $this->reporter_name,
            'reporter_type' => $this->reporter_type,
            'hostel' => [
                'id' => $this->hostel?->id,
                'name' => $this->hostel?->name,
            ],
            'reporter_student' => $this->when($this->reporterStudent, [
                'id' => $this->reporterStudent?->id,
                'map_student_id' => $this->reporterStudent?->map_student_id,
                'roll_no' => $this->reporterStudent?->roll_no,
                'user' => [
                    'id' => $this->reporterStudent?->user?->id,
                    'name' => $this->reporterStudent?->user?->name,
                    'phone' => $this->reporterStudent?->user?->phone,
                ],
            ]),
            'reporter_user' => $this->when($this->reporterUser, [
                'id' => $this->reporterUser?->id,
                'name' => $this->reporterUser?->name,
                'phone' => $this->reporterUser?->phone,
            ]),
            'assignee_user' => $this->when($this->assigneeUser, [
                'id' => $this->assigneeUser?->id,
                'name' => $this->assigneeUser?->name,
                'phone' => $this->assigneeUser?->phone,
            ]),
            'comments' => $this->when($this->relationLoaded('comments'), function () {
                return TicketCommentResource::collection($this->comments);
            }),
            'comments_count' => $this->when($this->relationLoaded('comments'), function () {
                return $this->comments->count();
            }),
        ];
    }
}
