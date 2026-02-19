<?php
/**
 * One-time backfill: populate new columns and rooms from extra_data JSON.
 *
 * Usage: docker exec bmn-v2-wordpress wp eval-file /var/www/html/wp-content/plugins/bmn-extractor/backfill-columns.php --allow-root
 */

global $wpdb;

$normalizer = new BMN\Extractor\Service\DataNormalizer();
$roomsTable = $wpdb->prefix . 'bmn_rooms';
$propsTable = $wpdb->prefix . 'bmn_properties';

$batch_size = 100;
$offset = 0;
$total_updated = 0;
$total_rooms = 0;
$lead_set = 0;
$title5_set = 0;
$disc_set = 0;
$dogs_set = 0;
$cats_set = 0;

while (true) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, listing_key, extra_data FROM {$propsTable} WHERE extra_data IS NOT NULL AND extra_data != '' ORDER BY id LIMIT %d OFFSET %d",
        $batch_size,
        $offset
    ));

    if (empty($rows)) {
        break;
    }

    foreach ($rows as $row) {
        $apiData = json_decode($row->extra_data, true);
        if (!is_array($apiData)) {
            continue;
        }

        // Lead paint parsing.
        $leadPaint = null;
        $lp = $apiData['MLSPIN_LEAD_PAINT'] ?? null;
        if ($lp !== null && !(is_array($lp) && empty($lp))) {
            if (is_string($lp)) {
                $lp = [$lp];
            }
            if (is_array($lp) && !empty($lp)) {
                $h = implode(' ', $lp);
                if (stripos($h, 'Yes') !== false && stripos($h, 'Certified Treated') === false) {
                    $leadPaint = 1;
                } elseif (stripos($h, 'None') !== false || stripos($h, 'Certified Treated') !== false) {
                    $leadPaint = 0;
                }
            }
        }

        // Title5.
        $title5 = $apiData['MLSPIN_TITLE5'] ?? null;
        if (is_string($title5)) {
            $title5 = trim($title5);
        }
        if ($title5 === '' || $title5 === null) {
            $title5 = null;
        }

        // Disclosures.
        $disclosures = $apiData['Disclosures'] ?? null;
        if (is_array($disclosures)) {
            $disclosures = empty($disclosures) ? null : json_encode($disclosures, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if ($disclosures === '') {
            $disclosures = null;
        }

        // Pet parsing.
        $pets = $apiData['PetsAllowed'] ?? null;
        $dogs = null;
        $cats = null;
        if ($pets !== null) {
            if (is_string($pets)) {
                $pets = array_map('trim', explode(',', $pets));
            }
            if (is_array($pets) && !empty($pets)) {
                $ph = implode(' ', $pets);
                if (stripos($ph, 'No Pets') !== false || $ph === 'No') {
                    $dogs = 0;
                    $cats = 0;
                } else {
                    $dogs = stripos($ph, 'Dogs') !== false ? 1 : 0;
                    $cats = stripos($ph, 'Cats') !== false ? 1 : 0;
                    if (stripos($ph, 'Yes') !== false) {
                        $dogs = 1;
                        $cats = 1;
                    }
                }
            }
        }

        // Update columns.
        $updates = [];
        $update_vals = [];
        if ($leadPaint !== null) {
            $updates[] = 'lead_paint = %d';
            $update_vals[] = $leadPaint;
            $lead_set++;
        }
        if ($title5 !== null) {
            $updates[] = 'title5 = %s';
            $update_vals[] = $title5;
            $title5_set++;
        }
        if ($disclosures !== null) {
            $updates[] = 'disclosures = %s';
            $update_vals[] = $disclosures;
            $disc_set++;
        }
        if ($dogs !== null) {
            $updates[] = 'pets_dogs_allowed = %d';
            $update_vals[] = $dogs;
            $dogs_set++;
        }
        if ($cats !== null) {
            $updates[] = 'pets_cats_allowed = %d';
            $update_vals[] = $cats;
            $cats_set++;
        }

        if (!empty($updates)) {
            $update_vals[] = $row->id;
            $sql = "UPDATE {$propsTable} SET " . implode(', ', $updates) . ' WHERE id = %d';
            $wpdb->query($wpdb->prepare($sql, ...$update_vals));
            $total_updated++;
        }

        // Process rooms.
        $roomRows = $normalizer->normalizeRooms($apiData, $row->listing_key);
        if (!empty($roomRows)) {
            $wpdb->delete($roomsTable, ['listing_key' => $row->listing_key]);
            foreach ($roomRows as $rr) {
                $wpdb->insert($roomsTable, $rr);
                $total_rooms++;
            }
        }
    }

    $offset += $batch_size;
    if ($offset % 500 === 0) {
        WP_CLI::log("Processed {$offset} rows...");
    }
}

WP_CLI::success("Backfill complete!");
WP_CLI::log("Properties updated: {$total_updated}");
WP_CLI::log("Lead paint set: {$lead_set}");
WP_CLI::log("Title5 set: {$title5_set}");
WP_CLI::log("Disclosures set: {$disc_set}");
WP_CLI::log("Dogs allowed set: {$dogs_set}");
WP_CLI::log("Cats allowed set: {$cats_set}");
WP_CLI::log("Room records created: {$total_rooms}");
