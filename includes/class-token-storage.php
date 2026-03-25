<?php
/**
 * Encrypted token storage using AES-256-CBC with wp_salt.
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Token_Storage {

    private const OPTION_KEY = 'vincocrm_encrypted_tokens';
    private const CIPHER     = 'aes-256-cbc';

    /**
     * Get the encryption key derived from wp_salt.
     */
    private function get_key(): string {
        return hash( 'sha256', wp_salt( 'auth' ), true );
    }

    /**
     * Encrypt a value.
     */
    private function encrypt( string $plaintext ): string {
        $key = $this->get_key();
        $iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::CIPHER ) );

        $ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
        if ( false === $ciphertext ) {
            return '';
        }

        // base64( iv + ciphertext )
        return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    }

    /**
     * Decrypt a value.
     */
    private function decrypt( string $encrypted ): string {
        $key  = $this->get_key();
        $data = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

        if ( false === $data ) {
            return '';
        }

        $iv_length  = openssl_cipher_iv_length( self::CIPHER );
        $iv         = substr( $data, 0, $iv_length );
        $ciphertext = substr( $data, $iv_length );

        $plaintext = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

        return false === $plaintext ? '' : $plaintext;
    }

    /**
     * Store tokens (encrypted).
     *
     * @param array{access_token: string, refresh_token: string} $tokens
     */
    public function save( array $tokens ): void {
        $payload = wp_json_encode( $tokens );
        update_option( self::OPTION_KEY, $this->encrypt( $payload ) );
    }

    /**
     * Get decrypted tokens.
     *
     * @return array{access_token: string, refresh_token: string}|null
     */
    public function get(): ?array {
        $encrypted = get_option( self::OPTION_KEY, '' );
        if ( empty( $encrypted ) ) {
            return null;
        }

        $json = $this->decrypt( $encrypted );
        if ( empty( $json ) ) {
            return null;
        }

        $tokens = json_decode( $json, true );
        if ( ! is_array( $tokens ) || empty( $tokens['access_token'] ) ) {
            return null;
        }

        return $tokens;
    }

    /**
     * Get just the access token.
     */
    public function get_access_token(): string {
        $tokens = $this->get();
        return $tokens['access_token'] ?? '';
    }

    /**
     * Get just the refresh token.
     */
    public function get_refresh_token(): string {
        $tokens = $this->get();
        return $tokens['refresh_token'] ?? '';
    }

    /**
     * Check if tokens exist.
     */
    public function has_tokens(): bool {
        return null !== $this->get();
    }

    /**
     * Clear stored tokens.
     */
    public function clear(): void {
        delete_option( self::OPTION_KEY );
    }

    /**
     * Update just the access token (after refresh).
     */
    public function update_access_token( string $access_token ): void {
        $tokens = $this->get();
        if ( null === $tokens ) {
            return;
        }
        $tokens['access_token'] = $access_token;
        $this->save( $tokens );
    }
}
