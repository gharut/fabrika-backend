<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Concerns\BelongsToClient;
use App\Enums\MarketplaceAccountStatus;

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
        'status' => MarketplaceAccountStatus::class,
        'last_checked_at' => 'datetime',
    ];

    protected $hidden = [
        'api_token_enc',
    ];
    
    public function checkConnection(): bool
    {
        try {
            if ($this->platform === 'WB') {
                return $this->checkWildberriesConnection();
            }

            $this->update([
                'status' => 'error',
                'error_message' => 'Unsupported platform: ' . $this->platform,
                'last_checked_at' => now(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Marketplace connection check failed', [
                'account_id' => $this->id,
                'error' => $e->getMessage()
            ]);

            $this->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'last_checked_at' => now(),
            ]);

            return false;
        }
    }

    protected function checkWildberriesConnection(): bool
    {
        $response = Http::withOptions(['verify' => false])
        ->withHeaders([
            'Authorization' => $this->api_token_enc,
            'Accept' => 'application/json',
        ])
        ->timeout(10)
        ->get('https://content-api.wildberries.ru/ping');

        if ($response->successful()) {
            $data = $response->json();
            
            $isConnected = isset($data['Status']) && $data['Status'] === 'OK';
            
            $this->update([
                'status' => $isConnected ? 'connected' : 'error',
                'error_message' => $isConnected ? null : 'Invalid response from WB API',
                'last_checked_at' => now(),
            ]);

            return $isConnected;
        }

        $this->update([
            'status' => 'error',
            'error_message' => 'HTTP Error: ' . $response->status(),
            'last_checked_at' => now(),
        ]);

        return false;
    }
    
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
