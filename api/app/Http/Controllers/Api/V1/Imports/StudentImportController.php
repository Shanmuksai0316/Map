<?php

namespace App\Http\Controllers\Api\V1\Imports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\StudentImportDryRunRequest;
use App\Http\Resources\ImportJobResource;
use App\Models\ImportJob;
use App\Services\Imports\StudentImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StudentImportController extends Controller
{
    public function __construct(private readonly StudentImportService $service) {}

    public function dryRun(StudentImportDryRunRequest $request): JsonResponse
    {
        $job = $this->service->dryRun($request->validated());

        return ImportJobResource::make($job)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function commit(ImportJob $job): JsonResponse
    {
        $this->service->commit($job);

        return response()->json(status: Response::HTTP_ACCEPTED);
    }
}
