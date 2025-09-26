<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToClient;

class FileOperation extends Model
{
    use HasFactory, BelongsToClient;

    protected $fillable = [
        'operation_type',
        'file_name',
        'client_id',
        'file_extension',
        'file_size',
        'user_id',
        'status',
        'error_message',
        'related_to',
        'finished_at',
    ];

    protected $dates = [
        'finished_at',
    ];

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUCCESS     = 'success';
    public const STATUS_FAILED      = 'failed';

    public function markAsFinished(string $status = self::STATUS_SUCCESS, ?string $error = null): void
    {
        $this->update([
            'status'       => $status,
            'error_message'=> $error,
            'finished_at'  => now(),
        ]);
    }
}
