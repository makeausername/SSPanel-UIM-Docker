{if $public_setting['captcha_provider'] === 'turnstile'}
    <div id="cf-turnstile" class="cf-turnstile" data-sitekey="{$captcha['turnstile_sitekey']}"></div>
    <input type="hidden" id="captcha_turnstile" name="turnstile">
{/if}
{if $public_setting['captcha_provider'] === 'geetest'}
    <div id="geetest"></div>
    <input type="hidden" id="geetest_lot_number" name="geetest[lot_number]">
    <input type="hidden" id="geetest_captcha_output" name="geetest[captcha_output]">
    <input type="hidden" id="geetest_pass_token" name="geetest[pass_token]">
    <input type="hidden" id="geetest_gen_time" name="geetest[gen_time]">
{/if}
{if $public_setting['captcha_provider'] === 'hcaptcha'}
    <div class="h-captcha" data-sitekey="{$captcha['hcaptcha_sitekey']}"></div>
    <input type="hidden" id="captcha_hcaptcha" name="hcaptcha">
{/if}
{if $public_setting['captcha_provider'] === 'recaptcha_enterprise'}
    <div id="recaptcha"></div>
    <input type="hidden" id="captcha_recaptcha_enterprise" name="recaptcha_enterprise">
{/if}
