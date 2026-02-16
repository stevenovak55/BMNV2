<?php

declare(strict_types=1);

namespace BMN\Extractor\Repository;

use BMN\Platform\Database\Repository;

class PropertyHistoryRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_property_history';
    }

    protected bool $timestamps = false; // Only has created_at, no updated_at

    /**
     * Log a change for a property.
     */
    public function logChange(string $listingKey, string $changeType, string $fieldName, ?string $oldValue, ?string $newValue): int|false
    {
        return $this->wpdb->insert($this->table, [
            'listing_key' => $listingKey,
            'change_type' => $changeType,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
        ]) ? (int) $this->wpdb->insert_id : false;
    }

    /**
     * Log multiple changes from detectChanges output.
     */
    public function logChanges(string $listingKey, array $changes): int
    {
        $logged = 0;
        foreach ($changes as $change) {
            $changeType = match ($change['field']) {
                'list_price', 'original_list_price', 'close_price' => 'price_change',
                'standard_status', 'mls_status' => 'status_change',
                default => 'field_change',
            };

            $result = $this->logChange(
                $listingKey,
                $changeType,
                $change['field'],
                $change['old_value'] !== null ? (string) $change['old_value'] : null,
                $change['new_value'] !== null ? (string) $change['new_value'] : null
            );

            if ($result !== false) {
                $logged++;
            }
        }
        return $logged;
    }

    /**
     * Get history for a listing.
     */
    public function getForListing(string $listingKey, ?string $changeType = null): array
    {
        if ($changeType !== null) {
            return $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE listing_key = %s AND change_type = %s ORDER BY changed_at DESC",
                    $listingKey,
                    $changeType
                )
            ) ?? [];
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE listing_key = %s ORDER BY changed_at DESC",
                $listingKey
            )
        ) ?? [];
    }
}
