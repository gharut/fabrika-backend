<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationParticipant extends Model
{
    protected $fillable = [
        'organization_id',
        'model_type',
        'model_id',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'organization_id');
    }

    public function model()
    {
        return $this->morphTo(null, 'model_type', 'model_id');
    }

    // public function user()
    // {
    //     return $this->morphTo(null, 'model_type', 'model_id');
    // }

    public function user()
    {
        return $this->belongsTo(User::class, 'model_id');
    }
    
    public function organizationModel()
    {
        return $this->morphTo(null, 'model_type', 'model_id');
    }
}
