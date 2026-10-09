<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_name', 'phone', 'plate_number', 'brand', 'type', 'year'])]
class Vehicle extends Model
{
    protected function casts(): array
    {
        return [
            'year' => 'integer',
        ];
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
