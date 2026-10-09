<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Resources\BusinessResource;
use App\Models\AuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(AuditLog::query()->with('user'), $request, ['action', 'table_name'], [], 'created_at');
    }

    public function show(AuditLog $auditLog): BusinessResource
    {
        return new BusinessResource($auditLog->load('user'));
    }
}
