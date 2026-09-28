<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['number', 'token', 'seats', 'status', 'is_active', 'service_requested_at'])]
class Table extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'free'];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'is_active' => 'boolean',
            'service_requested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Table $table) {
            $table->token ??= Str::random(32);
        });
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    /** The currently open session (the visit in progress), if any. */
    public function openSession(): HasOne
    {
        return $this->hasOne(TableSession::class)
            ->whereIn('status', [SessionStatus::Open, SessionStatus::Billed])
            ->latestOfMany('opened_at');
    }

    /** Returns the open session, creating one if the table is free. */
    public function openOrStartSession(): TableSession
    {
        $session = $this->openSession()->first();

        if ($session) {
            return $session;
        }

        $session = $this->sessions()->create([
            'status' => SessionStatus::Open,
            'opened_at' => now(),
        ]);

        $this->update(['status' => TableStatus::Occupied]);

        return $session;
    }

    /** Absolute URL for the QR code, built on the LAN-reachable base from config. */
    public function customerUrl(): string
    {
        return rtrim(config('restaurant.customer_url'), '/').route('customer.table', $this->token, absolute: false);
    }

    public function requestService(): void
    {
        $this->update(['service_requested_at' => now()]);
    }

    public function clearServiceRequest(): void
    {
        $this->update(['service_requested_at' => null]);
    }

    public function needsService(): bool
    {
        return $this->service_requested_at !== null;
    }
}
