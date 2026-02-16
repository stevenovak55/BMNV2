<?php

declare(strict_types=1);

namespace BMN\Extractor\Admin;

use BMN\Extractor\Repository\ExtractionRepository;
use BMN\Extractor\Repository\PropertyRepository;
use BMN\Extractor\Service\ExtractionEngine;

/**
 * WP Admin dashboard page for the BMN Extractor plugin.
 *
 * Provides:
 *   - Property count overview by status
 *   - Last extraction run status
 *   - Manual extraction trigger
 *   - Extraction history table
 */
final class AdminDashboard
{
    private ExtractionEngine $engine;
    private ExtractionRepository $extractions;
    private PropertyRepository $properties;

    public function __construct(
        ExtractionEngine $engine,
        ExtractionRepository $extractions,
        PropertyRepository $properties,
    ) {
        $this->engine = $engine;
        $this->extractions = $extractions;
        $this->properties = $properties;
    }

    /**
     * Register the admin menu page and AJAX handlers.
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_bmn_extraction_status', [$this, 'ajaxStatus']);
        add_action('wp_ajax_bmn_extraction_trigger', [$this, 'ajaxTrigger']);
        add_action('wp_ajax_bmn_extraction_history', [$this, 'ajaxHistory']);
    }

    /**
     * Add the admin menu page.
     */
    public function addMenuPage(): void
    {
        add_menu_page(
            'BMN Extractor',
            'BMN Extractor',
            'manage_options',
            'bmn-extractor',
            [$this, 'renderDashboard'],
            'dashicons-download',
            80
        );
    }

    /**
     * Render the dashboard page.
     */
    public function renderDashboard(): void
    {
        $statusCounts = $this->properties->countByStatus();
        $totalProperties = array_sum($statusCounts);
        $lastRun = $this->extractions->getLastRun();
        $isRunning = $this->extractions->isRunning();
        $recentRuns = $this->extractions->getHistory(10);

        include __DIR__ . '/views/dashboard.php';
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueueAssets(string $hookSuffix): void
    {
        if (strpos($hookSuffix, 'bmn-extractor') === false) {
            return;
        }

        wp_enqueue_script(
            'bmn-extractor-admin',
            BMN_EXTRACTOR_URL . 'assets/js/admin.js',
            ['jquery'],
            BMN_EXTRACTOR_VERSION,
            true
        );

        wp_localize_script('bmn-extractor-admin', 'bmnExtractor', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bmn_extractor_nonce'),
        ]);
    }

    /**
     * AJAX: Get current extraction status.
     */
    public function ajaxStatus(): void
    {
        check_ajax_referer('bmn_extractor_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
            return;
        }

        $lastRun = $this->extractions->getLastRun();
        $isRunning = $this->extractions->isRunning();
        $statusCounts = $this->properties->countByStatus();

        wp_send_json_success([
            'is_running' => $isRunning,
            'total_properties' => array_sum($statusCounts),
            'by_status' => $statusCounts,
            'last_run' => $lastRun ? [
                'status' => $lastRun->status,
                'listings_processed' => (int) $lastRun->listings_processed,
                'started_at' => $lastRun->started_at,
                'completed_at' => $lastRun->completed_at,
            ] : null,
        ]);
    }

    /**
     * AJAX: Trigger a manual extraction.
     */
    public function ajaxTrigger(): void
    {
        check_ajax_referer('bmn_extractor_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
            return;
        }

        try {
            $result = $this->engine->run(isResync: false, triggeredBy: 'manual');
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Get extraction history.
     */
    public function ajaxHistory(): void
    {
        check_ajax_referer('bmn_extractor_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
            return;
        }

        $runs = $this->extractions->getHistory(20);
        wp_send_json_success($runs);
    }
}
