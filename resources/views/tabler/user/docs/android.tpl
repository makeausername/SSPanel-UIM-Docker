{include file='user/header.tpl'}

<style>
.eziplc-android-guide .guide-hero {
    border: 0;
    overflow: hidden;
    box-shadow: 0 1rem 2.5rem rgba(20, 62, 130, 0.12);
}

.eziplc-android-guide .guide-hero-accent {
    min-height: 100%;
    color: #fff;
    background: linear-gradient(145deg, #1768e5 0%, #1b9bea 68%, #28b8df 100%);
    position: relative;
    overflow: hidden;
}

.eziplc-android-guide .guide-hero-accent::before,
.eziplc-android-guide .guide-hero-accent::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.09);
}

.eziplc-android-guide .guide-hero-accent::before {
    width: 15rem;
    height: 15rem;
    top: -7rem;
    right: -5rem;
}

.eziplc-android-guide .guide-hero-accent::after {
    width: 9rem;
    height: 9rem;
    bottom: -4rem;
    left: -2rem;
}

.eziplc-android-guide .guide-step-card {
    border: 0;
    box-shadow: 0 0.45rem 1.5rem rgba(31, 51, 89, 0.08);
}

.eziplc-android-guide .guide-number {
    width: 2.25rem;
    height: 2.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: var(--tblr-primary);
    background: var(--tblr-primary-lt);
    font-weight: 700;
    flex: 0 0 auto;
}

.eziplc-android-guide .action-card {
    height: 100%;
    border: 1px solid rgba(32, 107, 196, 0.16);
    border-radius: 0.75rem;
    background: rgba(32, 107, 196, 0.04);
}

.eziplc-android-guide .guide-list li + li {
    margin-top: 0.75rem;
}

.eziplc-android-guide .phone-action {
    width: 3rem;
    height: 3rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: #fff;
    background: var(--tblr-primary);
    box-shadow: 0 0.5rem 1.25rem rgba(32, 107, 196, 0.28);
}

@media (max-width: 991.98px) {
    .eziplc-android-guide .guide-hero-accent {
        min-height: 15rem;
    }
}
</style>

<div class="page-wrapper eziplc-android-guide">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <div class="page-pretitle mb-2">EzIPLC Android</div>
                    <h2 class="page-title">{trans key='docs.android.title'}</h2>
                    <div class="page-pretitle mt-3">{trans key='docs.android.intro'}</div>
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
                                <span class="badge bg-primary-lt">EzIPLC 0.1.3</span>
                                <span class="badge bg-azure-lt">{trans key='docs.android.badge'}</span>
                            </div>
                            <h1 class="display-6 mb-3">{trans key='docs.android.title'}</h1>
                            <p class="text-secondary fs-3 mb-4">{trans key='docs.android.hero_body'}</p>
                            <div class="d-flex flex-column flex-sm-row gap-2">
                                <a class="btn btn-primary btn-lg" href="/downloads/eziplc-android.apk" download>
                                    <i class="ti ti-brand-android me-1"></i>
                                    {trans key='docs.android.download'}
                                </a>
                                <a class="btn btn-outline-primary btn-lg" href="/downloads/eziplc-android.apk.sha256" target="_blank" rel="noopener">
                                    <i class="ti ti-shield-check me-1"></i>
                                    {trans key='docs.android.verify'}
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
                                    <span>{trans key='docs.android.install_title'}</span>
                                </div>
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <span class="badge bg-white text-primary rounded-pill">2</span>
                                    <span>{trans key='docs.android.login_title'}</span>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-white text-primary rounded-pill">3</span>
                                    <span>{trans key='docs.android.connect_title'}</span>
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
                        <h4 class="alert-title">{trans key='docs.android.before_title'}</h4>
                        <div class="text-secondary">{trans key='docs.android.before_body'}</div>
                    </div>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-checklist me-2 text-primary"></i>{trans key='docs.android.requirements_title'}</h3>
                </div>
                <div class="card-body p-4">
                    <ul class="guide-list text-secondary ps-4 mb-0">
                        <li>{trans key='docs.android.requirement_android'}</li>
                        <li>{trans key='docs.android.requirement_account'}</li>
                        <li>{trans key='docs.android.requirement_network'}</li>
                    </ul>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="guide-number">1</span>
                        <div>
                            <h2 class="mb-2">{trans key='docs.android.install_title'}</h2>
                            <p class="text-secondary mb-0">{trans key='docs.android.install_body'}</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="action-card p-4">
                                <div class="fw-bold mb-2">
                                    <i class="ti ti-download me-1 text-primary"></i>
                                    {trans key='docs.android.browser_title'}
                                </div>
                                <div class="text-secondary">{trans key='docs.android.browser_body'}</div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="action-card p-4">
                                <div class="fw-bold mb-2">
                                    <i class="ti ti-settings me-1 text-primary"></i>
                                    {trans key='docs.android.permission_title'}
                                </div>
                                <div class="text-secondary">{trans key='docs.android.permission_body'}</div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <div class="fw-semibold mb-1">{trans key='docs.android.permission_note'}</div>
                        <div>{trans key='docs.android.update_install_note'}</div>
                    </div>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="guide-number">2</span>
                        <div>
                            <h2 class="mb-2">{trans key='docs.android.login_title'}</h2>
                            <p class="text-secondary mb-0">{trans key='docs.android.login_body'}</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="action-card p-4">
                                <div class="fw-bold mb-2"><i class="ti ti-mail me-1 text-primary"></i>{trans key='docs.android.email_title'}</div>
                                <div class="text-secondary">{trans key='docs.android.email_note'}</div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="action-card p-4">
                                <div class="fw-bold mb-2"><i class="ti ti-key me-1 text-primary"></i>{trans key='docs.android.login_once_title'}</div>
                                <div class="text-secondary">{trans key='docs.android.login_once_note'}</div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="action-card p-4">
                                <div class="fw-bold mb-2"><i class="ti ti-refresh me-1 text-primary"></i>{trans key='docs.android.auto_sync_title'}</div>
                                <div class="text-secondary">{trans key='docs.android.auto_sync_note'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="guide-number">3</span>
                        <div>
                            <h2 class="mb-2">{trans key='docs.android.connect_title'}</h2>
                            <p class="text-secondary mb-0">{trans key='docs.android.connect_body'}</p>
                        </div>
                    </div>
                    <div class="row g-3 align-items-stretch">
                        <div class="col-lg-4">
                            <div class="action-card p-4">
                                <div class="phone-action mb-3"><i class="ti ti-list-check fs-2"></i></div>
                                <div class="fw-bold mb-2">{trans key='docs.android.select_line_title'}</div>
                                <div class="text-secondary">{trans key='docs.android.select_line_body'}</div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="action-card p-4">
                                <div class="phone-action mb-3"><i class="ti ti-player-play-filled fs-2"></i></div>
                                <div class="fw-bold mb-2">{trans key='docs.android.vpn_title'}</div>
                                <div class="text-secondary">{trans key='docs.android.vpn_body'}</div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="action-card p-4">
                                <div class="phone-action mb-3"><i class="ti ti-shield-check fs-2"></i></div>
                                <div class="fw-bold mb-2">{trans key='docs.android.connected_title'}</div>
                                <div class="text-secondary">{trans key='docs.android.connected_note'}</div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">{trans key='docs.android.notification_note'}</div>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="guide-number">4</span>
                        <div>
                            <h2 class="mb-2">{trans key='docs.android.switch_title'}</h2>
                            <p class="text-secondary mb-0">{trans key='docs.android.switch_body'}</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="action-card p-4">
                                <div class="fw-bold mb-2"><i class="ti ti-plug-off me-1 text-primary"></i>{trans key='docs.android.disconnect_title'}</div>
                                <div class="text-secondary">{trans key='docs.android.disconnect_body'}</div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="action-card p-4">
                                <div class="fw-bold mb-2"><i class="ti ti-refresh me-1 text-primary"></i>{trans key='docs.android.refresh_title'}</div>
                                <div class="text-secondary">{trans key='docs.android.refresh_body'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-start gap-3">
                        <span class="guide-number">5</span>
                        <div>
                            <h2 class="mb-2">{trans key='docs.android.update_title'}</h2>
                            <p class="text-secondary mb-2">{trans key='docs.android.update_body'}</p>
                            <div class="small text-primary fw-semibold"><i class="ti ti-info-circle me-1"></i>{trans key='docs.android.update_warning'}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card guide-step-card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-lifebuoy me-2 text-primary"></i>{trans key='docs.android.troubleshooting_title'}</h3>
                </div>
                <div class="list-group list-group-flush">
                    <div class="list-group-item p-4">
                        <div class="fw-bold mb-1">{trans key='docs.android.download_issue_title'}</div>
                        <div class="text-secondary">{trans key='docs.android.download_issue_body'}</div>
                    </div>
                    <div class="list-group-item p-4">
                        <div class="fw-bold mb-1">{trans key='docs.android.install_issue_title'}</div>
                        <div class="text-secondary">{trans key='docs.android.install_issue_body'}</div>
                    </div>
                    <div class="list-group-item p-4">
                        <div class="fw-bold mb-1">{trans key='docs.android.login_issue_title'}</div>
                        <div class="text-secondary">{trans key='docs.android.login_issue_body'}</div>
                    </div>
                    <div class="list-group-item p-4">
                        <div class="fw-bold mb-1">{trans key='docs.android.empty_issue_title'}</div>
                        <div class="text-secondary">{trans key='docs.android.empty_issue_body'}</div>
                    </div>
                    <div class="list-group-item p-4">
                        <div class="fw-bold mb-1">{trans key='docs.android.connect_issue_title'}</div>
                        <div class="text-secondary">{trans key='docs.android.connect_issue_body'}</div>
                    </div>
                    <div class="list-group-item p-4">
                        <div class="fw-bold mb-1">{trans key='docs.android.login_again_title'}</div>
                        <div class="text-secondary">{trans key='docs.android.login_again_body'}</div>
                    </div>
                </div>
            </div>

            <div class="row row-cards mb-4">
                <div class="col-lg-6">
                    <div class="card guide-step-card h-100">
                        <div class="card-header">
                            <h3 class="card-title"><i class="ti ti-lock me-2 text-primary"></i>{trans key='docs.android.security_title'}</h3>
                        </div>
                        <div class="card-body p-4">
                            <ul class="guide-list text-secondary ps-4 mb-0">
                                <li>{trans key='docs.android.security_password'}</li>
                                <li>{trans key='docs.android.security_session'}</li>
                                <li>{trans key='docs.android.security_source'}</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card guide-step-card h-100">
                        <div class="card-header">
                            <h3 class="card-title"><i class="ti ti-message-circle-question me-2 text-primary"></i>{trans key='docs.android.support_title'}</h3>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-secondary mb-0">{trans key='docs.android.support_body'}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-center pb-4">
                <a class="btn btn-outline-primary btn-lg" href="/user">
                    <i class="ti ti-arrow-left me-1"></i>
                    {trans key='docs.android.back'}
                </a>
            </div>
        </div>
    </div>

{include file='user/footer.tpl'}
