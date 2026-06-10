<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class StudentController extends Controller
{
    /**
     * GET /api/students
     * ข้อมูล student อย่างเดียว
     */
    public function index(): JsonResponse
    {
        $items = Student::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load students successfully'
        );
    }

    /**
     * GET /api/students/detail
     * ข้อมูล student + FK ทั้งหมด
     */
    public function detail(): JsonResponse
    {
        $items = Student::query()
            ->with([
                'title',

                'teacher',
                'teacher.title',

                'studentStatus',
                'admissionChannel',

                'highSchool',
                'highSchool.subdistrict',
                'highSchool.subdistrict.district',
                'highSchool.subdistrict.district.province',

                'affiliation',
                'studyPlan',
                'department',
                'faculty',
                'campus',

                'guardianTitle',
                'guardianRelationship',
                
                // Nested Relation
                'studyPlan.curriculum',
            ])
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $items,
            'Load student detail successfully'
        );
    }
}
