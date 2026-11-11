<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolves a support-line user's place in support_departments > support_works > support_units.
 *
 * Support positions are global (positions.support_unit_id is null, see
 * 2026_09_27_000001_promote_support_unit_positions_to_global), so the user's unit lives in the
 * validated users.department path "ฝ่าย > งาน > หน่วย" ("ฝ่าย > งาน" for a head of งาน).
 * A unit-scoped position still wins when one is assigned.
 */
class SupportUnitResolver
{
    /** @var array<string, array{departmentName: string, workName: ?string, unitName: ?string, unitId: ?int}|null> */
    private array $pathCache = [];

    /** @var array<int, ?int> */
    private array $positionUnitCache = [];

    /**
     * Matches the path against the support tables. Returns null unless at least the department exists;
     * deeper levels are kept only when they exist under their parent.
     *
     * @return array{departmentName: string, workName: ?string, unitName: ?string, unitId: ?int}|null
     */
    public function fromDepartmentPath(?string $department): ?array
    {
        $parts = array_values(array_filter(array_map('trim', explode(' > ', (string) $department))));
        if ($parts === [] || count($parts) > 3) {
            return null;
        }

        $key = implode("\x1f", $parts);
        if (array_key_exists($key, $this->pathCache)) {
            return $this->pathCache[$key];
        }

        $departmentId = DB::table('support_departments')->where('name', $parts[0])->value('id');
        if (! $departmentId) {
            return $this->pathCache[$key] = null;
        }

        $workId = isset($parts[1])
            ? DB::table('support_works')->where('support_department_id', $departmentId)->where('name', $parts[1])->value('id')
            : null;
        $unitId = $workId && isset($parts[2])
            ? DB::table('support_units')->where('support_work_id', $workId)->where('name', $parts[2])->value('id')
            : null;

        return $this->pathCache[$key] = [
            'departmentName' => $parts[0],
            'workName' => $workId ? $parts[1] : null,
            'unitName' => $unitId ? $parts[2] : null,
            'unitId' => $unitId ? (int) $unitId : null,
        ];
    }

    /** Roles that lead a งาน and therefore sit at ฝ่าย > งาน instead of a unit. */
    public const WORK_LEVEL_ROLES = ['supervisor'];

    /**
     * True when the path places the person correctly: ฝ่าย > งาน > หน่วย for everyone,
     * or ฝ่าย > งาน for a head of งาน (supervisor).
     */
    public function isPlaced(?string $department, ?string $roleKey): bool
    {
        $depth = count(array_filter(array_map('trim', explode(' > ', (string) $department))));
        $resolved = $this->fromDepartmentPath($department);
        if (! $resolved) {
            return false;
        }

        return match ($depth) {
            3 => $resolved['unitId'] !== null,
            2 => in_array($roleKey, self::WORK_LEVEL_ROLES, true) && $resolved['workName'] !== null,
            default => false,
        };
    }

    /** Unit of the user's position when it is unit-scoped, otherwise the unit from users.department. */
    public function unitIdFor(?int $positionId, ?string $department): ?int
    {
        if ($positionId) {
            if (! array_key_exists($positionId, $this->positionUnitCache)) {
                $unitId = DB::table('positions')->where('id', $positionId)->value('support_unit_id');
                $this->positionUnitCache[$positionId] = $unitId ? (int) $unitId : null;
            }
            if ($this->positionUnitCache[$positionId]) {
                return $this->positionUnitCache[$positionId];
            }
        }

        return $this->fromDepartmentPath($department)['unitId'] ?? null;
    }
}
