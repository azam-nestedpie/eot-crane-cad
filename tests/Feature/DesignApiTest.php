<?php

namespace Tests\Feature;

use App\Models\Design;
use App\Services\Crane\CraneCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Workshop A crane',
            'capacity_t' => 10,
            'span_mm' => 25000,
            'lifting_height_mm' => 7900,
            'rail_height_mm' => 8321,
            'building_height_mm' => null,
            'duty_group' => 'A5',
            'notes' => null,
            ...$overrides,
        ];
    }

    public function test_it_creates_a_design_and_returns_the_calculation(): void
    {
        $response = $this->postJson(route('api.v1.designs.store'), $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Workshop A crane')
            ->assertJsonPath('data.calculation.dimensions.overall_length.value', 25270);

        $this->assertDatabaseHas('designs', ['name' => 'Workshop A crane', 'span_mm' => 25000]);
    }

    public function test_it_lists_designs_without_calculation(): void
    {
        Design::factory()->count(2)->create();

        $this->getJson(route('api.v1.designs.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.calculation');
    }

    public function test_it_shows_a_design(): void
    {
        $design = Design::factory()->create();

        $this->getJson(route('api.v1.designs.show', $design))
            ->assertOk()
            ->assertJsonPath('data.id', $design->id)
            ->assertJsonPath('data.calculation.dimensions.span.value', 25000);
    }

    public function test_it_updates_a_design(): void
    {
        $design = Design::factory()->create();

        $this->putJson(route('api.v1.designs.update', $design), $this->validPayload(['name' => 'Renamed', 'span_mm' => 20000]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.calculation.dimensions.overall_length.value', 20270);

        $this->assertSame(20000, $design->fresh()->span_mm);
    }

    public function test_it_deletes_a_design(): void
    {
        $design = Design::factory()->create();

        $this->deleteJson(route('api.v1.designs.destroy', $design))->assertNoContent();

        $this->assertModelMissing($design);
    }

    public function test_missing_design_returns_json_404(): void
    {
        $this->getJson('/api/v1/designs/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']);
    }

    public function test_malformed_json_returns_400_not_validation_errors(): void
    {
        $this->call('POST', route('api.v1.designs.store'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: '{"name": "broken')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Malformed JSON body.');

        $this->assertDatabaseCount('designs', 0);
    }

    public function test_validation_messages_use_readable_field_names(): void
    {
        $this->postJson(route('api.v1.designs.store'), $this->validPayload(['span_mm' => null]))
            ->assertJsonPath('errors.span_mm.0', 'The span field is required.');
    }

    public function test_it_rejects_missing_and_out_of_range_fields(): void
    {
        $this->postJson(route('api.v1.designs.store'), $this->validPayload([
            'name' => '   ',
            'capacity_t' => 50,
            'span_mm' => 1000,
            'duty_group' => 'Z9',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'capacity_t', 'span_mm', 'duty_group']);

        $this->assertDatabaseCount('designs', 0);
    }

    public function test_it_rejects_non_integer_millimetres_and_bad_capacity_steps(): void
    {
        $this->postJson(route('api.v1.designs.store'), $this->validPayload([
            'span_mm' => 'twenty',
            'capacity_t' => 7.3,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['span_mm', 'capacity_t']);
    }

    public function test_it_rejects_a_lifting_height_the_hoist_cannot_reach(): void
    {
        $this->postJson(route('api.v1.designs.store'), $this->validPayload(['lifting_height_mm' => 8500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lifting_height_mm']);
    }

    public function test_it_rejects_a_building_too_low_for_the_crane(): void
    {
        $this->postJson(route('api.v1.designs.store'), $this->validPayload(['building_height_mm' => 9000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['building_height_mm']);
    }

    public function test_update_with_invalid_data_leaves_the_design_unchanged(): void
    {
        $design = Design::factory()->create(['name' => 'Original']);

        $this->putJson(route('api.v1.designs.update', $design), $this->validPayload(['name' => 'Changed', 'span_mm' => 99999]))
            ->assertUnprocessable();

        $this->assertSame('Original', $design->fresh()->name);
    }

    public function test_input_schema_exposes_the_same_limits_the_server_validates(): void
    {
        $this->getJson(route('api.v1.input-schema'))
            ->assertOk()
            ->assertJsonPath('data.fields.span_mm.min', CraneCalculator::LIMITS['span_mm'][0])
            ->assertJsonPath('data.fields.span_mm.max', CraneCalculator::LIMITS['span_mm'][1])
            ->assertJsonPath('data.fields.building_height_mm.required', false)
            ->assertJsonPath('data.duty_groups', ['A3', 'A4', 'A5', 'A6']);
    }

    public function test_calculate_endpoint_previews_without_saving(): void
    {
        $this->postJson(route('api.v1.calculate'), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('data.dimensions.overall_length.value', 25270)
            ->assertJsonPath('data.dimensions.overall_length.source', 'derived');

        $this->assertDatabaseCount('designs', 0);
    }
}
