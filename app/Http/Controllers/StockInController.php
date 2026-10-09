<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Models\StockMovement;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockInController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        return $this->listing(StockMovement::query()->with('sparePart.category', 'user')->where('type', 'in'), $request, ['notes'], ['spare_part_id'], 'created_at');
    }
}
