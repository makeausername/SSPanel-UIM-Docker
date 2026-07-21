{include file='user/header.tpl'}
{assign var=guideLocale value='zh'}
{if $current_locale === 'en-US'}
    {assign var=guideLocale value='en'}
{/if}

<style>
.eziplc-guide .guide-hero {
    border: 0;
    overflow: hidden;
    box-shadow: 0 1rem 2.5rem rgba(20, 62, 130, 0.12);
}

.eziplc-guide .guide-hero-accent {
    min-height: 100%;
    color: #fff;
    background: linear-gradient(145deg, #1768e5 0%, #1b9bea 68%, #28b8df 100%);
    position: relative;
    overflow: hidden;
}

.eziplc-guide .guide-hero-accent::before,
.eziplc-guide .guide-hero-accent::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.09);
}

.eziplc-guide .guide-hero-accent::before {
    width: 15rem;
    height: 15rem;
    top: -7rem;
    right: -5rem;
}

.eziplc-guide .guide-hero-accent::after {
    width: 9rem;
    height: 9rem;
    bottom: -4rem;
    left: -2rem;
}

.eziplc-guide .guide-number {
    width: 2rem;
    height: 2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: var(--tblr-primary);
    background: var(--tblr-primary-lt);
    font-weight: 700;
    flex: 0 0 auto;
}

.eziplc-guide .guide-shot {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 0.75rem;
    border: 1px solid rgba(98, 105, 118, 0.16);
    box-shadow: 0 0.75rem 2rem rgba(31, 51, 89, 0.1);
}

.eziplc-guide .guide-step-card {
    border: 0;
    box-shadow: 0 0.45rem 1.5rem rgba(31, 51, 89, 0.08);
}

.eziplc-guide .mode-card {
    height: 100%;
    border: 1px solid rgba(32, 107, 196, 0.16);
    border-radius: 0.75rem;
    background: rgba(32, 107, 196, 0.04);
}

.eziplc-guide .security-list li + li {
    margin-top: 0.65rem;
}

@media (max-width: 991.98px) {
    .eziplc-guide .guide-hero-accent {
        min-height: 15rem;
    }
}
</style>

<div class="page-wrapper eziplc-guide">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <div class="page-pretitle mb-2">EzIPLC Desktop</div>
                    <h2 class="page-title">{trans key='docs.windows.title'}</h2>
                    <div class="page-pretitle mt-3">{trans key='docs.windows.intro'}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <div class="card guide-hero mb-4">
                <div class="row g-0">
                    <div class="col-lg-8">
                        <div class="card-body p-4 p-lg-5">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-primary-lt">EzIPLC 0.2.7</span>
                                <span class="badge bg-azure-lt">{trans key='docs.windows.badge'}</span>
                            </div>
                            <h1 class="display-6 mb-3">{trans key='docs.windows.title'}</h1>
                            <p class="text-secondary fs-3 mb-4">{trans key='docs.windows.intro'}</p>
                            <div class="d-flex flex-column flex-sm-row gap-2">
                                <a class="btn btn-primary btn-lg" href="/downloads/eziplc-windows.exe" download>
                                    <i class="ti ti-brand-windows me-1"></i>
                                    {trans key='docs.windows.download'}
                                </a>
                                <a class="btn btn-outline-primary btn-lg" href="/downloads/eziplc-windows.exe.sha256" target="_blank" rel="noopener">
                                    <i class="ti ti-shield-check me-1"></i>
                                    {trans key='docs.windows.verify'}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="guide-hero-accent p-4 p-lg-5 d-flex align-items-center">
                            <div class="position-relative z-1">
                                <div class="fs-1 fw-bold mb-4">EzIPLC</div>
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <span class="badge bg-white text-primary rounded-pill">1</span>
                                    <span>{trans key='docs.windows.download_title'}</span>
                                </div>
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <span class="badge bg-white text-primary rounded-pill">2</span>
                                    <span>{trans key='docs.windows.login_title'}</span>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-white text-primary rounded-pill">3</span>
                                    <span>{trans key='docs.windows.connect_title'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning mb-4" role="alert">
                <div class="d-flex">
                    <div class="me-3"><i class="ti ti-alert-triangle fs-2"></i></div>
                    <div>
                        <h4 class="alert-title">{trans key='docs.windows.unsigned_title'}</h4>
                        <div class="text-secondary">{trans key='docs.windows.unsigned_body'}</div>
                    </div>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-start gap-3">
                        <span class="guide-number">1</span>
                        <div>
                            <h2 class="mb-2">{trans key='docs.windows.download_title'}</h2>
                            <p class="text-secondary mb-2">{trans key='docs.windows.download_body'}</p>
                            <div class="d-flex align-items-center gap-2 text-primary fw-semibold">
                                <i class="ti ti-package"></i>
                                <span>{trans key='docs.windows.runtime_note'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="row align-items-center g-4 g-lg-5">
                        <div class="col-lg-5">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="guide-number">2</span>
                                <div>
                                    <h2 class="mb-2">{trans key='docs.windows.login_title'}</h2>
                                    <p class="text-secondary mb-0">{trans key='docs.windows.login_body'}</p>
                                </div>
                            </div>
                            <ul class="text-secondary ps-4 mb-0">
                                <li class="mb-2">{trans key='docs.windows.language_note'}</li>
                                <li>{trans key='docs.windows.session_note'}</li>
                            </ul>
                        </div>
                        <div class="col-lg-7">
                            <img class="guide-shot" loading="lazy"
                                 src="/assets/images/docs/windows/login-{$guideLocale}.png"
                                 alt="{trans key='docs.windows.image_login_alt'}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="row align-items-center g-4 g-lg-5">
                        <div class="col-lg-7 order-2 order-lg-1">
                            <img class="guide-shot" loading="lazy"
                                 src="/assets/images/docs/windows/home-{$guideLocale}.png"
                                 alt="{trans key='docs.windows.image_home_alt'}">
                        </div>
                        <div class="col-lg-5 order-1 order-lg-2">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="guide-number">3</span>
                                <div>
                                    <h2 class="mb-2">{trans key='docs.windows.connect_title'}</h2>
                                    <p class="text-secondary mb-0">{trans key='docs.windows.connect_body'}</p>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-12">
                                    <div class="mode-card p-3">
                                        <div class="fw-bold mb-1"><i class="ti ti-route me-1 text-primary"></i>{trans key='docs.windows.smart_title'}</div>
                                        <div class="text-secondary">{trans key='docs.windows.smart_body'}</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mode-card p-3">
                                        <div class="fw-bold mb-1"><i class="ti ti-world me-1 text-primary"></i>{trans key='docs.windows.global_title'}</div>
                                        <div class="text-secondary">{trans key='docs.windows.global_body'}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3 mb-0 py-2">{trans key='docs.windows.connect_note'}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row row-cards mb-4">
                <div class="col-lg-6">
                    <div class="card guide-step-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="guide-number">4</span>
                                <div>
                                    <h2 class="mb-2">{trans key='docs.windows.lines_title'}</h2>
                                    <p class="text-secondary mb-2">{trans key='docs.windows.lines_body'}</p>
                                    <div class="small text-secondary"><i class="ti ti-clock me-1"></i>{trans key='docs.windows.latency_note'}</div>
                                </div>
                            </div>
                            <img class="guide-shot mt-4" loading="lazy"
                                 src="/assets/images/docs/windows/lines-{$guideLocale}.png"
                                 alt="{trans key='docs.windows.image_lines_alt'}">
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card guide-step-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="guide-number">5</span>
                                <div>
                                    <h2 class="mb-2">{trans key='docs.windows.usage_title'}</h2>
                                    <p class="text-secondary mb-2">{trans key='docs.windows.usage_body'}</p>
                                    <div class="small text-secondary"><i class="ti ti-infinity me-1"></i>{trans key='docs.windows.admin_usage'}</div>
                                </div>
                            </div>
                            <div class="mt-4 p-4 rounded bg-primary-lt text-primary">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span><i class="ti ti-chart-bar me-1"></i>{trans key='docs.windows.usage_title'}</span>
                                    <span class="badge bg-primary text-primary-fg">EzIPLC</span>
                                </div>
                                <div class="progress progress-sm bg-white">
                                    <div class="progress-bar" style="width: 42%" role="progressbar" aria-label="42%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="row align-items-center g-4 g-lg-5">
                        <div class="col-lg-5">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="guide-number">6</span>
                                <div>
                                    <h2 class="mb-2">{trans key='docs.windows.settings_title'}</h2>
                                    <p class="text-secondary mb-2">{trans key='docs.windows.settings_body'}</p>
                                    <div class="small text-secondary"><i class="ti ti-logout me-1"></i>{trans key='docs.windows.logout_note'}</div>
                                </div>
                            </div>
                            <hr class="my-4">
                            <div class="d-flex align-items-start gap-3">
                                <span class="guide-number">7</span>
                                <div>
                                    <h2 class="mb-2">{trans key='docs.windows.tray_title'}</h2>
                                    <p class="text-secondary mb-2">{trans key='docs.windows.tray_body'}</p>
                                    <p class="small text-secondary mb-2">{trans key='docs.windows.exit_note'}</p>
                                    <p class="small text-secondary mb-0">{trans key='docs.windows.uninstall_note'}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <img class="guide-shot" loading="lazy"
                                 src="/assets/images/docs/windows/settings-{$guideLocale}.png"
                                 alt="{trans key='docs.windows.image_settings_alt'}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="row row-cards mb-4">
                <div class="col-lg-7">
                    <div class="card guide-step-card h-100">
                        <div class="card-header">
                            <h3 class="card-title"><i class="ti ti-lifebuoy me-2 text-primary"></i>{trans key='docs.windows.troubleshooting_title'}</h3>
                        </div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item p-4">
                                <div class="fw-bold mb-1">{trans key='docs.windows.button_issue_title'}</div>
                                <div class="text-secondary">{trans key='docs.windows.button_issue_body'}</div>
                            </div>
                            <div class="list-group-item p-4">
                                <div class="fw-bold mb-1">{trans key='docs.windows.latency_issue_title'}</div>
                                <div class="text-secondary">{trans key='docs.windows.latency_issue_body'}</div>
                            </div>
                            <div class="list-group-item p-4">
                                <div class="fw-bold mb-1">{trans key='docs.windows.login_issue_title'}</div>
                                <div class="text-secondary">{trans key='docs.windows.login_issue_body'}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card guide-step-card h-100">
                        <div class="card-header">
                            <h3 class="card-title"><i class="ti ti-lock me-2 text-primary"></i>{trans key='docs.windows.security_title'}</h3>
                        </div>
                        <div class="card-body p-4">
                            <ul class="security-list text-secondary ps-4 mb-0">
                                <li>{trans key='docs.windows.security_password'}</li>
                                <li>{trans key='docs.windows.security_session'}</li>
                                <li>{trans key='docs.windows.security_local'}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-center pb-4">
                <a class="btn btn-outline-primary btn-lg" href="/user">
                    <i class="ti ti-arrow-left me-1"></i>
                    {trans key='docs.windows.back'}
                </a>
            </div>
        </div>
    </div>

{include file='user/footer.tpl'}
