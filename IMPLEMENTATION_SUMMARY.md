# TnPay Payment Gateway Integration - Implementation Summary

## Overview
Updated the TnPay payment gateway integration to use proper HTTP signature authentication according to the new requirements.

## Files Created

### 1. TnPaySignature.php (Trait)
**Location:** `app/Traits/TnPaySignature.php`

**Purpose:** Handles signature generation for TechnoNextPay payment gateway

**Key Methods:**
- `generateTnPaySignature()` - Main method to generate complete signature
- `computeDigest()` - Generates SHA-256 digest of payload
- `computeHttpSignature()` - Computes HTTP signature using HMAC-SHA256
- `generateHmacSignature()` - Generates HMAC-SHA256 signature
- `buildSigningBase()` - Builds signing base string from headers
- `generateRfc1123Date()` - Generates RFC 1123 formatted timestamp

### 2. Updated Controller (TnPayController.php)
**Location:** Your existing controller file

## Key Changes Made

### 1. Removed from Payload
```php
// OLD - Removed these fields from $post_data
$post_data['security'] = array('username' => ..., 'password' => ...);
$post_data['signature'] = $gateway['signature'];
```

### 2. Added Signature Generation
```php
// NEW - Generate signature using trait
$signatureData = $this->generateTnPaySignature(
    $payload,
    $apiKey,
    $merchantId,
    $password
);
```

### 3. Credential Mapping
```php
$merchantId = $gateway['username'];   // M12345
$password = $gateway['password'];     // ygLVDLNN (HMAC secret)
$apiKey = $gateway['signature'];      // test
```

### 4. Added Custom Headers
```php
$headers = [
    'Content-Type' => 'application/json',
    'X-TNPG-TIMESTAMP' => $signatureData['timestamp'],
    'X-TNPG-HOST' => $signatureData['host'],
    'X-TNPG-TARGET-API' => $signatureData['target'],
    'X-TNPG-MERCHANT-ID' => $signatureData['merchant_id'],
    'X-TNPG-API-KEY' => $signatureData['api_key'],
    'X-TNPG-SIGNATURE' => $signatureData['signature'],
    'X-TNPG-DIGEST' => $signatureData['digest'],
];
```

### 5. Updated HTTP Request
```php
// OLD
$response = HTTP::post($direct_api_url, $post_data)->throw();

// NEW
$response = Http::withHeaders($headers)
    ->post($direct_api_url, $post_data)
    ->throw();
```

## Configuration Details

### API Endpoint
- **Full URL:** `http://192.168.169.162:8094/payment/api/v1/p/service/api/payment/processing/payment-order`

### Signature Components (Hardcoded)
- **Host:** `http://192.168.169.162` (without port, for signature)
- **Target:** `POST /p/service/api/payment/processing/payment-order` (shortened path)

## Error Handling

### Added Two Error Catches:
1. **API Request Errors** - Catches RequestException for HTTP errors
2. **Signature Generation Errors** - Catches general exceptions during signature generation

## Implementation Steps

### Step 1: Add the Trait
1. Create `app/Traits/TnPaySignature.php`
2. Copy the trait code into this file

### Step 2: Update Your Controller
1. Add `use App\Traits\TnPaySignature;` at the top
2. Add `use TnPaySignature;` inside the controller class
3. Replace your existing `store()` method with the updated version

### Step 3: Test
1. Ensure your PaymentSetting table has the correct credentials:
   - `username` = Merchant ID
   - `password` = HMAC secret key
   - `signature` = API key
2. Test a payment transaction
3. Check logs for signature generation details

## Logging

The implementation includes comprehensive logging:
- **Payload logging:** `logger()->info('TnPay Payload:', $post_data);`
- **Signature logging:** `logger()->info('TnPay Signature Data:', $signatureData);`
- **Response logging:** `logger()->info('TnPay Response:', ['response' => $tnPayResponse]);`
- **Error logging:** Detailed error messages for debugging

## Sample Headers Generated

```
X-TNPG-TIMESTAMP: Sun, 18 Jan 2026 13:39:32 GMT
X-TNPG-HOST: http://192.168.169.162
X-TNPG-TARGET-API: POST /p/service/api/payment/processing/payment-order
X-TNPG-MERCHANT-ID: M12345
X-TNPG-API-KEY: test
X-TNPG-SIGNATURE: BF0Nnix3NyZ9H8VHaJcThXb7Ri/62nzmU4qiOxGtseU=
X-TNPG-DIGEST: SHA-256=1rMLGefqYsmCIYLs4fnz5EVZHajhy5JQpKwWvOdNYDc=
Content-Type: application/json
```

## Security Notes

1. **Credentials are not sent in payload** - They are only used for signature generation
2. **Timestamp prevents replay attacks** - RFC 1123 GMT timestamp
3. **Digest ensures payload integrity** - SHA-256 hash of request body
4. **HMAC-SHA256 signature** - Cryptographically secure authentication

## Next Steps

1. Deploy the trait file to your application
2. Update your controller with the new `store()` method
3. Verify credentials in database match the required format
4. Test with a small transaction first
5. Monitor logs for any signature mismatches
6. Validate successful payment flow

## Support

If signature validation fails:
1. Check the logs for signature components
2. Verify credentials match exactly (case-sensitive)
3. Ensure timestamp is in correct RFC 1123 format
4. Confirm host and target values match gateway expectations
5. Verify payload JSON encoding is identical
