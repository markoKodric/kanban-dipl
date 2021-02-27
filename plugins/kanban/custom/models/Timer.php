<?php namespace Kanban\Custom\Models;

use Auth;
use Model;
use RainLab\User\Models\User;
use October\Rain\Database\Traits\Validation;

class Timer extends Model
{
    use Validation;

    public $table = 'kanban_custom_timers';

    public $timestamps = false;

    public $dates = ['created_at', 'updated_at'];

    public $fillable = ['user_id', 'timeable_id', 'timeable_type', 'created_at', 'updated_at'];

    public $rules = [];

    public $with = ['user'];

    public $morphTo = [
        'timeable' => []
    ];

    public $belongsTo = [
        'user' => User::class,
    ];

    public function getTimeInSecondsAttribute()
    {
        if ($this->updated_at) {
            return $this->updated_at->diffInSeconds($this->created_at);
        }

        return now()->diffInSeconds($this->created_at);
    }

    public function scopeMine($query)
    {
        return $query->where('user_id', Auth::getUser()->id);
    }

    public function scopeRunning($query)
    {
        return $query->whereNull('updated_at');
    }
}