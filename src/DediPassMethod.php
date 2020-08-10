<?php

namespace Azuriom\Plugin\DedipassPayment;

use Azuriom\Models\User;
use Azuriom\Plugin\Shop\Cart\Cart;
use Azuriom\Plugin\Shop\Models\Payment;
use Azuriom\Plugin\Shop\Models\PaymentItem;
use Azuriom\Plugin\Shop\Payment\PaymentMethod;
use Illuminate\Http\Request;

class DediPassMethod extends PaymentMethod
{
    /**
     * The payment method id name.
     *
     * @var string
     */
    protected $id = 'dedipass';

    /**
     * The payment method display name.
     *
     * @var string
     */
    protected $name = 'Dedipass';

    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        if (! use_site_money()) {
            return redirect()->route('shop.cart.index')
                ->with('error', 'This payment method is not supported with direct payments');
        }

        return view('dedipasspayment::dedipass', [
            'dedipassPublicKey' => $this->gateway->data['public-key'],
            'dedipassCustom' => auth()->id(),
        ]);
    }

    public function notification(Request $request, ?string $paymentId)
    {
        if (! $request->has('code') || ! $request->has('custom')) {
            return response()->json(['status' => 'error', 'message' => 'No code or no custom']);
        }

        $code = $request->input('code');
        // $publicKey = $this->gateway->data['public-key'];
        $privateKey = $this->gateway->data['private-key'];

        if ($privateKey !== $request->input('privateKey')) {
            logger()->warning('[Shop] Dedipass - Invalid private key: '.$request->input('privateKey'));

            return response()->json(['status' => 'error', 'message' => 'Invalid private key']);
        }

        $useLegacyShop = ! class_exists(PaymentItem::class);

        if (Payment::where($useLegacyShop ? 'payment_id' : 'transaction_id', $code)
            ->where('created_at', '>', now()->subMinute())
            ->exists()) {
            //logger()->warning('[Shop] Dedipass - Payment already completed: '.$code);

            return response()->json(['status' => 'success', 'message' => 'Payment already completed']);
        }

        // TODO Dedipass API is broken when using IPN, so we can't verify the request...
        //$url = "http://api.dedipass.com/v1/pay/?public_key={$publicKey}&private_key=$privateKey&code={$code}";
        //$response = (new Client())->post($url);

        $status = $request->input('status');

        if ($status !== 'success') {
            logger()->warning('[Shop] Dedipass - Invalid status: '.$status);

            return response()->json(['status' => 'error', 'message' => 'Invalid status']);
        }

        $price = $request->input('payout', 0);
        $money = $request->input('virtual_currency', 0);

        $user = User::find($request->input('custom'));

        if ($user === null) {
            return response()->json(['status' => 'error', 'message' => 'Invalid user id']);
        }

        $user->addMoney($money);
        $user->save();

        // TODO remove shop 0.1.x compatibility
        if ($useLegacyShop) {
            Payment::forceCreate([
                'user_id' => $user->id,
                'price' => $price,
                'currency' => 'EUR',
                'payment_type' => $this->id,
                'status' => 'DELIVERED',
                'items' => 'Money: '.$money,
                'payment_id' => $code,
                'type' => 'OFFER',
            ]);

            return response()->json(['status' => 'success']);
        }

        Payment::create([
            'user_id' => $user->id,
            'price' => $price,
            'currency' => 'EUR',
            'gateway_type' => $this->id,
            'status' => 'completed',
            'transaction_id' => $code,
        ]);

        return response()->json(['status' => 'success']);
    }

    public function success(Request $request)
    {
        return view('shop::payments.success');
    }

    public function view()
    {
        return 'dedipasspayment::admin.dedipass';
    }

    public function rules()
    {
        return [
            'public-key' => ['required', 'string'],
            'private-key' => ['required', 'string'],
        ];
    }

    public function image()
    {
        return asset('plugins/dedipasspayment/img/dedipass.png');
    }

    public function hasFixedAmount()
    {
        return true;
    }
}
