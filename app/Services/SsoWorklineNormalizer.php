<?php

namespace App\Services;

use Illuminate\Support\Str;

class SsoWorklineNormalizer
{
    public function structureType(?string $workline): ?string
    {
        $value = $this->normalize($workline);

        if ($value === '') {
            return null;
        }

        if (in_array($value, ['วิชาการ', 'สายวิชาการ', 'สายงานวิชาการ'], true)) {
            return 'academic';
        }

        if (in_array($value, ['สนับสนุน', 'สายสนับสนุน', 'สายงานสนับสนุน'], true)) {
            return 'support';
        }

        return null;
    }

    public function systemWorkline(?string $workline): ?string
    {
        return match ($this->structureType($workline)) {
            'academic' => 'สายวิชาการ',
            'support' => 'สายสนับสนุน',
            default => null,
        };
    }

    private function normalize(?string $value): string
    {
        return Str::lower((string) preg_replace('/\s+/u', '', trim((string) $value)));
    }
}
