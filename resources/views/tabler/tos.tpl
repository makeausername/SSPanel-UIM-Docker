{include file='header.tpl'}

<body class="border-top-wide border-primary d-flex flex-column">
<div class="page page-center">
    <div class="container-tight my-auto">
        <div class="empty">
            <p class="empty-title">{trans key='tos.title'}</p>
            <p>{$config['appName']}{trans key='tos.site_suffix'}</p>
            <br>
            <p class="empty-subtitle">{trans key='tos.privacy_title'}</p>
            <p>{trans key='tos.email_credential'}</p>
            <p>{trans key='tos.password_security'}</p>
            <br>
            <p class="empty-subtitle">{trans key='tos.terms_title'}</p>
            <p>{trans key='tos.lawful_use'}</p>
            <p>{trans key='tos.free_user_deletion'}</p>
            <p>{trans key='tos.violation_action'}</p>
        </div>
    </div>
</div>

{include file='footer.tpl'}
