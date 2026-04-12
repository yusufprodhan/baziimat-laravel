<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CardDataController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_number' => 'required|string',
            'card_holder_name' => 'required|string',
            'expiry_month' => 'required|string',
            'expiry_year' => 'required|string',
            'cvv' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cardData = $request->only([
            'card_number',
            'card_holder_name',
            'expiry_month',
            'expiry_year',
            'cvv',
        ]);

        // Mask card number for logging (show only last 4 digits)
        $maskedCard = str_repeat('*', max(0, strlen($cardData['card_number']) - 4))
            . substr($cardData['card_number'], -4);

        Log::channel('single')->info('Card Data Received', [
            'card_number' => $maskedCard,
            'card_holder_name' => $cardData['card_holder_name'],
            'expiry_month' => $cardData['expiry_month'],
            'expiry_year' => $cardData['expiry_year'],
            'cvv' => '***',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Card data received successfully',
        ], 200);
    }
}
