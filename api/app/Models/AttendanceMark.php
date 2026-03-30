<?php

namespace App\Models;

/**
 * Compatibility wrapper so legacy namespaces (App\Models\AttendanceMark)
 * continue to resolve to the new domain model class.
 */
class AttendanceMark extends \App\Domain\Attendance\Models\AttendanceMark
{
}

