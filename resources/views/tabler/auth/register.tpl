{include file='header.tpl'}

<body class="border-top-wide border-primary d-flex flex-column">
<div class="page page-center">
    <div class="container-tight my-auto">
        <div class="text-center mb-4">
            <a href="#" class="navbar-brand navbar-brand-autodark">
                <img src="/images/uim-logo-round_96x96.png" height="64" alt="SSPanel-UIM Logo">
            </a>
        </div>
        <div class="card card-md">
            {if $public_setting['reg_mode'] !== 'close'}
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">{trans key='auth.register.title'}</h2>
                    <div class="mb-3">
                        <input id="name" type="text" class="form-control" placeholder="{trans key='auth.register.name'}">
                    </div>
                    <div class="mb-3">
                        <input id="email" type="email" class="form-control" placeholder="{trans key='auth.register.email'}">
                    </div>
                    {if $public_setting['reg_email_verify']}
                    <div class="mb-3">
                        <div class="input-group mb-2">
                            <input id="emailcode" type="text" class="form-control" placeholder="{trans key='auth.register.email_code'}">
                            <button id="send-verify-email" class="btn text-blue" type="button"
                                    hx-post="/auth/send" hx-swap="none" hx-disabled-elt="this"
                                    hx-vals='js:{ email: document.getElementById("email").value }'>
                                {trans key='auth.register.get_email_code'}
                            </button>
                        </div>
                    </div>
                    {/if}
                    <div class="mb-3">
                        <div class="input-group input-group-flat">
                            <input id="password" type="password" class="form-control" placeholder="{trans key='auth.register.password'}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="input-group input-group-flat">
                            <input id="confirm_password" type="password" class="form-control" placeholder="{trans key='auth.register.confirm_password'}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="input-group input-group-flat">
                            <input id="invite_code" type="text" class="form-control"
                                   placeholder="{trans key='auth.register.invite_code'}{if $public_setting['reg_mode'] === 'open'}{trans key='auth.register.optional'}{else}{trans key='auth.register.required'}{/if}"
                                   value="{$invite_code}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-check">
                            <input id="tos" type="checkbox" class="form-check-input"/>
                            <span class="form-check-label">
                                    {trans key='auth.register.tos_prefix'} <a href="/tos" tabindex="-1"> {trans key='auth.register.tos_link'} </a>
                                </span>
                        </label>
                    </div>
                    <div class="mb-3">
                        <div class="input-group mb-3">
                        {if $public_setting['enable_reg_captcha']}
                            {include file='captcha/div.tpl'}
                        {/if}
                        </div>
                    </div>
                    <div class="form-footer">
                        <button class="btn btn-primary w-100"
                                hx-post="/auth/register" hx-swap="none" hx-vals='js:{
                                    {if $public_setting['reg_email_verify']}
                                        emailcode: document.getElementById("emailcode").value,
                                    {/if}
                                    {if $public_setting['enable_reg_captcha']}
                                        {include file='captcha/ajax.tpl'}
                                    {/if}
                                    name: document.getElementById("name").value,
                                    email: document.getElementById("email").value,
                                    password: document.getElementById("password").value,
                                    confirm_password: document.getElementById("confirm_password").value,
                                    invite_code: document.getElementById("invite_code").value,
                                    tos: document.getElementById("tos").checked,
                                 }'>
                            {trans key='auth.register.submit'}
                        </button>
                    </div>
                </div>
            {else}
                <div class="card-body">
                    <p>{trans key='auth.register.closed'}</p>
                </div>
            {/if}
        </div>
        <div class="text-center text-secondary mt-3">
            {trans key='auth.register.has_account'} <a href="/auth/login" tabindex="-1">{trans key='auth.register.login_link'}</a>
        </div>
    </div>
</div>

{if $public_setting['enable_reg_captcha']}
    {include file='captcha/js.tpl'}
{/if}

{include file='footer.tpl'}
