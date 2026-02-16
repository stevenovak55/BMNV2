<?php

declare(strict_types=1);

namespace BMN\Extractor\Repository;

use BMN\Platform\Database\Repository;

class OfficeRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_offices';
    }

    public function findByMlsId(string $officeMlsId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE office_mls_id = %s LIMIT 1",
                $officeMlsId
            )
        );
        return $result ?: null;
    }

    /**
     * Upsert office by office_mls_id.
     */
    public function upsert(array $data): void
    {
        $existing = $this->findByMlsId($data['office_mls_id']);
        if ($existing) {
            $this->update($existing->id, $data);
        } else {
            $this->create($data);
        }
    }
}
