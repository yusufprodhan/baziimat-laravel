<?php

namespace App\Traits;

use Exception;

/**
 * TnPaySignature Trait
 * 
 * Handles signature generation for TechnoNextPay payment gateway integration
 * Implements HTTP signature using HMAC-SHA256 and SHA-256 digest
 */
trait TnPaySignature
{
    /**
     * Computes a SHA-256 digest of the input data and returns it in the format "SHA-256=<base64>"
     * This mirrors the Java implementation's behavior
     *
     * @param string $data The input string to hash
     * @return string The formatted digest string
     * @throws Exception If hash computation fails
     */
    protected function computeDigest(string $data): string
    {
        try {
            // Compute SHA-256 hash
            $hashBytes = hash('sha256', $data, true);
            
            if ($hashBytes === false) {
                throw new Exception('SHA-256 hash computation failed');
            }
            
            // Convert hash to base64
            $base64Hash = base64_encode($hashBytes);
            
            // Return formatted result
            return sprintf('SHA-256=%s', $base64Hash);
        } catch (Exception $error) {
            logger()->error("SHA-256 algorithm not available: " . $error->getMessage());
            throw new Exception("Required cryptographic algorithm not available");
        }
    }

    /**
     * Computes HTTP signature using HMAC-SHA256
     *
     * @param array $headersToSign Array of header names to sign
     * @param array $signatureComponents Map of header names to values
     * @param string $password Secret key for HMAC
     * @return string The base64-encoded signature
     * @throws Exception If signature computation fails
     */
    protected function computeHttpSignature(
        array $headersToSign,
        array $signatureComponents,
        string $password
    ): string {
        try {
            $signingBase = $this->buildSigningBase($headersToSign, $signatureComponents);
            $hmacSignature = $this->generateHmacSignature($signingBase, $password);

            return $hmacSignature;
        } catch (Exception $error) {
            logger()->error("Failed to compute HTTP signature: " . $error->getMessage());
            throw new Exception("Failed to generate message signature.");
        }
    }

    /**
     * Generates HMAC-SHA256 signature
     *
     * @param string $signingBase The base string to sign
     * @param string $secretKey Secret key for HMAC
     * @return string The base64-encoded signature
     * @throws Exception If signature generation fails
     */
    protected function generateHmacSignature(string $signingBase, string $secretKey): string
    {
        try {
            // Generate HMAC-SHA256 signature
            $signatureBytes = hash_hmac('sha256', $signingBase, $secretKey, true);
            
            if ($signatureBytes === false) {
                throw new Exception('HMAC-SHA256 signature generation failed');
            }
            
            // Convert to base64
            $base64Signature = base64_encode($signatureBytes);
            
            return $base64Signature;
        } catch (Exception $error) {
            logger()->error("HMAC-SHA256 generation failed: " . $error->getMessage());
            throw new Exception("Failed to generate HMAC-SHA256 signature");
        }
    }

    /**
     * Builds the signing base string from headers
     * Format: "header1: value1\nheader2: value2"
     *
     * @param array $headersToSign Array of header names
     * @param array $signatureComponents Map of header names to values
     * @return string The signing base string
     */
    protected function buildSigningBase(array $headersToSign, array $signatureComponents): string
    {
        $parts = [];
        
        foreach ($headersToSign as $header) {
            $headerKey = $header; // Keep the case from the array
            if (isset($signatureComponents[$headerKey])) {
                $parts[] = strtolower($header) . ': ' . $signatureComponents[$headerKey];
            }
        }
        
        return implode("\n", $parts);
    }

    /**
     * Generates RFC 1123 formatted date string (GMT)
     * Format: "Day, DD Mon YYYY HH:MM:SS GMT"
     *
     * @return string The formatted RFC 1123 date string
     */
    protected function generateRfc1123Date(): string
    {
        return gmdate('D, d M Y H:i:s \G\M\T');
    }

    /**
     * Generates complete signature with all components for TnPay
     *
     * @param string $payload The request body (JSON)
     * @param string $apiKey API key
     * @param string $merchantId Merchant ID
     * @param string $password API secret (signing key)
     * @return array Array containing signature and all components
     * @throws Exception If signature generation fails
     */
    protected function generateTnPaySignature(
        string $payload,
        string $apiKey,
        string $merchantId,
        string $password
    ): array {
        try {
            // Generate digest from payload
            $generatedDigest = $this->computeDigest($payload);

            // Define headers to sign in order
            $headersToSign = [
                "timestamp",
                "host",
                "target",
                "merchant-id",
                "api-key",
                "digest",
            ];

            // Generate components
            $timestamp = $this->generateRfc1123Date();
            $host = "http://192.168.169.162";
            $target = "POST /p/service/api/payment/processing/payment-order";

            // Build signature components
            $signatureComponents = [
                "timestamp" => $timestamp,
                "host" => $host,
                "target" => $target,
                "merchant-id" => $merchantId,
                "api-key" => $apiKey,
                "digest" => $generatedDigest,
            ];

            // Generate signature
            $signature = $this->computeHttpSignature(
                $headersToSign,
                $signatureComponents,
                $password
            );

            // Return the signature and other components for use in headers
            return [
                "host" => $host,
                "target" => $target,
                "timestamp" => $timestamp,
                "digest" => $generatedDigest,
                "signature" => $signature,
                "merchant_id" => $merchantId,
                "api_key" => $apiKey,
            ];
        } catch (Exception $error) {
            logger()->error("generateTnPaySignature failed: " . $error->getMessage());
            throw $error;
        }
    }
}
