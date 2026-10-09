<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Resources\BusinessResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

abstract class Controller
{
    protected function filtered(Builder $query, ListRequest $request, array $searchable = [], array $filters = [], ?string $dateColumn = null): Builder
    {
        if ($request->filled('q') && $searchable !== []) {
            $query->where(function (Builder $search) use ($request, $searchable): void {
                foreach ($searchable as $column) {
                    $search->orWhereLike($column, '%'.$request->input('q').'%');
                }
            });
        }
        foreach ($filters as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->input($column));
            }
        }
        if ($dateColumn !== null) {
            if ($request->filled('from')) {
                $query->whereDate($dateColumn, '>=', $request->input('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate($dateColumn, '<=', $request->input('to'));
            }
        }

        return $query;
    }

    protected function listing(Builder $query, ListRequest $request, array $searchable = [], array $filters = [], ?string $dateColumn = null): AnonymousResourceCollection
    {
        $this->filtered($query, $request, $searchable, $filters, $dateColumn);

        return BusinessResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 15))->withQueryString());
    }
}
