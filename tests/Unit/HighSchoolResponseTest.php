<?php

namespace Tests\Unit;

use App\Http\Responses\HighSchoolResponse;
use App\Models\District;
use App\Models\HighSchool;
use App\Models\Province;
use App\Models\Subdistrict;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class HighSchoolResponseTest extends TestCase
{
    public function test_it_includes_ids_and_names_for_every_geography_level(): void
    {
        $province = (new Province)->forceFill([
            'id' => 10,
            'province_name' => 'Test Province',
        ]);
        $district = (new District)->forceFill([
            'id' => 20,
            'district_name' => 'Test District',
        ])->setRelation('province', $province);
        $subdistrict = (new Subdistrict)->forceFill([
            'id' => 30,
            'subdistrict_name' => 'Test Subdistrict',
        ])->setRelation('district', $district);
        $highSchool = (new HighSchool)->forceFill([
            'id' => 40,
            'school_name' => 'Test School',
            'subdistrict_id' => 30,
            'latitude' => 13.7563,
            'longitude' => 100.5018,
            'status' => HighSchool::STATUS_ACTIVE,
        ])->setRelation('subdistrict', $subdistrict);

        $response = (new HighSchoolResponse($highSchool))->toArray(new Request);

        $this->assertSame(10, $response['province_id']);
        $this->assertSame('Test Province', $response['province_name']);
        $this->assertSame(20, $response['district_id']);
        $this->assertSame('Test District', $response['district_name']);
        $this->assertSame(30, $response['subdistrict_id']);
        $this->assertSame('Test Subdistrict', $response['subdistrict_name']);
    }
}
