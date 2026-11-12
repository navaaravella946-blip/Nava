<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_be_created_viewed_updated_and_deleted(): void
    {
        $patientData = [
            'patient_number' => 'PAT-001',
            'first_name' => 'Jamie',
            'last_name' => 'Rivera',
            'birth_date' => '1990-04-15',
            'sex' => 'Female',
            'contact_number' => '555-0100',
            'address' => '12 Clinic Street',
        ];

        $this->post(route('patients.store'), $patientData)
            ->assertRedirect(route('patients.index'));

        $patient = Patient::where('patient_number', 'PAT-001')->firstOrFail();
        $this->assertDatabaseHas('patients', [
            'patient_number' => 'PAT-001',
            'first_name' => 'Jamie',
            'last_name' => 'Rivera',
            'sex' => 'Female',
            'contact_number' => '555-0100',
            'address' => '12 Clinic Street',
        ]);
        $this->assertSame('1990-04-15', $patient->birth_date->toDateString());

        $this->get(route('patients.show', $patient))
            ->assertOk()
            ->assertSee('Jamie');

        $this->put(route('patients.update', $patient), [
            ...$patientData,
            'first_name' => 'Jordan',
        ])->assertRedirect(route('patients.index'));

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'first_name' => 'Jordan',
        ]);

        $this->delete(route('patients.destroy', $patient))
            ->assertRedirect(route('patients.index'));

        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
    }

    public function test_patient_number_must_be_unique_and_address_must_match_form_limit(): void
    {
        $patientData = [
            'patient_number' => 'PAT-001',
            'first_name' => 'Jamie',
            'last_name' => 'Rivera',
            'birth_date' => '1990-04-15',
            'sex' => 'Female',
        ];

        Patient::create($patientData);

        $this->from(route('patients.create'))
            ->post(route('patients.store'), [
                ...$patientData,
                'address' => str_repeat('a', 2001),
            ])
            ->assertRedirect(route('patients.create'))
            ->assertSessionHasErrors(['patient_number', 'address']);
    }
}
