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
            <div class="card-body">
                <h2 class="card-title text-center mb-4">{trans key='auth.password.reset.title'}</h2>
                <div class="mb-3">
                    <label class="form-label">{trans key='auth.password.reset.password'}</label>
                    <input id="password" type="password" class="form-control" placeholder="{trans key='auth.password.reset.password_placeholder'}">
                </div>
                <div class="mb-3">
                    <label class="form-label">{trans key='auth.password.reset.confirm_password'}</label>
                    <input id="confirm_password" type="password" class="form-control" placeholder="{trans key='auth.password.reset.confirm_password_placeholder'}">
                </div>
                <div class="form-footer">
                    <button class="btn btn-primary w-100"
                            hx-post="{ location.pathname }" hx-swap="none"
                            hx-vals='js:{
                            password: document.getElementById("password").value,
                            confirm_password: document.getElementById("confirm_password").value, }'>
                        <i class="ti ti-key icon"></i>
                        {trans key='auth.password.reset.submit'}
                    </button>
                </div>
            </div>
        </div>
        <div class="text-center text-secondary mt-3">
            {trans key='auth.password.has_account'} <a href="/auth/login" tabindex="-1">{trans key='auth.password.login_link'}</a>
        </div>
    </div>
</div>

{include file='footer.tpl'}
