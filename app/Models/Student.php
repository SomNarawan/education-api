<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use SoftDeletes;

    protected $table = 'students';
    
    protected $fillable = [
        'student_code',
        'student_id_card',
        'title_id',
        'first_name_th',
        'last_name_th',
        'first_name_en',
        'last_name_en',
        'title_id',
        'phone',
        'email',
        'teacher_id',
        'student_status_id',
        'admission_channel_id',
        'high_school_id',
        'study_plan_id',
        'entry_year',
        'gpa',
        'passed_credits',
        'not_passed_credits',
        'overed_credits',

        'guardian_title_id',
        'guardian_first_name_th',
        'guardian_last_name_th',
        'guardian_relationship_id',
        'guardian_phone',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class, 'title_id');
    }

    public function guardianTitle(): BelongsTo
    {
        return $this->belongsTo(Title::class, 'guardian_title_id');
    }

    public function guardianRelationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class, 'guardian_relationship_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
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

    public function studyPlan(): BelongsTo
    {
        return $this->belongsTo(StudyPlanTrack::class, 'study_plan_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'student_id');
    }
}