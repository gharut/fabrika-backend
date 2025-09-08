<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToClient;

class MarketplaceAccount extends Model
{
    use BelongsToClient;

    protected $fillable = [
        'client_id',
        'platform',
        'name',
        'api_token_enc',
        'status',
        'error_message',
        'last_checked_at',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];

    protected $hidden = [
        'api_token_enc',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
