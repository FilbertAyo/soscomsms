<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\ContactImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ImportContactsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @var array{created: int, skipped: int, invalid: int, errors: list<array{row: int, reason: string}>}|null
     */
    public ?array $result = null;

    public function __construct(
        public int $customerId,
        public string $storedPath,
        public string $nameColumn,
        public string $phoneColumn,
        public ?int $contactGroupId = null,
        public ?string $groupName = null,
    ) {}

    public function handle(ContactImporter $importer): void
    {
        $customer = Customer::query()->find($this->customerId);
        if ($customer === null) {
            $this->deleteStoredFile();

            return;
        }

        $path = Storage::path($this->storedPath);
        $this->result = $importer->importPath(
            $customer,
            $path,
            strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            $this->nameColumn,
            $this->phoneColumn,
            true,
            null,
            null,
            $this->contactGroupId,
            $this->groupName,
        );

        $this->deleteStoredFile();
    }

    private function deleteStoredFile(): void
    {
        Storage::delete($this->storedPath);
    }
}
