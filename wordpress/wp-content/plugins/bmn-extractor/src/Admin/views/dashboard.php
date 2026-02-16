<?php
/**
 * BMN Extractor Dashboard Template.
 *
 * @var array  $statusCounts    Property counts by status.
 * @var int    $totalProperties  Total property count.
 * @var object|null $lastRun     Most recent extraction run.
 * @var bool   $isRunning        Whether an extraction is currently running.
 * @var array  $recentRuns       Recent extraction runs.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>BMN Extractor Dashboard</h1>

    <!-- Status Cards -->
    <div id="bmn-status-cards" style="display: flex; gap: 16px; margin: 20px 0; flex-wrap: wrap;">
        <div class="card" style="flex: 1; min-width: 200px; padding: 16px;">
            <h3>Total Properties</h3>
            <p style="font-size: 2em; font-weight: bold; margin: 0;">
                <?php echo esc_html(number_format($totalProperties)); ?>
            </p>
        </div>

        <div class="card" style="flex: 1; min-width: 200px; padding: 16px;">
            <h3>Active Listings</h3>
            <p style="font-size: 2em; font-weight: bold; margin: 0; color: #0073aa;">
                <?php echo esc_html(number_format($statusCounts['Active'] ?? 0)); ?>
            </p>
        </div>

        <div class="card" style="flex: 1; min-width: 200px; padding: 16px;">
            <h3>Extraction Status</h3>
            <p style="font-size: 1.2em; margin: 0;">
                <?php if ($isRunning): ?>
                    <span style="color: #d63638;">&#9679; Running</span>
                <?php elseif ($lastRun && $lastRun->status === 'completed'): ?>
                    <span style="color: #00a32a;">&#9679; Idle</span>
                <?php elseif ($lastRun && $lastRun->status === 'paused'): ?>
                    <span style="color: #dba617;">&#9679; Paused</span>
                <?php else: ?>
                    <span style="color: #8c8f94;">&#9679; Unknown</span>
                <?php endif; ?>
            </p>
            <?php if ($lastRun): ?>
                <p style="font-size: 0.9em; color: #666; margin: 4px 0 0;">
                    Last run: <?php echo esc_html($lastRun->started_at ?? 'N/A'); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="card" style="flex: 1; min-width: 200px; padding: 16px;">
            <h3>Last Run Results</h3>
            <?php if ($lastRun): ?>
                <p style="margin: 0; font-size: 0.95em;">
                    Processed: <?php echo esc_html(number_format((int) $lastRun->listings_processed)); ?><br>
                    Created: <?php echo esc_html(number_format((int) $lastRun->listings_created)); ?><br>
                    Updated: <?php echo esc_html(number_format((int) $lastRun->listings_updated)); ?><br>
                    Errors: <?php echo esc_html(number_format((int) $lastRun->errors_count)); ?>
                </p>
            <?php else: ?>
                <p style="color: #666;">No extractions yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Controls -->
    <div style="margin: 20px 0;">
        <button id="bmn-trigger-extraction" class="button button-primary" <?php echo $isRunning ? 'disabled' : ''; ?>>
            Run Incremental Extraction
        </button>
        <button id="bmn-trigger-resync" class="button" <?php echo $isRunning ? 'disabled' : ''; ?>>
            Run Full Resync
        </button>
        <button id="bmn-refresh-status" class="button">
            Refresh Status
        </button>
    </div>

    <!-- Status Breakdown -->
    <div class="card" style="padding: 16px; margin: 20px 0;">
        <h3>Properties by Status</h3>
        <table class="widefat" style="max-width: 500px;">
            <thead>
                <tr>
                    <th>Status</th>
                    <th style="text-align: right;">Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statusCounts as $status => $count): ?>
                    <tr>
                        <td><?php echo esc_html($status ?: 'Unknown'); ?></td>
                        <td style="text-align: right;"><?php echo esc_html(number_format($count)); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($statusCounts)): ?>
                    <tr>
                        <td colspan="2" style="text-align: center; color: #666;">No properties yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Extraction History -->
    <div class="card" style="padding: 16px; margin: 20px 0;">
        <h3>Recent Extractions</h3>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Triggered By</th>
                    <th>Status</th>
                    <th>Processed</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Errors</th>
                    <th>Started</th>
                    <th>Completed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRuns as $run): ?>
                    <tr>
                        <td><?php echo esc_html($run->id); ?></td>
                        <td><?php echo esc_html($run->extraction_type); ?></td>
                        <td><?php echo esc_html($run->triggered_by); ?></td>
                        <td>
                            <?php
                            $statusColor = match($run->status) {
                                'completed' => '#00a32a',
                                'running' => '#d63638',
                                'paused' => '#dba617',
                                'failed' => '#d63638',
                                default => '#8c8f94',
                            };
                            ?>
                            <span style="color: <?php echo esc_attr($statusColor); ?>;">
                                <?php echo esc_html(ucfirst($run->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(number_format((int) $run->listings_processed)); ?></td>
                        <td><?php echo esc_html(number_format((int) $run->listings_created)); ?></td>
                        <td><?php echo esc_html(number_format((int) $run->listings_updated)); ?></td>
                        <td><?php echo esc_html(number_format((int) $run->errors_count)); ?></td>
                        <td><?php echo esc_html($run->started_at ?? ''); ?></td>
                        <td><?php echo esc_html($run->completed_at ?? '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentRuns)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: #666;">No extractions yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    var nonce = '<?php echo esc_js(wp_create_nonce('bmn_extractor_nonce')); ?>';

    document.getElementById('bmn-trigger-extraction')?.addEventListener('click', function() {
        if (!confirm('Start incremental extraction?')) return;
        this.disabled = true;
        this.textContent = 'Running...';
        fetch(ajaxUrl + '?action=bmn_extraction_trigger&nonce=' + nonce, { method: 'POST' })
            .then(r => r.json())
            .then(d => { alert(d.success ? 'Extraction started!' : 'Error: ' + d.data); location.reload(); })
            .catch(e => { alert('Error: ' + e.message); location.reload(); });
    });

    document.getElementById('bmn-trigger-resync')?.addEventListener('click', function() {
        if (!confirm('Start full resync? This may take a while.')) return;
        this.disabled = true;
        this.textContent = 'Running...';
        fetch(ajaxUrl + '?action=bmn_extraction_trigger&nonce=' + nonce + '&type=full', { method: 'POST' })
            .then(r => r.json())
            .then(d => { alert(d.success ? 'Resync started!' : 'Error: ' + d.data); location.reload(); })
            .catch(e => { alert('Error: ' + e.message); location.reload(); });
    });

    document.getElementById('bmn-refresh-status')?.addEventListener('click', function() {
        location.reload();
    });
});
</script>
