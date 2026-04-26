<link rel="stylesheet"
      href="//{$config['jsdelivr_url']}/npm/@tabler/core@latest/dist/css/tabler-payments.min.css">

<div class="card-inner">
    <h4>
        Stripe
    </h4>
    <p class="card-heading"></p>
    <p>{trans key='payment.stripe_card_hint_prefix'}
        <span class="payment payment-xs payment-provider-unionpay me-auto"></span>
        <span class="payment payment-xs payment-provider-mastercard me-auto"></span>
        <span class="payment payment-xs payment-provider-visa me-auto"></span>
        {trans key='payment.stripe_card_hint_suffix'}</p>
    <div class="form-group form-group-label">
        <button class="btn btn-flat waves-attach"
            hx-post="/user/payment/purchase/stripe" hx-swap="none"
            hx-vals='js:{
                invoice_id: {$invoice->id},
            }'>
            <i class="icon ti ti-credit-card"></i>
        </button>
    </div>
</div>
