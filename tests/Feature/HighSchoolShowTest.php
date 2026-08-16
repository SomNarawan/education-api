<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HighSchoolShowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(AuthenticateJwt::class);

        Schema::create('provinces', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('province_name');
        });
        Schema::create('districts', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('province_id');
            $table->string('district_name');
        });
        Schema::create('subdistricts', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('district_id');
            $table->string('subdistrict_name');
        });
        Schema::create('high_schools', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('school_name');
            $table->unsignedInteger('subdistrict_id');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('status');
            $table->timestamps();
            $table->string('created_by');
            $table->string('updated_by');
        });

        DB::table('provinces')->insert(['id' => 1, 'province_name' => 'Province A']);
        DB::table('districts')->insert(['id' => 2, 'province_id' => 1, 'district_name' => 'District A']);
        DB::table('subdistricts')->insert(['id' => 3, 'district_id' => 2, 'subdistrict_name' => 'Subdistrict A']);
        DB::table('high_schools')->insert([
            'id' => 4,
            'school_name' => 'School A',
            'subdistrict_id' => 3,
            'latitude' => 13.7563,
            'longitude' => 100.5018,
            'status' => 'active',
            'created_at' => now(),
            'created_by' => 'Creator',
            'updated_at' => now(),
            'updated_by' => 'Editor',
        ]);
    }

    public function test_it_returns_one_high_school_with_its_geography(): void
    {
        $this->getJson('/api/high-schools/4')
            ->assertOk()
            ->assertJsonPath('data.id', 4)
            ->assertJsonPath('data.province_id', 1)
            ->assertJsonPath('data.province_name', 'Province A')
            ->assertJsonPath('data.district_id', 2)
            ->assertJsonPath('data.district_name', 'District A')
            ->assertJsonPath('data.subdistrict_id', 3)
            ->assertJsonPath('data.subdistrict_name', 'Subdistrict A');

        $this->getJson('/api/high-schools/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'High school not found');
    }
}
