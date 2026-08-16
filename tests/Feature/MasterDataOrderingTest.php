<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterDataOrderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(AuthenticateJwt::class);

        Schema::create('titles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('title_name_th');
        });
        Schema::create('admission_channels', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('channel_name');
        });
        Schema::create('provinces', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('province_name');
        });
        Schema::create('districts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('district_name');
            $table->unsignedInteger('province_id');
        });
        Schema::create('subdistricts', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('subdistrict_name');
            $table->unsignedInteger('district_id');
        });
        Schema::create('relationships', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('relationship_name');
        });
        Schema::create('student_statuses', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('status_name');
        });
        Schema::create('note_types', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('note');
        });
        Schema::create('curriculum_plans', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name_th');
        });
        Schema::create('curriculum_divisions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('name_th');
            $table->string('division_type');
        });

        $this->insertNames('titles', 'title_name_th');
        $this->insertNames('admission_channels', 'channel_name');
        $this->insertNames('provinces', 'province_name');
        $this->insertNames('relationships', 'relationship_name');
        $this->insertNames('student_statuses', 'status_name');
        $this->insertNames('note_types', 'note');
        $this->insertNames('curriculum_plans', 'name_th');

        DB::table('districts')->insert([
            ['id' => 1, 'district_name' => 'Zulu', 'province_id' => 1],
            ['id' => 2, 'district_name' => 'Alpha', 'province_id' => 1],
        ]);
        DB::table('subdistricts')->insert([
            ['id' => 1, 'subdistrict_name' => 'Zulu', 'district_id' => 1],
            ['id' => 2, 'subdistrict_name' => 'Alpha', 'district_id' => 1],
        ]);
        DB::table('curriculum_divisions')->insert([
            ['id' => 1, 'parent_id' => null, 'name_th' => 'Zulu', 'division_type' => 'group'],
            ['id' => 2, 'parent_id' => null, 'name_th' => 'Alpha', 'division_type' => 'group'],
        ]);
    }

    public function test_master_data_responses_are_ordered_by_name(): void
    {
        $cases = [
            ['/api/titles', 'title_name_th'],
            ['/api/admission-channels', 'channel_name'],
            ['/api/provinces', 'province_name'],
            ['/api/districts?province_id=1', 'district_name'],
            ['/api/subdistricts?district_id=1', 'subdistrict_name'],
            ['/api/relationships', 'relationship_name'],
            ['/api/student-statuses', 'status_name'],
            ['/api/note-types', 'note'],
            ['/api/study-plans', 'name_th'],
            ['/api/curriculum-divisions', 'name_th'],
        ];

        foreach ($cases as [$uri, $nameField]) {
            $this->getJson($uri)
                ->assertOk()
                ->assertJsonPath("data.0.{$nameField}", 'Alpha')
                ->assertJsonPath("data.1.{$nameField}", 'Zulu');
        }
    }

    private function insertNames(string $table, string $nameField): void
    {
        DB::table($table)->insert([
            ['id' => 1, $nameField => 'Zulu'],
            ['id' => 2, $nameField => 'Alpha'],
        ]);
    }
}
