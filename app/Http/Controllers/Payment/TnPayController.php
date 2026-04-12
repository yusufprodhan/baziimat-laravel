<?php

namespace App\Http\Controllers\Payment;

use App\Helpers\EmailHelper;
use App\Helpers\PriceHelper;
use App\Helpers\SmsHelper;
use App\Http\Controllers\Controller;
use App\Jobs\EmailSendJob;
use App\Models\Currency;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PaymentSetting;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Models\ShippingService;
use App\Models\State;
use App\Models\TrackOrder;
use App\Traits\TnPaySignature;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Razorpay\Api\Card;
use Stripe\Price;

class TnPayController extends Controller
{
    use TnPaySignature;

    public function store(Request $request)
    {
        $state = State::whereStatus(1)->count() != 0 ? 'required' : '';
        $shipping = ShippingService::whereStatus(1)->count() == 0 || PriceHelper::CheckDigital() == true ? 'required' : '';

        if ($request->single_page_checkout == 1) {
            $request->validate([
                'state_id' => $state,
                'shipping_id' => $shipping,
                'bill_first_name' => 'required',
                'bill_last_name' => 'required',
                'bill_email' => 'required',
                'bill_phone' => 'required',
                'bill_address' => 'required',
                'bill_city' => 'required',
                'bill_zip' => 'required',
            ]);
        } else {
            $request->validate([
                'state_id' => $state,
                'shipping_id' => $shipping,
            ]);
        }

        PriceHelper::checkCheckout($request);

        if (Session::has('currency')) {
            $currency = Currency::findOrFail(Session::get('currency'));
        } else {
            $currency = Currency::where('is_default', 1)->first();
        }

        $supported = ['BDT'];
        if (!in_array($currency->name, $supported)) {
            Session::flash('error', __('Currency Not Supported'));
            return redirect()->back();
        }

        $user = Auth::user();
        $setting = Setting::first();
        $cart = Session::get('cart');
        $total_tax = 0;
        $cart_total = 0;
        $total = 0;
        $option_price = 0;

        foreach ($cart as $key => $item) {
            $total += $item['main_price'] * $item['qty'];
            $option_price += $item['attribute_price'];
            $cart_total = $total + $option_price;
            $item_name = $item['name'];
            $item_category = $item['type'];
            $item_type = $item['slug'];
            $item_profile = $item['item_type'];
            $item_qty = $item['qty'];

            $item = Item::findOrFail($key);
            if ($item->tax) {
                $total_tax += $item::taxCalculate($item);
            }
        }

        if (!PriceHelper::Digital($cart)) {
            $shipping = null;
        } else {
            $shipping = ShippingService::findOrFail($request['shipping_id']);
        }

        $discount = [];
        if (Session::has('coupon')) {
            $discount = Session::get('coupon');
        }

        if (!PriceHelper::Digital($cart)) {
            $shipping = null;
        }

        $txnid = "TXN_" . uniqid();
        $orderData['state'] = $request['state_id'] ? json_encode(State::findOrFail($request['state_id']), true) : null;
        $grand_total = ($cart_total + ($shipping ? $shipping->price : 0)) + $total_tax;
        $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
        $grand_total += PriceHelper::StatePrce($request->state_id, $cart_total);
        $total_amount = PriceHelper::setConvertPrice($grand_total);
        $orderData['cart'] = json_encode($cart, true);
        $orderData['discount'] = json_encode($discount, true);
        $orderData['shipping'] = json_encode($shipping, true);
        $orderData['tax'] = $total_tax;
        $orderData['state_price'] = PriceHelper::StatePrce($request['state_id'], $cart_total);
        $orderData['shipping_info'] = json_encode(Session::get('shipping_address'), true);
        $orderData['billing_info'] = json_encode(Session::get('billing_address'), true);
        $orderData['payment_method'] = 'TnPay';
        $orderData['order_status'] = 'Pending';
        $orderData['user_id'] = isset($user) ? $user->id : 0;
        $orderData['transaction_number'] = Str::random(10);
        $orderData['currency_sign'] = PriceHelper::setCurrencySign();
        $orderData['currency_value'] = PriceHelper::setCurrencyValue();
        $orderData['txnid'] = $txnid;
        $order = Order::create($orderData);

        // Get payment gateway credentials
        $data = PaymentSetting::whereUniqueKeyword('tnPay')->first();
        $gateway = $data->convertJsonData();

        // Map gateway credentials to signature parameters
        $merchantId = $gateway['merchantId'];  // Merchant ID
        $password = $gateway['password'];    // HMAC secret key
        $apiKey = $gateway['username'];     // API Key

        // Prepare billing information
        $bill_info = Session::get('billing_address');

        // Prepare post data (payload) - removed security and signature fields
        $post_data = array();
        $post_data['order_id'] = $txnid;
        $post_data['order_information'] = array(
            'payable_amount' => $total_amount,
            'currency_code' => "BDT"
        );
        $post_data['ipn_url'] = route('front.checkout.ipn');
        $post_data['return_url'] = route('front.tnpay.notify');
        // $post_data['success_url'] = route('front.tnpay.notify');
        // $post_data['cancel_url'] = route('front.checkout.cancle');
        // $post_data['failure_url'] = route('front.checkout.cancle');
        $post_data['promotion_information'] = array(
            'preferred_channel' => '',
            'allowed_bin' => ''
        );
        $post_data['discount_detail'] = array(
            'product_amount' => '',
            'discount_amount' => ''
        );
        $post_data['mdf_1'] = 'string';
        $post_data['mdf_2'] = 'string';
        $post_data['mdf_3'] = 'string';
        $post_data['mdf_4'] = 'string';
        $post_data['mdf_5'] = 'string';
        $post_data['mdf_6'] = 'string';
        $post_data['customer_information'] = array(
            'name' => $bill_info['bill_first_name'] . ' ' . $bill_info['bill_last_name'],
            'contact_number' => $bill_info['bill_phone'],
            'email' => $bill_info['bill_email'],
            'address_line1' => $bill_info['bill_address1'],
            'address_line2' => $bill_info['bill_city'] . ' , ' . $bill_info['bill_zip'] . ' ' . $bill_info['bill_country'],
            'city' => $bill_info['bill_city'],
            'state' => $bill_info['bill_city'],
            'postcode' => $bill_info['bill_zip'],
            'country' => $bill_info['bill_country'],
            'fax' => ""
        );
        $post_data['product_information'] = array(
            'product_name' => substr($item_name, 0, 10),
            'product_category' => substr($item_category, 0, 10),
            'product_profile' => substr($item_profile, 0, 10),
            'product_type' => substr($item_profile, 0, 10)
        );
        $post_data['shipment_information'] = array(
            'shipping_method' => 'Courier',
            'num_of_item' => PriceHelper::setConvertPrice($item_qty),
            'weight_of_items' => PriceHelper::setConvertPrice($item_qty),
            'shipping_address' => $bill_info['bill_address1']
        );

        // Convert payload to JSON
        $payload = json_encode($post_data);

        //logger()->info('TnPay Payload:', $post_data);

        try {
            // Generate signature using the trait
            $signatureData = $this->generateTnPaySignature(
                $payload,
                $apiKey,
                $merchantId,
                $password
            );

            //logger()->info('TnPay Signature Data:', $signatureData);

            // Prepare headers with signature components
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

            // API endpoint URL
            //$direct_api_url = "http://10.70.32.110:8084/payment/api/v1/p/service/api/payment/processing/payment-order"; //local
            //$direct_api_url = "http://192.168.169.114:8094/payment/api/v1/p/service/api/payment/processing/payment-order"; // dev
            $direct_api_url = "http://192.168.169.162:8094/payment/api/v1/p/service/api/payment/processing/payment-order"; // stage

            // Make API request with headers
            $response = Http::withHeaders($headers)
                ->post($direct_api_url, $post_data)
                ->throw();

            $tnPayResponse = $response->body();

            logger()->info('TnPay Response:', ['response' => $tnPayResponse]);

        } catch (RequestException $e) {
            $statusCode = $e->response->status();
            $errorBody = $e->response->json();

            logger()->error('TnPay API Error', [
                'error' => $errorBody,
                'status_code' => $statusCode,
            ]);

            return redirect()->back()->with('unsuccess', "FAILED TO CONNECT API");
        } catch (\Exception $e) {
            logger()->error('TnPay Signature Generation Error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('unsuccess', "Failed to generate payment signature");
        }

        // Parse the JSON response
        $tnPay = json_decode($tnPayResponse, true);
        logger()->info('TnPay Parsed Response:', $tnPay);

        if (isset($tnPay['data']['gateway_page_url']) && $tnPay['data']['gateway_page_url'] != "") {
            logger()->info('TnPay data', $tnPay['data']);
            // Redirect to payment gateway
            echo "<script>window.location.href = '" . $tnPay['data']['gateway_page_url'] . "';</script>";
            echo "<meta http-equiv='refresh' content='0;url=" . $tnPay['data']['gateway_page_url'] . "'>";
            header("Location: " . $tnPay['data']['gateway_page_url']);
            exit;
        } else {
            return redirect()->back()->with('unsuccess', "JSON Data parsing error!");
        }
    }

    public function notify(Request $request)
    {
        $input = $request->all();
        if ($input['status'] == '1002') {
            $order = Order::where('txnid', $input['orderId'])->first();
            logger()->info('Order Data:', ['order' => $order]);
            if (isset($order)) {
                $data['payment_status'] = 'Paid';
                $order->update($data);
                TrackOrder::create([
                    'title' => 'Pending',
                    'order_id' => $order->id,
                ]);
                $user = Auth::user();
                $cart = json_decode($order->cart, true);
                $customer_info = json_decode($order->billing_info, true);
                logger()->info('Order Cart Data:', ['cart' => $cart]);
                logger()->info('Order customer info Data:', ['customer_info' => $customer_info]);
                $total_tax = 0;
                $cart_total = 0;
                $total = 0;
                $option_price = 0;
                foreach ($cart as $key => $item) {

                    $total += $item['main_price'] * $item['qty'];
                    $option_price += $item['attribute_price'];
                    $cart_total = $total + $option_price;
                    $item = Item::findOrFail($key);
                    if ($item->tax) {
                        $total_tax += $item::taxCalculate($item);
                    }
                }
                if (!PriceHelper::Digital($cart)) {
                    $shipping = null;
                } else {
                    $order_shipping_data = json_decode($order->shipping, true);
                    $shipping = ShippingService::findOrFail($order_shipping_data['id']);
                }
                $discount = [];
                if (Session::has('coupon')) {
                    $discount = Session::get('coupon');
                }

                $grand_total = ($cart_total + ($shipping ? $shipping->price : 0)) + $total_tax;
                $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
                $total_amount = PriceHelper::setConvertPrice($grand_total);

                $new_txn = $new_txn = 'ORD-' . str_pad(Carbon::now()->format('Ymd'), 4, '0000', STR_PAD_LEFT) . '-' . $order->id;
                $order->transaction_number = $new_txn;
                $order->save();

                PriceHelper::Transaction($order->id, $order->transaction_number, EmailHelper::getEmail($customer_info['bill_email']), PriceHelper::OrderTotal($order, 'trns'));
                PriceHelper::LicenseQtyDecrese($cart);

                Notification::create([
                    'order_id' => $order->id
                ]);

                $emailData = [
                    'to' => EmailHelper::getEmail($customer_info['bill_email']),
                    'type' => "Order",
                    'user_name' => isset($user) ? $user->displayName() : $customer_info['bill_first_name'],
                    'order_cost' => $total_amount,
                    'transaction_number' => $order->transaction_number,
                    'site_title' => Setting::first()->title,
                ];

                $setting = Setting::first();
                if ($setting->is_queue_enabled == 1) {
                    dispatch(new EmailSendJob($emailData, "template"));
                } else {
                    $email = new EmailHelper($customer_info['bill_email']);
                    $email->sendTemplateMail($emailData, "template");
                }

                Session::put('order_id', $order->id);
                Session::forget('cart');
                Session::forget('discount');
                Session::forget('coupon');
                if ($discount) {
                    $coupon_id = $discount['code']['id'];
                    $get_coupon = PromoCode::findOrFail($coupon_id);
                    $get_coupon->no_of_times -= 1;
                    $get_coupon->update();
                }
//                $setting = Setting::first();
//                if ($setting->is_twilio == 1) {
//                    // message
//                    $sms = new SmsHelper();
//                    $user_number = json_decode($order->billing_info, true)['bill_phone'];
//                    if ($user_number) {
//                        $sms->SendSms($user_number, "'purchase'", $order->transaction_number);
//                    }
//                }
                logger()->info('Payment successful, redirecting to success page', ['order_id' => $order->id]);
                return redirect()->route('front.checkout.success', ['order_id' => $order->id]);
            } else {
                return redirect()->route('front.checkout.cancle');
            }
        } else {
            return redirect()->route('front.checkout.cancle');
        }
    }
}
