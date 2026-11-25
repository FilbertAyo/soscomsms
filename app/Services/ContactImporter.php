<?php

namespace App\Services;

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Customer;
use App\Support\TanzaniaPhone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ContactImporter
{
    public function __construct(private SpreadsheetReader $spreadsheets) {}

    /**
     * @return array{created: int, skipped: int, invalid: int, errors: list<array{row: int, reason: string}>}
     */
    public function import(
        Customer $customer,
        UploadedFile $file,
        string $nameColumn = 'name',
        string $phoneColumn = 'phone',
        bool $hasHeader = true,
        ?int $nameIndex = null,
        ?int $phoneIndex = null,
        ?int $contactGroupId = null,
        ?string $groupName = null,
    ): array {
        return $this->importPath(
            $customer,
            $file->getRealPath() ?: $file->getPathname(),
            strtolower($file->getClientOriginalExtension()),
            $nameColumn,
            $phoneColumn,
            $hasHeader,
            $nameIndex,
            $phoneIndex,
            $contactGroupId,
            $groupName,
        );
    }

    /**
     * @return array{created: int, skipped: int, invalid: int, errors: list<array{row: int, reason: string}>}
     */
    public function importPath(
        Customer $customer,
        string $path,
        string $extension,
        string $nameColumn = 'name',
        string $phoneColumn = 'phone',
        bool $hasHeader = true,
        ?int $nameIndex = null,
        ?int $phoneIndex = null,
        ?int $contactGroupId = null,
        ?string $groupName = null,
    ): array {
        try {
            $rows = $this->spreadsheets->rows($path, $extension);
        } catch (Throwable) {
            return $this->failure('The file could not be read. Use a CSV or Excel file.');
        }

        if ($this->usedRows($rows) === []) {
            return $this->failure('The file is empty.', 1);
        }

        if ($hasHeader) {
            $header = array_shift($rows);
            if ($header === null) {
                return $this->failure('The file is empty.', 1);
            }

            $fromHeader = $this->columnIndexes($header, $nameColumn, $phoneColumn);
            $indexes = [
                'name' => $this->isIgnored($nameColumn) ? null : ($nameIndex ?? $fromHeader['name']),
                'phone' => $phoneIndex ?? $fromHeader['phone'],
            ];
        } else {
            $indexes = [
                'phone' => 0,
                'name' => $this->isIgnored($nameColumn) ? null : 1,
            ];
        }

        if ($indexes['phone'] === null) {
            return $this->failure('Choose the phone number column.', 1);
        }

        $created = 0;
        $skipped = 0;
        $invalid = 0;
        $errors = [];
        $rowNumber = $hasHeader ? 1 : 0;
        $seenInFile = [];
        $pending = [];

        return DB::transaction(function () use (
            $rows,
            $indexes,
            $customer,
            $contactGroupId,
            $groupName,
            &$created,
            &$skipped,
            &$invalid,
            &$errors,
            &$rowNumber,
            &$seenInFile,
            &$pending,
        ): array {
            $groupId = $this->existingGroupId($customer, $contactGroupId, $groupName);
            if ($groupId === false) {
                return $contactGroupId !== null
                    ? $this->failure('Choose a group that belongs to this account.')
                    : $this->failure('Choose a group for these contacts.');
            }

            foreach ($rows as $row) {
                $rowNumber++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $phoneRaw = trim((string) ($row[$indexes['phone']] ?? ''));
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
                if ($groupId === null) {
                    $groupId = $this->createGroup($customer, (string) $groupName)->id;
                }

                $pending[] = [
                    'name' => $name,
                    'phone' => $phone,
                    'group_id' => $groupId,
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
    }

    /**
     * @param  list<array{name: string, phone: string, group_id: int}>  $pending
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
     * Existing group id, null when a new name should be created on the first valid row, or false when the choice is invalid.
     */
    private function existingGroupId(Customer $customer, ?int $contactGroupId, ?string $groupName): int|null|false
    {
        if ($contactGroupId !== null) {
            return $customer->contactGroups()->whereKey($contactGroupId)->first()?->id ?? false;
        }

        $groupName = trim((string) $groupName);
        if ($groupName === '') {
            return false;
        }

        $existing = $customer->contactGroups()
            ->get()
            ->first(fn (ContactGroup $group): bool => Str::lower($group->name) === Str::lower($groupName));

        return $existing?->id;
    }

    private function createGroup(Customer $customer, string $groupName): ContactGroup
    {
        return $customer->contactGroups()->create([
            'name' => trim($groupName),
            'description' => null,
        ]);
    }

    /**
     * @param  list<string>  $header
     * @return array{name: int|null, phone: int|null}
     */
    private function columnIndexes(array $header, string $nameColumn, string $phoneColumn): array
    {
        $normalized = [];
        foreach ($header as $index => $label) {
            $normalized[$this->normalizeHeader($label)] = $index;
        }

        return [
            'name' => $this->isIgnored($nameColumn)
                ? null
                : ($normalized[$this->normalizeHeader($nameColumn)] ?? $normalized['name'] ?? null),
            'phone' => $normalized[$this->normalizeHeader($phoneColumn)] ?? $normalized['phone'] ?? $normalized['phone number'] ?? null,
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
     * @param  list<string>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<list<string>>  $rows
     * @return list<list<string>>
     */
    private function usedRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => ! $this->isEmptyRow($row),
        ));
    }

    /**
     * @return array{created: int, skipped: int, invalid: int, errors: list<array{row: int, reason: string}>}
     */
    private function failure(string $reason, int $row = 0): array
    {
        return [
            'created' => 0,
            'skipped' => 0,
            'invalid' => 1,
            'errors' => [['row' => $row, 'reason' => $reason]],
        ];
    }
}
