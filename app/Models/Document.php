<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_number',
        'title',
        'type',
        'status',
        'action',
        'author',
        'originating_office',
        'current_office',
        'designated_office',
        'file_attach',
        'drive',
        'remarks',
        'received_by',
        'released_by',
        'terminal_by',
    ];

    public function paperTrails() {
        return $this->hasMany(PaperTrail::class);
    }

    public function receivedBy() {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function releasedBy() {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function terminalBy() {
        return $this->belongsTo(User::class, 'terminal_by');
    }

    public function notifications() {
        return $this->hasMany(Notification::class);
    }

}
