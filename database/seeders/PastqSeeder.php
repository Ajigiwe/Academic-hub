<?php

namespace Database\Seeders;

use App\Models\Bundle;
use App\Models\Course;
use App\Models\Programme;
use App\Models\Resource;
use App\Models\Setting;
use App\Models\User;
use App\Services\BundlePricing;
use App\Services\ProgrammeSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PastqSeeder extends Seeder
{
    // Same manifest as prisma/seed.ts.
    private const PROGRAMMES = [
        ['name' => 'Procurement and Supply', 'slug' => 'procurement-and-supply'],
        ['name' => 'Marketing', 'slug' => 'marketing'],
        ['name' => 'Accounting', 'slug' => 'accounting'],
        ['name' => 'Secretaryship and Management', 'slug' => 'secretaryship-and-management'],
    ];

    private const COURSES = [
        ['code' => 'ACC 102', 'title' => 'Introduction to Financial Accounting', 'slug' => 'acc-102-introduction-to-financial-accounting'],
        ['code' => 'ACC 201', 'title' => 'Financial Reporting', 'slug' => 'acc-201-financial-reporting'],
        ['code' => 'MKT 203', 'title' => 'Principles of Marketing', 'slug' => 'mkt-203-principles-of-marketing'],
        ['code' => 'PSM 201', 'title' => 'Procurement Principles', 'slug' => 'psm-201-procurement-principles'],
        ['code' => 'PSM 305', 'title' => 'Public Procurement', 'slug' => 'psm-305-public-procurement'],
        ['code' => 'SMG 204', 'title' => 'Office Administration', 'slug' => 'smg-204-office-administration'],
    ];

    private const BUNDLES = [
        [
            'slug' => 'acc-102-introduction-to-financial-accounting-2024-2025',
            'title' => 'ACC 102 — Introduction to Financial Accounting · 2024/2025 Past Questions',
            'course' => 'ACC 102',
            'programme' => 'accounting',
            'level' => 100,
            'academic_year' => '2024/2025',
            'description' => 'Complete past questions covering the accounting cycle, journal entries, ledgers, trial balance, and basic financial statements — both semester papers included.',
            'papers' => [
                ['slug' => 'acc-102-introduction-to-financial-accounting-2024-2025-sem1-exam', 'title' => 'ACC 102 — Introduction to Financial Accounting · Semester 1 Exam', 'semester' => 1, 'page_count' => 20],
                ['slug' => 'acc-102-introduction-to-financial-accounting-2024-2025-sem2-exam', 'title' => 'ACC 102 — Introduction to Financial Accounting · Semester 2 Exam', 'semester' => 2, 'page_count' => 18],
            ],
        ],
        [
            'slug' => 'acc-201-financial-reporting-2024-2025',
            'title' => 'ACC 201 — Financial Reporting · 2024/2025 Past Questions',
            'course' => 'ACC 201',
            'programme' => 'accounting',
            'level' => 200,
            'academic_year' => '2024/2025',
            'description' => 'Past questions on IFRS-based reporting, preparation of company financial statements, consolidation basics, and cash-flow statements.',
            'papers' => [
                ['slug' => 'acc-201-financial-reporting-2024-2025-sem2-exam', 'title' => 'ACC 201 — Financial Reporting · Semester 2 Exam', 'semester' => 2, 'page_count' => 26],
            ],
        ],
        [
            'slug' => 'mkt-203-principles-of-marketing-2024-2025',
            'title' => 'MKT 203 — Principles of Marketing · 2024/2025 Past Questions',
            'course' => 'MKT 203',
            'programme' => 'marketing',
            'level' => 200,
            'academic_year' => '2024/2025',
            'description' => 'The marketing mix, segmentation and targeting, consumer behaviour, branding, and digital marketing — both semesters included.',
            'papers' => [
                ['slug' => 'mkt-203-principles-of-marketing-2024-2025-sem1-exam', 'title' => 'MKT 203 — Principles of Marketing · Semester 1 Exam', 'semester' => 1, 'page_count' => 22],
            ],
        ],
        [
            'slug' => 'psm-201-procurement-principles-2024-2025',
            'title' => 'PSM 201 — Procurement Principles · 2024/2025 Past Questions',
            'course' => 'PSM 201',
            'programme' => 'procurement-and-supply',
            'level' => 200,
            'academic_year' => '2024/2025',
            'description' => 'Procurement cycle, sourcing and supplier selection, tendering, contract management, and ethics in procurement — both semesters included.',
            'papers' => [
                ['slug' => 'psm-201-procurement-principles-2024-2025-sem1-exam', 'title' => 'PSM 201 — Procurement Principles · Semester 1 Exam', 'semester' => 1, 'page_count' => 24],
                ['slug' => 'psm-201-procurement-principles-2024-2025-sem2-exam', 'title' => 'PSM 201 — Procurement Principles · Semester 2 Exam', 'semester' => 2, 'page_count' => 21],
            ],
        ],
        [
            'slug' => 'psm-305-public-procurement-2024-2025',
            'title' => 'PSM 305 — Public Procurement · 2024/2025 Past Questions',
            'course' => 'PSM 305',
            'programme' => 'procurement-and-supply',
            'level' => 300,
            'academic_year' => '2024/2025',
            'description' => 'The Public Procurement Act, procurement planning, methods of procurement, evaluation criteria, and dispute resolution — full exam coverage.',
            'papers' => [
                ['slug' => 'psm-305-public-procurement-2024-2025-sem1-exam', 'title' => 'PSM 305 — Public Procurement · Semester 1 Exam', 'semester' => 1, 'page_count' => 28],
            ],
        ],
        [
            'slug' => 'smg-204-office-administration-2023-2024',
            'title' => 'SMG 204 — Office Administration · 2023/2024 Past Questions',
            'course' => 'SMG 204',
            'programme' => 'secretaryship-and-management',
            'level' => 200,
            'academic_year' => '2023/2024',
            'description' => 'Office procedures, records management, business communication, meeting documentation, and office technology questions.',
            'papers' => [
                ['slug' => 'smg-204-office-administration-2023-2024-sem2-exam', 'title' => 'SMG 204 — Office Administration · Semester 2 Exam', 'semester' => 2, 'page_count' => 19],
            ],
        ],
    ];

    /** Demo free materials — bundle-less, published, publicly downloadable. */
    private const MATERIALS = [
        [
            'slug' => 'mkt-203-principles-of-marketing-slides-sem1',
            'title' => 'MKT 203 — Principles of Marketing · Lecture Slides (Semester 1)',
            'description' => 'Full slide deck covering the marketing mix, segmentation, and consumer behaviour.',
            'type' => 'SLIDES',
            'course' => 'MKT 203',
            'programme' => 'marketing',
            'level' => 200,
            'semester' => 1,
            'page_count' => 14,
        ],
        [
            'slug' => 'psm-201-procurement-principles-revision-pack',
            'title' => 'PSM 201 — Procurement Principles · Revision Pack',
            'description' => 'Condensed revision notes on the procurement cycle, tendering, and contract management.',
            'type' => 'REVISION',
            'course' => 'PSM 201',
            'programme' => 'procurement-and-supply',
            'level' => 200,
            'semester' => 1,
            'page_count' => 9,
        ],
        [
            'slug' => 'acc-102-financial-accounting-lecture-notes',
            'title' => 'ACC 102 — Introduction to Financial Accounting · Lecture Notes',
            'description' => 'Topic-by-topic notes on the accounting cycle, ledgers, and trial balance.',
            'type' => 'LECTURE_NOTES',
            'course' => 'ACC 102',
            'programme' => 'accounting',
            'level' => 100,
            'semester' => 1,
            'page_count' => 12,
        ],
    ];

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@pastq.test'],
            [
                'name' => 'Platform Admin',
                'first_name' => 'Platform',
                'last_name' => 'Admin',
                'password' => Hash::make('Admin@12345'),
                'role' => 'ADMIN',
                'status' => 'ACTIVE',
            ]
        );

        User::updateOrCreate(
            ['email' => 'student@pastq.test'],
            [
                'name' => 'Ama Mensah',
                'first_name' => 'Ama',
                'last_name' => 'Mensah',
                'password' => Hash::make('Student@123'),
                'role' => 'STUDENT',
                'status' => 'ACTIVE',
            ]
        );

        foreach (self::PROGRAMMES as $p) {
            Programme::updateOrCreate(['slug' => $p['slug']], ['name' => $p['name']]);

            // Fresh installs start with the canonical programmes enabled
            // (a MISSING row would also count as enabled — this just makes
            // the state explicit for the Admin → Settings page).
            Setting::updateOrCreate(
                ['key' => ProgrammeSettings::settingKey($p['slug'])],
                ['value' => 'true']
            );
        }

        foreach (self::COURSES as $c) {
            Course::updateOrCreate(
                ['code' => $c['code']],
                ['title' => $c['title'], 'slug' => $c['slug']]
            );
        }

        // One price for every bundle (Admin → Settings). Fresh installs
        // start at the launch price; the setting is the source of truth
        // the catalogue and checkout both read.
        $pricePesewas = BundlePricing::set(BundlePricing::DEFAULT_PESEWAS);

        $programmeBySlug = Programme::all()->keyBy('slug');
        $courseByCode = Course::all()->keyBy('code');

        foreach (self::BUNDLES as $b) {
            $bundle = Bundle::updateOrCreate(
                ['slug' => $b['slug']],
                [
                    'title' => $b['title'],
                    'description' => $b['description'],
                    'status' => 'PUBLISHED',
                    'level' => $b['level'],
                    'academic_year' => $b['academic_year'],
                    // Mirror of the platform price, kept in step by BundlePricing.
                    'price_pesewas' => $pricePesewas,
                    'published_at' => now(),
                    'programme_id' => $programmeBySlug[$b['programme']]->id,
                    'course_id' => $courseByCode[$b['course']]->id,
                ]
            );

            foreach ($b['papers'] ?? [] as $p) {
                Resource::updateOrCreate(
                    ['slug' => $p['slug']],
                    [
                        'title' => $p['title'],
                        'description' => $b['description'],
                        'type' => 'PAST_QUESTION',
                        'status' => 'PUBLISHED',
                        'level' => $b['level'],
                        'semester' => $p['semester'],
                        'academic_year' => $b['academic_year'],
                        'page_count' => $p['page_count'],
                        'preview_pages' => 2,
                        'published_at' => now(),
                        'bundle_id' => $bundle->id,
                        'programme_id' => $bundle->programme_id,
                        'course_id' => $bundle->course_id,
                    ]
                );
            }
        }

        // Free materials: same resource table, no bundle — served via the
        // public download route instead of the viewer.
        foreach (self::MATERIALS as $m) {
            $programme = $programmeBySlug[$m['programme']];
            $course = $courseByCode[$m['course']];

            Resource::updateOrCreate(
                ['slug' => $m['slug']],
                [
                    'title' => $m['title'],
                    'description' => $m['description'],
                    'type' => $m['type'],
                    'status' => 'PUBLISHED',
                    'level' => $m['level'],
                    'semester' => $m['semester'],
                    'academic_year' => '2024/2025',
                    'page_count' => $m['page_count'],
                    'preview_pages' => 0,
                    'published_at' => now(),
                    'bundle_id' => null,
                    'programme_id' => $programme->id,
                    'course_id' => $course->id,
                ]
            );
        }
    }
}
