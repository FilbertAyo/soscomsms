<?php

namespace App\Services;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Customer;
use App\Support\TanzaniaPhone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactImporter
{
    /**
     * @return array{created: int, skipped: int, invalid: int, errors: list<array{row: int, reason: string}>}
     */
    public function import(
        Customer $customer,
        UploadedFile $file,
        string $nameColumn = 'name',
        string $phoneColumn = 'phone',
        ?string $groupColumn = 'group',
        bool $hasHeader = true,
        ?int $nameIndex = null,
        ?int $phoneIndex = null,
        ?int $groupIndex = null,
    ): array {
        return $this->importPath(
            $customer,
            $file->getRealPath() ?: $file->getPathname(),
            $nameColumn,
            $phoneColumn,
            $groupColumn,
            $hasHeader,
            $nameIndex,
            $phoneIndex,
            $groupIndex,
        );
    }

    /**
     * @return array{created: int, skipped: int, invalid: int, errors: list<array{row: int, reason: string}>}
     */
    public function importPath(
        Customer $customer,
        string $path,
        string $nameColumn = 'name',
        string $phoneColumn = 'phone',
        ?string $groupColumn = 'group',
        bool $hasHeader = true,
        ?int $nameIndex = null,
        ?int $phoneIndex = null,
        ?int $groupIndex = null,
    ): array {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [
                'created' => 0,
                'skipped' => 0,
                'invalid' => 1,
                'errors' => [['row' => 0, 'reason' => 'The file could not be read.']],
            ];
        }

        try {
            $indexes = [
                'name' => $this->isIgnored($nameColumn) ? null : $nameIndex,
                'phone' => $phoneIndex,
                'group' => $this->isIgnored($groupColumn) ? null : $groupIndex,
            ];

            if (! $hasHeader) {
                $indexes = [
                    'phone' => 0,
                    'group' => 1,
                    'name' => 2,
                ];
            } else {
                $header = fgetcsv($handle);
                if ($header === false) {
                    return [
                        'created' => 0,
                        'skipped' => 0,
                        'invalid' => 1,
                        'errors' => [['row' => 1, 'reason' => 'The file is empty.']],
                    ];
                }

                $fromHeader = $this->columnIndexes($header, $nameColumn, $phoneColumn, $groupColumn);
                $indexes['name'] = $this->isIgnored($nameColumn) ? null : ($nameIndex ?? $fromHeader['name']);
                $indexes['phone'] = $phoneIndex ?? $fromHeader['phone'];
                $indexes['group'] = $this->isIgnored($groupColumn)
                    ? null
                    : ($groupIndex ?? $fromHeader['group']);
            }

            if ($indexes['phone'] === null) {
                return [
                    'created' => 0,
                    'skipped' => 0,
                    'invalid' => 1,
                    'errors' => [['row' => 1, 'reason' => 'Choose the phone number column.']],
                ];
            }

            $created = 0;
            $skipped = 0;
            $invalid = 0;
            $errors = [];
            $rowNumber = $hasHeader ? 1 : 0;
            $seenInFile = [];
            $pending = [];
            $groups = $customer->contactGroups()
                ->get()
                ->keyBy(fn (ContactGroup $group): string => Str::lower($group->name));

            return DB::transaction(function () use (
                $handle,
                $indexes,
                $customer,
                &$created,
                &$skipped,
                &$invalid,
                &$errors,
                &$rowNumber,
                &$seenInFile,
                &$pending,
                &$groups,
            ): array {
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;

                    if ($this->isEmptyRow($row)) {
                        continue;
                    }

                    $phoneRaw = trim((string) ($row[$indexes['phone']] ?? ''));
                    $groupName = $indexes['group'] === null
                        ? ''
                        : trim((string) ($row[$indexes['group']] ?? ''));
                    $name = $indexes['name'] === null
                        ? ''
                        : trim((string) ($row[$indexes['name']] ?? ''));

                    if (! TanzaniaPhone::isValid($phoneRaw)) {
                        $invalid++;
                        $errors[] = ['row' => $rowNumber, 'reason' => 'Enter a valid Tanzanian mobile number.'];

                        continue;
                    }

                    $phone = TanzaniaPhone::normalize($phoneRaw);
                    if ($name === '') {
                        $name = $phone;
                    }

                    if (isset($seenInFile[$phone])) {
                        $skipped++;

                        continue;
                    }

                    $seenInFile[$phone] = true;
                    $pending[] = [
                        'name' => $name,
                        'phone' => $phone,
                        'group_id' => $this->groupIdFor($customer, $groups, $groupName),
                    ];

                    if (count($pending) >= 250) {
                        [$createdChunk, $skippedChunk] = $this->flush($customer, $pending);
                        $created += $createdChunk;
                        $skipped += $skippedChunk;
                        $pending = [];
                    }

                    if ($created + $skipped + $invalid + count($pending) >= 5000) {
                        break;
                    }
                }

                if ($pending !== []) {
                    [$createdChunk, $skippedChunk] = $this->flush($customer, $pending);
                    $created += $createdChunk;
                    $skipped += $skippedChunk;
                }

                return [
                    'created' => $created,
                    'skipped' => $skipped,
                    'invalid' => $invalid,
                    'errors' => array_slice($errors, 0, 25),
                ];
            });
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  Collection<string, ContactGroup>  $groups
     */
    private function groupIdFor(Customer $customer, $groups, string $groupName): ?int
    {
        if ($groupName === '') {
            return null;
        }

        $key = Str::lower($groupName);
        $group = $groups->get($key);
        if ($group === null) {
            $group = $customer->contactGroups()->create([
                'name' => $groupName,
                'description' => null,
            ]);
            $groups->put($key, $group);
        }

        return $group->id;
    }

    /**
     * @param  list<array{name: string, phone: string, group_id: int|null}>  $pending
     * @return array{0: int, 1: int}
     */
    private function flush(Customer $customer, array $pending): array
    {
        $phones = array_column($pending, 'phone');
        $existing = $customer->contacts()
            ->whereIn('phone', $phones)
            ->pluck('phone')
            ->all();
        $existingSet = array_fill_keys($existing, true);

        $now = now();
        $insert = [];
        $skipped = 0;

        foreach ($pending as $row) {
            if (isset($existingSet[$row['phone']])) {
                $skipped++;

                continue;
            }

            $insert[] = [
                'customer_id' => $customer->id,
                'contact_group_id' => $row['group_id'],
                'name' => $row['name'],
                'phone' => $row['phone'],
                'status' => ContactStatus::Active->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $existingSet[$row['phone']] = true;
        }

        foreach (array_chunk($insert, 250) as $chunk) {
            Contact::query()->insert($chunk);
        }

        return [count($insert), $skipped];
    }

    /**
     * @param  list<string|null>  $header
     * @return array{name: int|null, phone: int|null, group: int|null}
     */
    private function columnIndexes(array $header, string $nameColumn, string $phoneColumn, ?string $groupColumn): array
    {
        $normalized = [];
        foreach ($header as $index => $label) {
            $normalized[$this->normalizeHeader((string) $label)] = $index;
        }

        return [
            'name' => $this->isIgnored($nameColumn)
                ? null
                : ($normalized[$this->normalizeHeader($nameColumn)] ?? $normalized['name'] ?? null),
            'phone' => $normalized[$this->normalizeHeader($phoneColumn)] ?? $normalized['phone'] ?? $normalized['phone number'] ?? null,
            'group' => $groupColumn === null || $this->isIgnored($groupColumn)
                ? null
                : ($normalized[$this->normalizeHeader($groupColumn)] ?? $normalized['group'] ?? null),
        ];
    }

    private function isIgnored(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        $normalized = $this->normalizeHeader($value);

        return $normalized === '' || $normalized === 'ignore' || $normalized === 'ignore this column';
    }

    private function normalizeHeader(string $value): string
    {
        return Str::lower(trim(preg_replace('/\xEF\xBB\xBF/', '', $value) ?? $value));
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
