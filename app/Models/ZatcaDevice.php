<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZatcaDevice extends Model
{
    use HasFactory;

    protected $primaryKey = 'serial';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'serial',
        'private_key',
        'csr',
        'compliance_request_id',
        'csid',
        'csid_secret',
        'certificate',
        'onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted',
            'csid' => 'encrypted',
            'csid_secret' => 'encrypted',
            'onboarded_at' => 'datetime',
        ];
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null
            && filled($this->csid)
            && filled($this->certificate);
    }
}
