<?php

namespace App\Contracts;

interface TeacherApi
{
    public function getTeachers(): array;

    public function findTeacher(int $teacherId): ?array;

    public function findTeacherByNontriId(string $nontriId): ?array;
}
