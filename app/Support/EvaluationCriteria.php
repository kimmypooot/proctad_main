<?php

namespace App\Support;

/**
 * Single source of truth for the Post-Examination Evaluation rating
 * statements, ported from the PROCTAD Google Form. Shared by
 * EvaluationController (prop payload + validation counts) and the
 * Evaluations/Create Inertia page.
 */
class EvaluationCriteria
{
    public const PUNCTUALITY = [
        'Arrived before 6:30 a.m.',
        'Started the examination proper at 8:00 a.m.',
        'Accounting of Test Materials was on time and orderly',
    ];

    public const DECORUM = [
        'Wearing decent/appropriate attire',
        'No unnecessary activity (such as reading test booklet, using mobile phone, and the like) was done during the examination',
        'Wearing the Identification Card properly',
    ];

    public const PROCEDURES = [
        'In admitting examinees, required the Application Receipt and/or Identification Card',
        'No unnecessary activity (such as reading test booklet, using mobile phone, and the like) was done during the examination',
        'Able to require relatives in the same room to be seated far apart from each other',
        'Answer Sheets (AS) and/or Test Booklets (TB) sequentially distributed',
        'PSP clean and accurately accomplished',
        'Reports were clean, accurate, and timely accomplished',
    ];

    public const ROOM_READINESS = [
        'Rooms are clean and free from litter',
        'Rooms are properly ventilated and well-lighted (windows open; lights and electric fans, if any, functioning and turned on)',
        'Rooms are spacious enough to spread 25 chairs',
        'Chairs and tables are arranged according to the prescribed room layout',
        'Chairs are arranged in a snake-like manner and properly numbered',
        'A table with one (1) chair for the proctor and two (2) chairs for the examinees is placed in front of the room for accomplishing forms',
        'Chairs are one (1) meter apart, with stable stands and arms suitable for convenient writing',
        'Excess chairs are placed outside the room',
    ];

    public const EXAM_PREPARATION = [
        'Examinee identification and processing went smoothly',
        'Distribution and retrieval of test materials were organized',
        'Noise levels inside and outside the venue were manageable',
        'No major disruptions or security concerns during the exam',
        'Examination started and ended on time',
    ];

    public const VENUE_READINESS = [
        'The testing venue was clean and orderly upon arrival',
        'Restrooms were clean and accessible for examinees and staff',
        'Signages (directional, room assignments, prohibited acts) were properly posted',
    ];

    public const COMMITTEE_COORDINATION = [
        'The orientation or briefing before the exam was clear and helpful',
        'Instructions from the Secretariat were clear and consistent',
        'Roles and responsibilities of the Test Administrators were clearly assigned and understood',
        'Coordination among exam personnel was smooth and efficient',
        'Support personnel (Helpers, Janitors, Paymaster) were responsive and available when needed',
    ];

    public const CONDUCT_OF_EXAM = self::EXAM_PREPARATION;

    public const EXAMINEE_EXPERIENCE = [
        'Examinees appeared comfortable with the venue setup',
        "Examinees' questions or concerns were addressed promptly",
        'The overall flow of examinee movement was smooth (entry, seating, exit)',
    ];

    public const OVERALL_RATING_OPTIONS = [
        5 => '5 – Excellent (Smooth, organized, and exceeded expectations)',
        4 => '4 – Very Satisfactory (Well-managed; only minor issues)',
        3 => '3 – Satisfactory (Met minimum expectations)',
        2 => '2 – Fair (Some issues; needs improvement)',
        1 => '1 – Poor (Serious issues; did not meet expectations)',
    ];

    public const RATING_SCALE = [
        5 => '5 - Excellent',
        4 => '4 - Very Satisfactory',
        3 => '3 - Satisfactory',
        2 => '2 - Fair',
        1 => '1 - Poor',
    ];

    /** Props payload shared with the Vue page. */
    public static function toArray(): array
    {
        return [
            'punctuality' => self::PUNCTUALITY,
            'decorum' => self::DECORUM,
            'procedures' => self::PROCEDURES,
            'room_readiness' => self::ROOM_READINESS,
            'exam_preparation' => self::EXAM_PREPARATION,
            'venue_readiness' => self::VENUE_READINESS,
            'committee_coordination' => self::COMMITTEE_COORDINATION,
            'conduct_of_exam' => self::CONDUCT_OF_EXAM,
            'examinee_experience' => self::EXAMINEE_EXPERIENCE,
            'overall_rating_options' => self::OVERALL_RATING_OPTIONS,
            'rating_scale' => self::RATING_SCALE,
        ];
    }
}
