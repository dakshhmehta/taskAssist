<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',

        'work_hours',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tasks(){
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function timesheet(){
        return $this->hasMany(Timesheet::class);
    }

    public function setPasswordAttribute($value){
        $this->attributes['password'] = Hash::make($value);
    }

    public function getUtilizationAttribute(){
        $tasks = $this->tasks()
            ->where('assignee_id', $this->id)
            ->whereNotNull('estimate')
            ->where('due_date', '<=', now()->endOfDay())
            ->get();

        $efficiencies = [];
        foreach($tasks as $task){
            $minutes = $task->minutes_taken;
            if($minutes > 0){
                \Log::debug([$task->title.' ', $minutes.' - '.$task->estimate]);
                $efficiencies[] = (($minutes * 100) / $task->estimate);
            }
        }

        if(count($efficiencies) == 0){
            return 0;
        }

        \Log::debug([count($efficiencies)]);
        return sprintf("%.2f", array_sum($efficiencies) / count($efficiencies));
    }

    public function getIsAdminAttribute(){
        return $this->id == 1;
    }
}
