<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Someone invited to a meeting, and whether they came. The role is the seat they fill: the staff member, the supervisor, the
 * HR person, or a committee member. The name is kept as it was when they were invited.
 */
class PerformanceMeetingAttendee extends Model
{
    public const ROLE_STAFF = 'staff';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLE_HR = 'hr';

    public const ROLE_COMMITTEE = 'committee';

    public const ROLE_LABELS = [
        self::ROLE_STAFF => 'Staff member',
        self::ROLE_SUPERVISOR => 'Supervisor',
        self::ROLE_HR => 'HR',
        self::ROLE_COMMITTEE => 'Committee',
    ];

    protected $table = 'performance_meeting_attendees';

    protected $fillable = ['meeting_id', 'role', 'staff_id', 'user_id', 'name', 'present'];

    protected $casts = ['present' => 'boolean'];

    public function meeting()
    {
        return $this->belongsTo(PerformanceMeeting::class, 'meeting_id');
    }
}
