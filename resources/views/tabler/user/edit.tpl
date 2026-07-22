{include file='user/header.tpl'}

<script src="//{$config['jsdelivr_url']}/npm/jquery/dist/jquery.min.js"></script>
<script src="https://unpkg.com/@simplewebauthn/browser@13.3.0/dist/bundle/index.umd.min.js"></script>

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='user.settings.title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='user.settings.subtitle'}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-deck row-cards">
                <div class="col-12">
                    <div class="card">
                        <ul class="nav nav-tabs nav-fill" data-bs-toggle="tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a href="#personal_information" class="nav-link active" data-bs-toggle="tab"
                                   aria-selected="true" role="tab">
                                    <i class="ti ti-chart-candle icon"></i>&nbsp;
                                    {trans key='user.settings.tab_profile'}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="#login_security" class="nav-link" data-bs-toggle="tab" aria-selected="true"
                                   role="tab">
                                    <i class="ti ti-shield-lock icon"></i>&nbsp;
                                    {trans key='user.settings.tab_login'}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="#use_safety" class="nav-link" data-bs-toggle="tab" aria-selected="false"
                                   tabindex="-1" role="tab">
                                    <i class="ti ti-brand-telegram icon"></i>&nbsp;
                                    {trans key='user.settings.tab_usage'}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="#other_settings" class="nav-link" data-bs-toggle="tab" aria-selected="false"
                                   tabindex="-1" role="tab">
                                    <i class="ti ti-settings icon"></i>&nbsp;
                                    {trans key='user.settings.tab_other'}
                                </a>
                            </li>
                        </ul>
                        <div class="card-body">
                            <div class="tab-content">
                                <div class="tab-pane active show" id="personal_information" role="tabpanel">
                                    <div class="row row-deck row-cards">
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.login_email'}</h3>
                                                    <p>{trans key='user.settings.current_email'}<code id="email">{$user->email}</code></p>
                                                    <div class="mb-3">
                                                        <input id="new-email" type="email" class="form-control"
                                                               placeholder="{trans key='user.settings.new_email'}"
                                                               {if ! $config['enable_change_email']}disabled=""{/if}>
                                                    </div>
                                                    {if $public_setting['reg_email_verify'] && $config['enable_change_email']}
                                                    <div class="mb-3">
                                                        <input id="email-code" type="text" class="form-control"
                                                               placeholder="{trans key='user.settings.verification_code'}">
                                                    </div>
                                                    {/if}
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        {if $public_setting['reg_email_verify'] && $config['enable_change_email']}
                                                        <button class="btn btn-link"
                                                                hx-post="/user/edit/send" hx-swap="none"
                                                                hx-vals='js:{ email: document.getElementById("newemail").value }'>
                                                            {trans key='user.settings.get_verification_code'}
                                                        </button>
                                                        <button class="btn btn-primary ms-auto"
                                                                hx-post="/user/edit/email" hx-swap="none"
                                                                hx-vals='js:{
                                                                    newemail: document.getElementById("new-email").value,
                                                                    emailcode: document.getElementById("email-code").value
                                                                }'>
                                                            {trans key='common.modify'}
                                                        </button>
                                                        {elseif $config['enable_change_email']}
                                                        <button class="btn btn-primary ms-auto"
                                                                hx-post="/user/edit/email" hx-swap="none"
                                                                hx-vals='js:{ newemail: document.getElementById("new-email").value }'>
                                                            {trans key='common.modify'}
                                                        </button>
                                                        {else}
                                                        <button class="btn btn-primary ms-auto"
                                                                disabled>{trans key='user.settings.change_not_allowed'}
                                                        </button>
                                                        {/if}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.username'}</h3>
                                                    <p>{trans key='user.settings.current_username'}<code id="username">{$user->user_name}</code></p>
                                                    <div class="mb-3">
                                                        <input id="new-username" type="text" class="form-control"
                                                               placeholder="{trans key='user.settings.new_username'}" autocomplete="off">
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto"
                                                           hx-post="/user/edit/username" hx-swap="none"
                                                           hx-vals='js:{ newusername: document.getElementById("new-username").value }'>
                                                            {trans key='common.modify'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.im_binding'}</h3>
                                                    <div class="mb-3">
                                                        <select id="imtype" class="form-select"
                                                                {if $user->im_type !== 0 && $user->im_value !== ''}disabled=""{/if}>
                                                            <option value="0" {if $user->im_type === 0}selected{/if}>
                                                                {trans key='user.settings.unbound'}
                                                            </option>
                                                            <option value="1" {if $user->im_type === 1}selected{/if}>
                                                                Slack
                                                            </option>
                                                            <option value="2" {if $user->im_type === 2}selected{/if}>
                                                                Discord
                                                            </option>
                                                            <option value="4" {if $user->im_type === 4}selected{/if}>
                                                                Telegram
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <input id="imvalue" type="text" class="form-control"
                                                               value="{$user->im_value}" disabled>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex btn-list justify-content-end"
                                                         id="oauth-provider"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.unbind_im'}</h3>
                                                    {if $user->im_type === 0}
                                                    <p>{trans key='user.settings.no_im_bound'}</p>
                                                    {else}
                                                    <p>
                                                        {trans key='user.settings.current_im_service'}{$user->imType()}
                                                        <br>
                                                        {trans key='user.settings.account_id'}<code>{$user->im_value}</code>
                                                    </p>
                                                    {/if}
                                                </div>
                                                {if $user->im_type !== 0}
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-red ms-auto"
                                                                hx-post="/user/edit/unbind_im" hx-swap="none">
                                                            {trans key='user.settings.unbind'}
                                                        </button>
                                                    </div>
                                                </div>
                                                {/if}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="login_security" role="tabpanel">
                                    <div class="row row-deck row-cards">
                                        <div class="col-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.mfa_identity_confirmation'}</h3>
                                                    <p class="card-subtitle">{trans key='user.settings.mfa_identity_confirmation_description'}</p>
                                                    <input id="mfa_current_password" type="password" class="form-control"
                                                           placeholder="{trans key='user.settings.current_password'}"
                                                           autocomplete="current-password">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.change_password'}</h3>
                                                    <div class="mb-3">
                                                        <form>
                                                            <input id="password" type="password" class="form-control"
                                                                   placeholder="{trans key='user.settings.current_password'}" autocomplete="off">
                                                        </form>
                                                    </div>
                                                    <div class="mb-3">
                                                        <form>
                                                            <input id="new_password" type="password"
                                                                   class="form-control" placeholder="{trans key='user.settings.new_password'}"
                                                                   autocomplete="off">
                                                        </form>
                                                    </div>
                                                    <div class="mb-3">
                                                        <form>
                                                            <input id="confirm_new_password" type="password"
                                                                   class="form-control" placeholder="{trans key='user.settings.confirm_new_password'}"
                                                                   autocomplete="off">
                                                        </form>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto"
                                                                hx-post="/user/edit/password" hx-swap="none"
                                                                hx-vals='js:{
                                                                    new_password: document.getElementById("new_password").value,
                                                                    confirm_new_password: document.getElementById("confirm_new_password").value,
                                                                    password: document.getElementById("password").value
                                                                }'>
                                                            {trans key='common.modify'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">TOTP
                                                        {if $totpDevices}
                                                             <span class="badge bg-green text-green-fg">{trans key='user.settings.enabled'}</span>
                                                        {else}
                                                             <span class="badge bg-red text-red-fg">{trans key='user.settings.disabled'}</span>
                                                        {/if}
                                                    </h3>
                                                    <p class="card-subtitle">{trans key='user.settings.totp_description'}</p>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        {if $totpDevices}
                                                            <button class="btn btn-red ms-auto mfa-delete"
                                                                    data-url="/user/totp"
                                                                    data-confirm="{trans key='user.settings.confirm_disable_totp'}">
                                                                {trans key='user.settings.disable'}
                                                            </button>
                                                        {else}
                                                            <button class="btn btn-primary ms-auto" id="enableTotp">
                                                                {trans key='user.settings.enable'}
                                                            </button>
                                                        {/if}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">Passkey</h3>
                                                    <p class="card-subtitle">{trans key='user.settings.passkey_description'}</p>
                                                    <div class="row row-cols-1 row-cols-md-4 g-4">
                                                        {foreach $webauthnDevices as $device}
                                                            <div class="col">
                                                                <div class="card">
                                                                    <div class="card-body">
                                                                         <h5 class="card-title">{if $device->name}{$device->name}{else}{trans key='user.settings.unnamed'}{/if}</h5>
                                                                        <p class="card-text">
                                                                             {trans key='user.settings.added_at'} {$device->created_at}</p>
                                                                        <p class="card-text">
                                                                             {trans key='user.settings.last_used'} {if $device->used_at}{$device->used_at}{else}{trans key='user.settings.never_used'}{/if}</p>
                                                                        <button class="btn btn-danger mfa-delete"
                                                                                data-url="/user/webauthn/{$device->id}"
                                                                                data-confirm="{trans key='user.settings.confirm_delete_device'}">{trans key='common.delete'}
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        {/foreach}
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto" id="webauthnReg">
                                                             {trans key='user.settings.register_passkey'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-12">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">FIDO
                                                        {if $fidoDevices}
                                                             <span class="badge bg-green text-green-fg">{trans key='user.settings.enabled'}</span>
                                                        {else}
                                                             <span class="badge bg-red text-red-fg">{trans key='user.settings.disabled'}</span>
                                                        {/if}
                                                    </h3>
                                                    <p class="card-subtitle">{trans key='user.settings.fido_description'}</p>
                                                    {if $fidoDevices}
                                                        <div class="row row-cols-1 row-cols-md-4 g-4">
                                                            {foreach $fidoDevices as $device}
                                                                <div class="col">
                                                                    <div class="card">
                                                                        <div class="card-body">
                                                                             <h5 class="card-title">{if $device->name}{$device->name}{else}{trans key='user.settings.unnamed'}{/if}</h5>
                                                                            <p class="card-text">
                                                                                 {trans key='user.settings.added_at'} {$device->created_at}</p>
                                                                            <p class="card-text">
                                                                                 {trans key='user.settings.last_used'} {if $device->used_at}{$device->used_at}{else}{trans key='user.settings.never_used'}{/if}</p>
                                                                            <button class="btn btn-danger mfa-delete"
                                                                                    data-url="/user/fido/{$device->id}"
                                                                                    data-confirm="{trans key='user.settings.confirm_delete_device'}">{trans key='common.delete'}
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            {/foreach}
                                                        </div>
                                                    {/if}
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto" id="fidoReg">
                                                             {trans key='user.settings.register_fido'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="use_safety" role="tabpanel">
                                    <div class="row row-deck row-cards">
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.change_encryption'}</h3>
                                                    <p>
                                                        {trans key='user.settings.change_encryption_description'}</p>
                                                    <div class="mb-3">
                                                        <select id="user-method" class="form-select">
                                                            {foreach $methods as $method}
                                                            <option value="{$method}"
                                                                    {if $user->method === $method}selected{/if}>
                                                                {$method}
                                                            </option>
                                                            {/foreach}
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto"
                                                                hx-post="/user/edit/method" hx-swap="none"
                                                                hx-vals='js:{ method: document.getElementById("user-method").value }'>
                                                            {trans key='common.modify'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.reset_subscription_url'}</h3>
                                                    <p>{trans key='user.settings.reset_subscription_url_description'}</p>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto bg-red"
                                                                hx-post="/user/edit/url_reset" hx-swap="none">
                                                            {trans key='common.reset'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.reset_connection_password'}</h3>
                                                    <p>{trans key='user.settings.reset_connection_password_description'}</p>
                                                    <p>{trans key='user.settings.current_connection_password'}<code id="passwd" class="spoiler">{$user->passwd}</code></p>
                                                    <p>{trans key='user.settings.current_uuid'}<code id="uuid" class="spoiler">{$user->uuid}</code></p>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto bg-red"
                                                                hx-post="/user/edit/passwd_reset" hx-swap="none">
                                                            {trans key='common.reset'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="other_settings" role="tabpanel">
                                    <div class="row row-deck row-cards">
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.daily_report'}</h3>
                                                    <div class="mb-3">
                                                        <select id="daily-mail" class="form-select">
                                                            <option value="0"
                                                                    {if $user->daily_mail_enable === 0}selected{/if}>
                                                                {trans key='user.settings.do_not_receive'}
                                                            </option>
                                                            <option value="1"
                                                                    {if $user->daily_mail_enable === 1}selected{/if}>
                                                                {trans key='user.settings.email_receive'}
                                                            </option>
                                                            <option value="2"
                                                                    {if $user->daily_mail_enable === 2}selected{/if}>
                                                                {trans key='user.settings.im_receive'}
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto"
                                                                hx-post="/user/edit/daily_mail" hx-swap="none"
                                                                hx-vals='js:{ mail: document.getElementById("daily-mail").value }'>
                                                            {trans key='common.modify'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.preferred_contact'}</h3>
                                                    <p>{trans key='user.settings.preferred_contact_description'}</p>
                                                    <div class="mb-3">
                                                        <select id="contact-method" class="form-select">
                                                            <option value="1"
                                                                    {if $user->contact_method === 1}selected{/if}>
                                                                {trans key='user.settings.email_contact'}
                                                            </option>
                                                            <option value="2"
                                                                    {if $user->contact_method === 2}selected{/if}>
                                                                IM
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto"
                                                                hx-post="/user/edit/contact_method" hx-swap="none"
                                                                hx-vals='js:{ contact: document.getElementById("contact-method").value }'>
                                                            {trans key='common.modify'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.change_theme'}</h3>
                                                    <div class="mb-3">
                                                        <select id="user-theme" class="form-select">
                                                            {foreach $themes as $theme}
                                                            <option value="{$theme}"
                                                                    {if $user->theme === $theme}selected{/if}>{$theme}
                                                            </option>
                                                            {/foreach}
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto"
                                                                hx-post="/user/edit/theme" hx-swap="none"
                                                                hx-vals='js:{ theme: document.getElementById("user-theme").value }'>
                                                            {trans key='common.modify'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.change_theme_mode'}</h3>
                                                    <div class="mb-3">
                                                        <select id="theme-mode" class="form-select">
                                                            <option value="2" {if $user->is_dark_mode === 2}selected{/if}>
                                                                {trans key='user.settings.theme_auto'}
                                                            </option>
                                                            <option value="0" {if $user->is_dark_mode === 0}selected{/if}>
                                                                {trans key='user.settings.theme_light'}
                                                            </option>
                                                            <option value="1" {if $user->is_dark_mode === 1}selected{/if}>
                                                                {trans key='user.settings.theme_dark'}
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex">
                                                        <button class="btn btn-primary ms-auto"
                                                                hx-post="/user/edit/theme_mode" hx-swap="none"
                                                                hx-vals='js:{ theme_mode: document.getElementById("theme-mode").value }'>
                                                            {trans key='common.modify'}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        {if $config['enable_kill']}
                                        <div class="col-sm-12 col-md-6">
                                            <div class="card">
                                                <div class="card-stamp">
                                                    <div class="card-stamp-icon bg-red">
                                                        <i class="ti ti-circle-x"></i>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <h3 class="card-title">{trans key='user.settings.delete_account_data'}</h3>
                                                </div>
                                                <div class="card-footer">
                                                    <button class="btn btn-red" data-bs-toggle="modal"
                                                       data-bs-target="#destroy-account">
                                                        <i class="ti ti-trash icon"></i>
                                                         {trans key='user.settings.confirm_delete'}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {if $config['enable_kill']}
    <div class="modal modal-blur fade" id="destroy-account" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status bg-danger"></div>
                <div class="modal-body text-center py-4">
                    <i class="ti ti-alert-circle icon mb-2 text-danger icon-lg" style="font-size:3.5rem;"></i>
                    <h3>{trans key='user.settings.delete_confirmation'}</h3>
                    <div class="text-secondary">
                        {trans key='user.settings.delete_warning'}
                    </div>
                    <div class="py-3">
                        <form>
                            <input id="confirm_kill_password" type="password" class="form-control"
                                   placeholder="{trans key='user.settings.enter_login_password'}" autocomplete="off">
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row">
                            <div class="col">
                                <button class="btn w-100" data-bs-dismiss="modal">
                                    {trans key='common.cancel'}
                                </button>
                            </div>
                            <div class="col">
                                <button href="#" class="btn btn-danger w-100" data-bs-dismiss="modal"
                                        hx-post="/user/edit/kill" hx-swap="none"
                                        hx-vals='js:{ password: document.getElementById("confirm_kill_password").value }'>
                                    {trans key='common.confirm'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {/if}

    <div class="modal" id="totpModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{trans key='user.settings.setup_totp'}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="row">
                        <div class="col-md-12">
                            <p>{trans key='user.settings.scan_totp_qr'}</p>
                        </div>
                        <div class="col-md-12 d-flex justify-content-center align-items-center">
                            <div id="qrcode"></div>
                        </div>
                        <div class="col-md-12">
                            <p>{trans key='user.settings.manual_totp_secret'}</p>
                            <p id="totpSecret"></p>
                        </div>
                        <div class="col-md-12">
                            <input type="text" id="totpCode" placeholder="{trans key='user.settings.enter_totp_code'}" class="form-control mx-auto">
                        </div>
                    </div>
                    <div id="qrcode"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="submitTotp">{trans key='common.submit'}</button>
                </div>
            </div>
        </div>
    </div>

    {include file='user/footer.tpl'}
    <script>
        async function authorizeMfaManagement() {
            const password = document.getElementById('mfa_current_password').value;
            const response = await fetch('/user/mfa/reauth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                {literal}
                body: JSON.stringify({password: password}),
                {/literal}
            });
            const result = await response.json();
            if (result.ret !== 1) {
                document.getElementById('fail-message').innerText = result.msg;
                failDialog.show();
                return false;
            }

            return true;
        }

        document.querySelectorAll('.mfa-delete').forEach(button => {
            button.addEventListener('click', async () => {
                if (!confirm(button.dataset.confirm) || !await authorizeMfaManagement()) {
                    return;
                }

                const response = await fetch(button.dataset.url, {literal}{method: 'DELETE'}{/literal});
                const result = await response.json();
                if (result.ret === 1) {
                    location.reload();
                    return;
                }

                document.getElementById('fail-message').innerText = result.msg;
                failDialog.show();
            });
        });

        {if not $totpDevices}
        document.querySelector('#enableTotp').addEventListener('click', async () => {
            if (!await authorizeMfaManagement()) {
                return;
            }
            const resp = await fetch('/user/totp');
            const data = await resp.json();
            var modal = new tabler.bootstrap.Modal(document.getElementById('totpModal'), {
                backdrop: 'static',
                keyboard: false
            });
            if (data.ret === 1) {
                let qrcodeElement = document.getElementById('qrcode');
                qrcodeElement.innerHTML = '';
                let totpSecret = document.getElementById('totpSecret');
                totpSecret.innerHTML = data.token;
                let qrcode = new QRCode(qrcodeElement, {
                    text: data.url,
                    width: 256,
                    height: 256,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
                modal.show();
            } else {
                var fail_modal = new tabler.bootstrap.Modal(document.getElementById('fail-dialog'));
                document.getElementById('fail-message').innerText = data.msg;
                fail_modal.show();
            }
        });

        document.getElementById('submitTotp').addEventListener('click', function () {
            var totpCode = document.getElementById('totpCode').value;

            fetch('/user/totp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                {literal}
                body: JSON.stringify({code: totpCode}),
                {/literal}
            })
                .then(response => response.json())
                .then(data => {
                    var totpModal = new tabler.bootstrap.Modal(document.getElementById('totpModal'));

                    if (data.ret === 1) {
                        totpModal.hide();
                        document.getElementById("success-message").innerHTML = data.msg;
                        successDialog.show();
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        document.getElementById("fail-message").innerHTML = data.msg;
                        failDialog.show();
                    }
                })
        });
        {/if}
        const { startRegistration } = SimpleWebAuthnBrowser;
        document.getElementById('fidoReg').addEventListener('click', async () => {
            if (!await authorizeMfaManagement()) {
                return;
            }
            const resp = await fetch('/user/fido');
            let attResp;
            const options = await resp.json();
            try {
                attResp = await startRegistration({ optionsJSON: options });
            } catch (error) {
                $('#error-message').text(error.message);
                $('#fail-dialog').modal('show');
                throw error;
            }
            attResp.name = prompt("{trans key='user.settings.enter_device_name'}");
            const verificationResp = await fetch('/user/fido', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(attResp),
            });

            const verificationJSON = await verificationResp.json();
            if (verificationJSON.ret === 1) {
                $('#success-message').text(verificationJSON.msg);
                $('#success-dialog').modal('show');
                setTimeout(function () {
                    location.reload();
                }, 1000);
            } else {
                $('#error-message').text(verificationJSON.msg);
                $('#fail-dialog').modal('show');
            }
        });
        document.getElementById('webauthnReg').addEventListener('click', async () => {
            if (!await authorizeMfaManagement()) {
                return;
            }
            const resp = await fetch('/user/webauthn');
            const options = await resp.json();
            let attResp;
            try {
                attResp = await startRegistration({ optionsJSON: options });
            } catch (error) {
                $('#error-message').text(error.message);
                $('#fail-dialog').modal('show');
                throw error;
            }
            attResp.name = prompt("{trans key='user.settings.enter_device_name'}");
            const verificationResp = await fetch('/user/webauthn', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(attResp),
            });
            const verificationJSON = await verificationResp.json();
            if (verificationJSON.ret === 1) {
                $('#success-message').text(verificationJSON.msg);
                $('#success-dialog').modal('show');
                setTimeout(function () {
                    location.reload();
                }, 1000);
            } else {
                $('#error-message').text(verificationJSON.msg);
                $('#fail-dialog').modal('show');
            }
        });
        {if $user->im_type === 0 && $user->im_value === ''}
        let oauthProvider = $('#oauth-provider');

        $("#imtype").on('change', function () {
            if ($(this).val() === '0') {
                oauthProvider.empty();
            } else if ($(this).val() === '1') {
                oauthProvider.empty();
                oauthProvider.append(
                    "<a id='bind-slack' class='btn btn-azure ms-auto'>{trans key='user.settings.bind_slack'}</a>"
                );
            } else if ($(this).val() === '2') {
                oauthProvider.empty();
                oauthProvider.append(
                    "<a id='bind-discord' class='btn btn-indigo ms-auto'>{trans key='user.settings.bind_discord'}</a>"
                );
            } else if ($(this).val() === '4') {
                oauthProvider.empty();
                oauthProvider.append(
                    '<script async src=\"https://telegram.org/js/telegram-widget.js?22\"' +
                    ' data-telegram-login=\"' + "{$public_setting['telegram_bot']}" +
                    '\" data-size=\"large" data-onauth=\"onTelegramAuth(user)\"' +
                    ' data-request-access=\"write\"><\/script>'
                );
            }
        });

        oauthProvider.on('click', '#bind-slack', function () {
            $.ajax({
                type: "POST",
                url: "/oauth/slack",
                dataType: "json",
                success: function (data) {
                    handleOauthResult(data, 'slack')
                }
            })
        });

        oauthProvider.on('click', '#bind-discord', function () {
            $.ajax({
                type: "POST",
                url: "/oauth/discord",
                dataType: "json",
                success: function (data) {
                    handleOauthResult(data, 'discord')
                }
            })
        });

        function onTelegramAuth(user) {
            $.ajax({
                type: "POST",
                url: "/oauth/telegram",
                dataType: "json",
                data: {
                    user: JSON.stringify(user),
                },
                success: function (data) {
                    handleOauthResult(data, 'telegram')
                }
            })
        }

        function handleOauthResult(data, type = 'telegram') {
            if (data.ret === 1) {
                if (type === 'telegram') {
                    $('#success-message').text(data.msg);
                    $('#success-dialog').modal('show');
                } else {
                    window.location.replace(data.redir);
                }
            } else {
                $('#error-message').text(data.msg);
                $('#fail-dialog').modal('show');
            }
        }
        {/if}
    </script>
