{include file='header.tpl'}

<script src="https://unpkg.com/@simplewebauthn/browser@13.3.0/dist/bundle/index.umd.min.js"></script>

<body class="border-top-wide border-primary d-flex flex-column">
<div class="page page-center">
    <div class="container-tight my-auto">
        <div class="text-center mb-4">
            <a href="#" class="navbar-brand navbar-brand-autodark">
                <img src="/images/uim-logo-round_96x96.png" height="64" alt="SSPanel-UIM Logo">
            </a>
        </div>
        <div class="card card-md">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">{trans key='auth.login.title'}</h2>
                {if $login_error}
                    <div class="alert alert-danger" role="alert">{$login_error|escape:'html'}</div>
                {/if}
                <form id="login-form" method="post" action="/auth/login"
                      hx-post="/auth/login" hx-swap="none"
                      {if $public_setting['enable_login_captcha']}
                      onsubmit="prepareCaptchaForm()"
                      hx-vals='js:{ {include file='captcha/ajax.tpl'} }'
                      {/if}>
                <input type="hidden" name="login_form" value="1">
                <div class="mb-3">
                    <label class="form-label">{trans key='auth.login.email'}</label>
                    <input id="email" name="email" type="email" class="form-control"
                           autocomplete="username" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">
                        {trans key='auth.login.password'}
                        <span class="form-label-description">
                                <a href="/password/reset">{trans key='auth.login.forgot_password'}</a>
                            </span>
                    </label>
                    <div class="input-group input-group-flat">
                        <input id="password" name="password" type="password" class="form-control"
                               autocomplete="current-password" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-check">
                        <input id="remember_me" name="remember_me" value="true"
                               type="checkbox" class="form-check-input"/>
                        <span class="form-check-label">{trans key='auth.login.remember_device'}</span>
                    </label>
                </div>
                <div class="mb-3">
                    <div class="input-group mb-3">
                    {if $public_setting['enable_login_captcha']}
                        {include file='captcha/div.tpl'}
                    {/if}
                    </div>
                </div>
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        {trans key='auth.login.submit'}
                    </button>
                    <button type="button" class="btn btn-primary w-100" id="webauthnLogin">
                        {trans key='auth.login.webauthn_submit'}
                    </button>
                </div>
                </form>
            </div>
        </div>
        <div class="text-center text-secondary mt-3">
            {trans key='auth.login.no_account'} <a href="/auth/register" tabindex="-1">{trans key='auth.login.register_link'}</a>
        </div>
    </div>
</div>

{if $public_setting['enable_login_captcha']}
    {include file='captcha/js.tpl'}
{/if}

{include file='footer.tpl'}

{literal}
    <script>
        const { startAuthentication } = SimpleWebAuthnBrowser;
        document.getElementById('webauthnLogin').addEventListener('click', async () => {
            const resp = await fetch('/auth/webauthn');
            const options = await resp.json();
            let asseResp;
            try {
                asseResp = await startAuthentication({ optionsJSON: options });
            } catch (error) {
                document.getElementById("fail-message").innerHTML = error;
                throw error;
            }
            const verificationResp = await fetch('/auth/webauthn', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(asseResp),
            });
            const verificationJSON = await verificationResp.json();
            if (verificationJSON.ret === 1) {
                document.getElementById("success-message").innerHTML = verificationJSON.msg;
                successDialog.show();
                window.location.href = verificationJSON.redir;
            } else {
                document.getElementById("fail-message").innerHTML = verificationJSON.msg;
                failDialog.show();
            }
        });
    </script>
{/literal}
