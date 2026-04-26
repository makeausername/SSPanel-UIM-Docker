<?php

declare(strict_types=1);

return [
    'auth' => [
        'login_title' => '登录到用户中心',
        'login' => [
            'email' => '邮箱',
            'forgot_password' => '忘记密码',
            'no_account' => '还没有账户？',
            'password' => '登录密码',
            'register_link' => '点击注册',
            'remember_device' => '记住此设备',
            'submit' => '登录',
            'title' => '登录到用户中心',
            'webauthn_submit' => '使用WebAuthn登录',
        ],
        'mfa' => [
            'description' => '您的账户已启用二步验证，为了您的账户安全，请您完成附加身份验证。',
            'fido_submit' => '使用 FIDO2 验证',
            'submit' => '提交',
            'title' => '二步验证',
        ],
        'password' => [
            'has_account' => '已有账户？',
            'login_link' => '点击登录',
            'forgot' => [
                'description' => '我们将向你的注册邮箱发送一封邮件，邮件内容中包含一个可以重设密码的链接',
                'email' => '注册邮箱',
                'send' => '发送邮件',
                'title' => '忘记密码',
            ],
            'reset' => [
                'confirm_password' => '再次输入新密码',
                'confirm_password_placeholder' => '请再次输入新密码',
                'password' => '新密码',
                'password_placeholder' => '请输入新密码',
                'submit' => '重置',
                'title' => '设置新密码',
            ],
        ],
        'register' => [
            'closed' => '还没有开放注册，过两天再来看看吧',
            'confirm_password' => '重复登录密码',
            'email' => '电子邮箱',
            'email_code' => '邮箱验证码',
            'get_email_code' => '获取',
            'has_account' => '已有账户？',
            'invite_code' => '注册邀请码',
            'login_link' => '点击登录',
            'name' => '昵称',
            'optional' => '（可选）',
            'password' => '登录密码',
            'required' => '（必填）',
            'submit' => '注册新账户',
            'title' => '注册账户',
            'tos_link' => '服务条款与隐私政策',
            'tos_prefix' => '我已阅读并同意',
        ],
    ],
    'common' => [
        'cancel' => '取消',
        'confirm' => '确认',
        'language' => '语言',
        'save' => '保存',
        'submit' => '提交',
        'switch_language' => '切换语言',
    ],
    'locale' => [
        'en-US' => 'English',
        'zh-CN' => '简体中文',
    ],
];
