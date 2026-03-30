<?php

namespace App\Http\Controllers\Api\V1\Imports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\RoomAllotmentImportDryRunRequest;
use App\Http\Resources\ImportJobResource;
use App\Models\ImportJob;
use App\Services\Imports\RoomAllotmentImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RoomAllotmentImportController extends Controller
{
    public function __construct(private readonly RoomAllotmentImportService $service) {}

    public function dryRun(RoomAllotmentImportDryRunRequest $request): JsonResponse
    {
        $this->authorize('create', [ImportJob::class, 'room_allotments']);

        $job = $this->service->dryRun($request->validated());

        return ImportJobResource::make($job)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function commit(ImportJob $job): JsonResponse
    {
        $this->authorize('update', $job);

        $this->service->commit($job);

        return response()->json(status: Response::HTTP_ACCEPTED);
    }
}
