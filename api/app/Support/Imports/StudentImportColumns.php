<?php

namespace App\Support\Imports;

final class StudentImportColumns
{
    private const REQUIRED_HEADERS = [
        'full_name',
        'email_address',
        'mobile_number',
        'gender',
    ];

    private const ALL_HEADERS = [
        'student_uid',
        'full_name',
        'email_address',
        'mobile_number',
        'gender',
        'date_of_birth',
        'map_id',
        'erp_number',
        'department',
        'year_of_study',
        'father_name',
        'father_mobile_number',
        'mother_name',
        'mother_mobile_number',
        'local_guardian_name',
        'local_guardian_contact',
        'local_relationship',
        'local_address',
        'blood_group',
        'medical_information',
    ];

    /**
     * Human-readable Google Form response headers that map to student import fields.
     * "Timestamp" is intentionally included because Google Forms adds it automatically.
     */
    private const GOOGLE_FORM_RESPONSE_HEADERS = [
        'Timestamp',
        'Full Name',
        'Email Address',
        'Mobile Number',
        'Gender',
        'Date of Birth',
        'Student UID',
        'MAP ID',
        'ERP Number',
        'Department',
        'Year of Study',
        'Father Name',
        'Father Mobile Number',
        'Mother Name',
        'Mother Mobile Number',
        'Local Guardian Name',
        'Local Guardian Contact',
        'Local Relationship',
        'Local Address',
        'Blood Group',
        'Medical Information',
    ];

    private const HEADER_ALIASES = [
        'student_uid' => [
            'student uid',
            'student id',
            'student identifier',
            'admission number',
            'admission no',
            'roll number',
            'roll no',
        ],
        'full_name' => [
            'full name',
            'student name',
            'name',
        ],
        'email_address' => [
            'email address',
            'email',
            'email id',
            'emailid',
        ],
        'mobile_number' => [
            'mobile number',
            'phone number',
            'phone',
            'contact number',
            'contact no',
            'student mobile number',
            'whatsapp number',
            'whatsapp no',
        ],
        'gender' => [
            'gender',
            'sex',
        ],
        'date_of_birth' => [
            'date of birth',
            'dob',
            'birth date',
        ],
        'map_id' => [
            'map id',
            'map student id',
            'map_student_id',
            'map identifier',
        ],
        'erp_number' => [
            'erp number',
            'erp no',
            'erp id',
            'erp',
        ],
        'department' => [
            'department',
            'branch',
            'course',
            'program',
        ],
        'year_of_study' => [
            'year of study',
            'study year',
            'academic year',
            'year',
            'semester year',
        ],
        'father_name' => [
            'father name',
            'fathers name',
        ],
        'father_mobile_number' => [
            'father mobile number',
            'father contact number',
            'father phone number',
            'father mobile',
        ],
        'mother_name' => [
            'mother name',
            'mothers name',
        ],
        'mother_mobile_number' => [
            'mother mobile number',
            'mother contact number',
            'mother phone number',
            'mother mobile',
        ],
        'local_guardian_name' => [
            'local guardian name',
            'guardian name',
        ],
        'local_guardian_contact' => [
            'local guardian contact',
            'local guardian mobile number',
            'local guardian phone number',
            'guardian contact',
            'guardian mobile number',
            'guardian phone number',
        ],
        'local_relationship' => [
            'local relationship',
            'relationship with guardian',
            'guardian relationship',
            'relationship',
        ],
        'local_address' => [
            'local address',
            'guardian address',
            'address',
        ],
        'blood_group' => [
            'blood group',
            'blood type',
        ],
        'medical_information' => [
            'medical information',
            'medical info',
            'medical history',
            'health notes',
            'health information',
            'allergies',
        ],
    ];

    private static ?array $resolvedHeaderMap = null;

    /**
     * @return list<string>
     */
    public static function requiredHeaders(): array
    {
        return self::REQUIRED_HEADERS;
    }

    /**
     * @return list<string>
     */
    public static function allHeaders(): array
    {
        return self::ALL_HEADERS;
    }

    /**
     * @return list<string>
     */
    public static function googleFormResponseHeaders(): array
    {
        return self::GOOGLE_FORM_RESPONSE_HEADERS;
    }

    /**
     * @param array<int|string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $canonical = self::resolveHeader((string) $key);
            if ($canonical === null) {
                continue;
            }

            $cleanValue = is_string($value) ? trim($value) : $value;

            if (! array_key_exists($canonical, $normalized) || self::isEmptyValue($normalized[$canonical])) {
                $normalized[$canonical] = $cleanValue;
            }
        }

        return $normalized;
    }

    /**
     * @param list<string> $headers
     * @return list<string>
     */
    public static function normalizeHeaders(array $headers): array
    {
        $resolved = [];

        foreach ($headers as $header) {
            $canonical = self::resolveHeader($header);
            if ($canonical !== null) {
                $resolved[] = $canonical;
            }
        }

        return array_values(array_unique($resolved));
    }

    public static function resolveHeader(string $header): ?string
    {
        $normalized = self::canonicalize($header);
        if ($normalized === '') {
            return null;
        }

        $resolved = self::resolvedHeaderMap()[$normalized] ?? null;
        if ($resolved !== null) {
            return $resolved;
        }

        // Handle common Google Form labels like "Field Name (Optional)".
        $withoutOptional = preg_replace('/_(optional|required)$/', '', $normalized) ?? $normalized;

        return self::resolvedHeaderMap()[$withoutOptional] ?? null;
    }

    public static function canonicalize(string $header): string
    {
        $cleaned = trim($header);
        $cleaned = preg_replace('/\((optional|required)\)/i', '', $cleaned) ?? $cleaned;
        $cleaned = str_replace('*', '', $cleaned);
        $cleaned = strtolower($cleaned);
        $cleaned = preg_replace('/[^a-z0-9]+/', '_', $cleaned) ?? $cleaned;

        return trim($cleaned, '_');
    }

    /**
     * @return array<string, string>
     */
    private static function resolvedHeaderMap(): array
    {
        if (self::$resolvedHeaderMap !== null) {
            return self::$resolvedHeaderMap;
        }

        $map = [];

        foreach (self::ALL_HEADERS as $canonical) {
            $map[self::canonicalize($canonical)] = $canonical;
        }

        foreach (self::HEADER_ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $normalizedAlias = self::canonicalize($alias);
                if ($normalizedAlias !== '') {
                    $map[$normalizedAlias] = $canonical;
                }
            }
        }

        self::$resolvedHeaderMap = $map;

        return self::$resolvedHeaderMap;
    }

    private static function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }
}
