{include file='header.tpl'}

<body class="border-top-wide border-primary d-flex flex-column">
<div class="page page-center">
    <div class="container-tight my-auto">
        <div class="empty">
            <div class="empty-header">405</div>
            <p class="empty-title">{trans key='error.405.title'}</p>
            <p class="empty-subtitle text-secondary">
                {trans key='error.405.subtitle'}
            </p>
            <div class="empty-action">
                <a href="/" class="btn btn-primary">
                    <i class="icon ti ti-chevron-left"></i>
                    {trans key='error.return_home'}
                </a>
            </div>
        </div>
    </div>
</div>

{include file='footer.tpl'}
