{if $public_setting['captcha_provider'] === 'turnstile'}
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script>
        function refreshCaptcha() {
            if (window.turnstile) {
                turnstile.render('#turnstile');
            }
        }

        window.syncCaptchaForm = function () {
            const response = document.querySelector('[name=cf-turnstile-response]');
            document.getElementById('captcha_turnstile').value = response ? response.value : '';
        };
    </script>
{/if}
{if $public_setting['captcha_provider'] === 'geetest'}
    <script src="https://static.geetest.com/v4/gt4.js"></script>
    <script>
        let geetest_result = '';
        initGeetest4({
            captchaId: '{$captcha['geetest_id']}',
            product: 'float',
            language: "zho",
            riskType: 'slide'
        }, function (geetest) {
            geetest.appendTo("#geetest");
            geetest.onSuccess(function () {
                geetest_result = geetest.getValidate();
                document.getElementById('geetest_lot_number').value = geetest_result.lot_number;
                document.getElementById('geetest_captcha_output').value = geetest_result.captcha_output;
                document.getElementById('geetest_pass_token').value = geetest_result.pass_token;
                document.getElementById('geetest_gen_time').value = geetest_result.gen_time;
            });
        });

        function refreshCaptcha() {
            if (window.geetest) {
                geetest.reset();
            }
        }

        window.syncCaptchaForm = function () {};
    </script>
{/if}
{if $public_setting['captcha_provider'] === 'hcaptcha'}
    <script src='https://js.hcaptcha.com/1/api.js' async defer></script>
    <script>
        function refreshCaptcha() {
            if (window.hcaptcha) {
                hcaptcha.reset();
            }
        }

        window.syncCaptchaForm = function () {
            document.getElementById('captcha_hcaptcha').value = window.hcaptcha ? hcaptcha.getResponse() : '';
        };
    </script>
{/if}
{if $public_setting['captcha_provider'] === 'recaptcha_enterprise'}
    <script src='https://www.recaptcha.net/recaptcha/enterprise.js?onload=initReCAPTCHA&render=explicit' async defer></script>
    <script>
        var initReCAPTCHA = function () {
            grecaptcha.enterprise.render('recaptcha', {
                'sitekey': '{$captcha['recaptcha_enterprise_key_id']}',
            });
        };

        function refreshCaptcha() {
            if (window.grecaptcha && window.grecaptcha.enterprise) {
                grecaptcha.enterprise.reset();
            }
        }

        window.syncCaptchaForm = function () {
            const captcha = window.grecaptcha && window.grecaptcha.enterprise;
            document.getElementById('captcha_recaptcha_enterprise').value = captcha ? captcha.getResponse() : '';
        };
    </script>
{/if}
<script>
    function prepareCaptchaForm() {
        if (window.syncCaptchaForm) {
            window.syncCaptchaForm();
        }
    }

    htmx.on("htmx:afterRequest", function (evt) {
        let res = JSON.parse(evt.detail.xhr.response);
        if (res.ret === 0) {
            refreshCaptcha();
        }
    });
</script>
