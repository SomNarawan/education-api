<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    protected $table = 'students';

    protected $fillable = [
        'student_code',
        'title_id',
        'first_name_th',
        'last_name_th',
        'first_name_en',
        'last_name_en',
        'phone',
        'email',
        'teacher_id',
        'student_status_id',
        'admission_channel_id',
        'high_school_id',
        'affiliation_id',
        'study_plan_id',
        'department_id',
        'faculty_id',
        'campus_id',
        'entry_year',
        'gpa',
        'earned_credits',
        'required_credits',
        'deleted_at',
        'is_deleted',
    ];

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function studentStatus(): BelongsTo
    {
        return $this->belongsTo(StudentStatus::class, 'student_status_id');
    }

    public function admissionChannel(): BelongsTo
    {
        return $this->belongsTo(AdmissionChannel::class, 'admission_channel_id');
    }

    public function highSchool(): BelongsTo
    {
        return $this->belongsTo(HighSchool::class, 'high_school_id');
    }

    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class, 'affiliation_id');
    }

    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlanTrack::class, 'study_plan_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }
}