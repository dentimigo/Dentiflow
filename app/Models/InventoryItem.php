<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    public const STOCK_LOW_MAX = 2;

    public const STOCK_RUNNING_OUT_MAX = 5;

    public const STOCK_AVAILABLE_MAX = 10;

    protected $fillable = [
        'material',
        'stock',
        'reposicion',
        'costo',
        'proveedor',
    ];

    protected $casts = [
        'stock' => 'integer',
        'reposicion' => 'integer',
        'costo' => 'decimal:2',
    ];

    public function needsRestock(): bool
    {
        return $this->stock <= self::STOCK_RUNNING_OUT_MAX;
    }

    public function stockStatus(): string
    {
        return match (true) {
            $this->stock <= self::STOCK_LOW_MAX => 'low',
            $this->stock <= self::STOCK_RUNNING_OUT_MAX => 'running_out',
            $this->stock <= self::STOCK_AVAILABLE_MAX => 'available',
            default => 'high',
        };
    }

    public function stockStatusLabel(): string
    {
        return match ($this->stockStatus()) {
            'low' => 'Stock bajo',
            'running_out' => 'Por agotarse',
            'available' => 'Stock disponible',
            'high' => 'Stock alto',
        };
    }

    public function inventoryValue(): float
    {
        return $this->stock * (float) $this->costo;
    }
}
