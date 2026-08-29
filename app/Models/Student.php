<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'phone',
        'email',
        'teacher_id',
        'student_status_id',
        'admission_channel_id',
        'high_school_id',
        'study_plan_id',
        'system_department_id',
        'entry_year',
        'study_year',
        'study_semester',
        'study_period',
        'gpa',
        'gpax',
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
        'title_id' => 'integer',
        'teacher_id' => 'integer',
        'student_status_id' => 'integer',
        'admission_channel_id' => 'integer',
        'high_school_id' => 'integer',
        'study_plan_id' => 'integer',
        'system_department_id' => 'integer',
        'entry_year' => 'integer',
        'study_year' => 'integer',
        'study_semester' => 'integer',
        'gpa' => 'decimal:2',
        'gpax' => 'decimal:2',
        'passed_credits' => 'integer',
        'not_passed_credits' => 'integer',
        'overed_credits' => 'integer',
        'guardian_title_id' => 'integer',
        'guardian_relationship_id' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function scopeStudying(Builder $query): Builder
    {
        return $query->whereHas(
            'studentStatus',
            fn (Builder $statusQuery) => $statusQuery
                ->where('status_name', Status::STUDYING)
        );
    }

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

    public function systemTeacher(): BelongsTo
    {
        return $this->belongsTo(SystemTeacher::class, 'teacher_id');
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

    public function curriculumPlan(): BelongsTo
    {
        return $this->belongsTo(CurriculumPlan::class, 'study_plan_id');
    }

    public function systemDepartment(): BelongsTo
    {
        return $this->belongsTo(SystemDepartment::class, 'system_department_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'student_id');
    }
}
