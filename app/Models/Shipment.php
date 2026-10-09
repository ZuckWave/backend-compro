<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'work_order_id', 'recipient_name', 'phone', 'address', 'courier', 'tracking_number', 'status', 'user_id', 'notes'])]
class Shipment extends Model
{
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ShipmentStatusLog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
