<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_opname_id', 'spare_part_id', 'system_stock', 'physical_stock', 'difference', 'notes'])]
class StockOpnameItem extends Model
{
    protected function casts(): array
    {
        return [
            'system_stock' => 'integer',
            'physical_stock' => 'integer',
            'difference' => 'integer',
        ];
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }
}
