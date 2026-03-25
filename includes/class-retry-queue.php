<?php
/**
 * Retry queue for failed CRM API calls.
 *
 * Stores failed requests in wp_options and retries with exponential backoff.
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Retry_Queue {

    private const OPTION_KEY    = 'vincocrm_retry_queue';
    private const MAX_ATTEMPTS  = 5;
    private const MAX_QUEUE     = 200;
    private const BASE_DELAY    = 60; // seconds

    private Api_Client $api;

    public function __construct( Api_Client $api ) {
        $this->api = $api;
    }

    /**
     * Register cron hook.
     */
    public function init(): void {
        add_action( 'vincocrm_retry_queue', [ $this, 'process' ] );
    }

    /**
     * Add a failed request to the retry queue.
     *
     * @param string $method   HTTP method.
     * @param string $endpoint API endpoint.
     * @param array  $body     Request body.
     * @param string $type     Label for logging (e.g. "contact_sync", "order_sync").
     */
    public function enqueue( string $method, string $endpoint, array $body, string $type = 'generic' ): void {
        $queue = $this->get_queue();

        if ( count( $queue ) >= self::MAX_QUEUE ) {
            // Drop oldest entries to prevent bloat.
            $queue = array_slice( $queue, -( self::MAX_QUEUE - 1 ) );
        }

        $queue[] = [
            'method'   => $method,
            'endpoint' => $endpoint,
            'body'     => $body,
            'type'     => $type,
            'attempts' => 0,
            'next_at'  => time(),
            'added_at' => time(),
        ];

        $this->save_queue( $queue );
    }

    /**
     * Process the retry queue (called by wp_cron).
     */
    public function process(): void {
        if ( ! $this->api->is_connected() ) {
            return;
        }

        $queue   = $this->get_queue();
        $now     = time();
        $updated = false;
        $remove  = [];

        foreach ( $queue as $index => &$item ) {
            if ( $item['next_at'] > $now ) {
                continue;
            }

            $item['attempts']++;

            $result = $this->execute_item( $item );

            if ( $result['success'] ) {
                $remove[] = $index;
                $updated  = true;
                continue;
            }

            // Permanent failure codes — don't retry.
            $status = $result['status'] ?? 0;
            if ( in_array( $status, [ 400, 403, 404, 409, 422 ], true ) ) {
                $remove[] = $index;
                $updated  = true;
                continue;
            }

            if ( $item['attempts'] >= self::MAX_ATTEMPTS ) {
                $remove[] = $index;
                $updated  = true;

                if ( WP_DEBUG ) {
                    error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                        '[VincoCRM] Retry queue: Dropped %s after %d attempts. Last error: %s',
                        $item['type'],
                        $item['attempts'],
                        $result['error'] ?? 'unknown'
                    ) );
                }
                continue;
            }

            // Exponential backoff: 1min, 4min, 16min, 64min...
            $delay             = self::BASE_DELAY * pow( 4, $item['attempts'] - 1 );
            $item['next_at']   = $now + (int) min( $delay, 3600 ); // cap at 1 hour
            $updated           = true;
        }
        unset( $item );

        if ( ! empty( $remove ) ) {
            foreach ( $remove as $index ) {
                unset( $queue[ $index ] );
            }
            $queue = array_values( $queue );
        }

        if ( $updated ) {
            $this->save_queue( $queue );
        }
    }

    /**
     * Execute a queued item.
     *
     * @param array $item Queue item.
     * @return array{success: bool, error?: string, status?: int}
     */
    private function execute_item( array $item ): array {
        return match ( strtoupper( $item['method'] ) ) {
            'POST'   => $this->api->post( $item['endpoint'], $item['body'] ),
            'PUT'    => $this->api->put( $item['endpoint'], $item['body'] ),
            'DELETE' => $this->api->delete( $item['endpoint'] ),
            default  => $this->api->get( $item['endpoint'] ),
        };
    }

    /**
     * Get the current queue.
     *
     * @return array<int, array>
     */
    private function get_queue(): array {
        $queue = get_option( self::OPTION_KEY, [] );
        return is_array( $queue ) ? $queue : [];
    }

    /**
     * Save the queue.
     *
     * @param array $queue
     */
    private function save_queue( array $queue ): void {
        update_option( self::OPTION_KEY, $queue, false );
    }

    /**
     * Get queue count (for admin display).
     */
    public function count(): int {
        return count( $this->get_queue() );
    }

    /**
     * Clear the entire queue.
     */
    public function clear(): void {
        $this->save_queue( [] );
    }
}
