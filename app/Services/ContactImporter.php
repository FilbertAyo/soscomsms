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
    ): array {
        return $this->importPath(
            $customer,
            $file->getRealPath() ?: $file->getPathname(),
            $nameColumn,
            $phoneColumn,
            $groupColumn,
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
            $header = fgetcsv($handle);
            if ($header === false) {
                return [
                    'created' => 0,
                    'skipped' => 0,
                    'invalid' => 1,
                    'errors' => [['row' => 1, 'reason' => 'The file is empty.']],
                ];
            }

            $indexes = $this->columnIndexes($header, $nameColumn, $phoneColumn, $groupColumn);
            if ($indexes['name'] === null || $indexes['phone'] === null) {
                return [
                    'created' => 0,
                    'skipped' => 0,
                    'invalid' => 1,
                    'errors' => [['row' => 1, 'reason' => 'Name and phone columns are required.']],
                ];
            }

            $created = 0;
            $skipped = 0;
            $invalid = 0;
            $errors = [];
            $rowNumber = 1;
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

                    $name = trim((string) ($row[$indexes['name']] ?? ''));
                    $phoneRaw = trim((string) ($row[$indexes['phone']] ?? ''));
                    $groupName = $indexes['group'] === null
                        ? ''
                        : trim((string) ($row[$indexes['group']] ?? ''));

                    if ($name === '') {
                        $invalid++;
                        $errors[] = ['row' => $rowNumber, 'reason' => 'Name is missing.'];

                        continue;
                    }

                    if (! TanzaniaPhone::isValid($phoneRaw)) {
                        $invalid++;
                        $errors[] = ['row' => $rowNumber, 'reason' => 'Enter a valid Tanzanian mobile number.'];

                        continue;
                    }

                    $phone = TanzaniaPhone::normalize($phoneRaw);

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
            'name' => $normalized[$this->normalizeHeader($nameColumn)] ?? $normalized['name'] ?? null,
            'phone' => $normalized[$this->normalizeHeader($phoneColumn)] ?? $normalized['phone'] ?? $normalized['phone number'] ?? null,
            'group' => $groupColumn === null || $groupColumn === '' || $this->normalizeHeader($groupColumn) === 'ignore'
                ? null
                : ($normalized[$this->normalizeHeader($groupColumn)] ?? $normalized['group'] ?? null),
        ];
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
