<?php

namespace Database\Seeders;

use App\Models\ExamType;
use App\Models\FieldOffice;
use App\Models\School;
use App\Models\Signatory;
use App\Models\TestingCenter;
use Illuminate\Database\Seeder;

/**
 * The CSC Regional Office VIII baseline: the offices, testing centers, schools,
 * exam types and signatories the system needs before anyone can register a
 * member or schedule an examination. This is real organisational data, not demo
 * content — nothing here is faked, and re-running it is safe.
 *
 * It supersedes the old FieldOffice/School/Signatory seeders, which had drifted
 * from the office codes actually in use (LEY/SLE/BIL rather than FOLI-TAC/FOSL/
 * FOB) and knew nothing about the Western Leyte satellite office.
 *
 * Signature images and letterheads are deliberately absent: those are uploaded
 * files, and a seeder that pointed at storage paths would hand every fresh
 * install a set of broken image links. An administrator uploads them once from
 * Settings.
 */
class InitialDataSeeder extends Seeder
{
    /** code => [name, address, is_regional] */
    private const FIELD_OFFICES = [
        'RO8' => ['CSC Regional Office VIII', 'Government Center, Candahug, Palo, Leyte', true],
        'FOLI-TAC' => ['CSC Field Office - Leyte I', 'Tacloban City', false],
        'FOLII-TAC' => ['CSC Field Office - Leyte II', 'Tacloban City', false],
        'FOWL' => ['CSC Satellite Office - Western Leyte', 'Ormoc City, Leyte', false],
        'FOSL' => ['CSC Field Office - Southern Leyte', 'Maasin City, Southern Leyte', false],
        'FOB' => ['CSC Field Office - Biliran', 'Naval, Biliran', false],
        'FOS' => ['CSC Field Office - Samar', 'Catbalogan City, Samar', false],
        'FOES' => ['CSC Field Office - Eastern Samar', 'Borongan City, Eastern Samar', false],
        'FONS' => ['CSC Field Office - Northern Samar', 'Catarman, Northern Samar', false],
    ];

    /**
     * center => office codes that handle it, the first being the administering
     * office. Tacloban and Ormoc/Naval are genuinely shared — the offices take
     * turns hosting — but exactly one of them must own intake, because a
     * member's field office is resolved from the center they register at.
     *
     * The regional office is left off every center: it oversees all of them, and
     * listing it would make it a candidate to sign a field office's certificates.
     */
    private const TESTING_CENTERS = [
        'Tacloban City' => ['FOLI-TAC', 'FOLII-TAC'],
        'Ormoc City, Leyte' => ['FOWL', 'FOB'],
        'Maasin City' => ['FOSL'],
        'Naval' => ['FOB', 'FOWL'],
        'Catbalogan City' => ['FOS'],
        'Calbayog City' => ['FOS'],
        'Borongan City' => ['FOES'],
        'Catarman' => ['FONS'],
    ];

    /** center => schools sitting under it, shared by every office that handles it */
    private const SCHOOLS = [
        'Tacloban City' => [
            'Leyte National High School',
            'Eastern Visayas State University',
            'Leyte Normal University',
        ],
        'Maasin City' => [
            'Saint Joseph College',
            'Southern Leyte National High School',
        ],
        'Naval' => [
            'Biliran Province State University',
            'Naval National High School',
        ],
        'Catbalogan City' => [
            'Samar National School',
        ],
        'Calbayog City' => [
            'Northwest Samar State University',
        ],
        'Borongan City' => [
            'Eastern Samar State University',
            'Eastern Samar National Comprehensive High School',
        ],
        'Catarman' => [
            'Catarman National High School',
            'University of Eastern Philippines',
        ],
    ];

    private const EXAM_TYPES = [
        'Career Service Examination - Pen and Paper Test (Professional and Sub-Professional)',
        'Fire Officer Examination and Penology Officer Examination',
        'Career Service Examination - Computerized',
    ];

    /** [name, position, office code or null for the region-wide default, active] */
    private const SIGNATORIES = [
        ['Atty. Marilyn E. Taldo', 'Director IV', null, true],
        ['Maria Natividad L. Costibolo', 'Director II', 'FOLII-TAC', true],
        ['Atty. Flordeliza C. Algas', 'Director III', 'FOLII-TAC', false],
    ];

    public function run(): void
    {
        $offices = $this->seedFieldOffices();
        $centers = $this->seedTestingCenters($offices);

        $this->seedSchools($centers);
        $this->seedExamTypes();
        $this->seedSignatories($offices);
    }

    /** @return array<string, FieldOffice> keyed by office code */
    private function seedFieldOffices(): array
    {
        $offices = [];

        foreach (self::FIELD_OFFICES as $code => [$name, $address, $isRegional]) {
            $offices[$code] = FieldOffice::updateOrCreate(['code' => $code], [
                'name' => $name,
                'address' => $address,
                'is_regional' => $isRegional,
                'is_active' => true,
            ]);
        }

        return $offices;
    }

    /**
     * @param  array<string, FieldOffice>  $offices
     * @return array<string, TestingCenter> keyed by center name
     */
    private function seedTestingCenters(array $offices): array
    {
        $centers = [];

        foreach (self::TESTING_CENTERS as $name => $officeCodes) {
            $center = TestingCenter::updateOrCreate(['name' => $name], ['is_active' => true]);

            $center->fieldOffices()->sync(collect($officeCodes)
                ->mapWithKeys(fn (string $code, int $index) => [
                    $offices[$code]->id => ['is_primary' => $index === 0],
                ])
                ->all());

            $centers[$name] = $center;
        }

        return $centers;
    }

    /** @param  array<string, TestingCenter>  $centers */
    private function seedSchools(array $centers): void
    {
        foreach (self::SCHOOLS as $centerName => $schools) {
            foreach ($schools as $school) {
                School::updateOrCreate(
                    ['name' => $school, 'testing_center_id' => $centers[$centerName]->id],
                    ['is_active' => true],
                );
            }
        }
    }

    private function seedExamTypes(): void
    {
        foreach (self::EXAM_TYPES as $name) {
            ExamType::updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }

    /** @param  array<string, FieldOffice>  $offices */
    private function seedSignatories(array $offices): void
    {
        foreach (self::SIGNATORIES as [$name, $position, $officeCode, $active]) {
            Signatory::updateOrCreate(
                ['name' => $name, 'field_office_id' => $officeCode ? $offices[$officeCode]->id : null],
                ['position' => $position, 'active' => $active],
            );
        }
    }
}
