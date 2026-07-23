<script src="https://www.paypal.com/sdk/js?client-id={$public_setting['paypal_client_id']}&currency={$public_setting['paypal_currency']}"></script>

<div class="card-inner">
    <h4>
        PayPal
    </h4>
    <p class="card-heading"></p>
    <div id="paypal-button-container"></div>
</div>

<script>
    paypal.Buttons({
        createOrder() {
            return fetch("/user/payment/purchase/paypal", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    invoice_id: {$invoice->id},
                }),
            })
                .then((response) => response.json())
                .then((order) => {
                    if (!order.id) {
                        throw new Error(order.msg || 'Unable to create PayPal order');
                    }

                    return order.id;
                });
        },
        onApprove(data, actions) {
            return actions.order.capture().then(() => {
                window.setTimeout(() => {
                    window.location.assign('/user/invoice/{$invoice->id}/view');
                }, Math.max(0, Number({$config['jump_delay']}) || 0));
            });
        },
        onError(error) {
            console.error('PayPal payment failed:', error);
            document.getElementById('fail-message').textContent = error.message || 'PayPal payment failed';
            failDialog.show();
        },
    }).render('#paypal-button-container');

</script>
