<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    protected $table = 'students';

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
}