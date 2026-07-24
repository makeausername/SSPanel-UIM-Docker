{include file='user/header.tpl'}

<style>
.shadowrocket-guide .guide-hero,
.shadowrocket-guide .guide-card {
    border: 0;
    box-shadow: 0 0.75rem 2rem rgba(31, 51, 89, 0.09);
}

.shadowrocket-guide .guide-hero {
    overflow: hidden;
}

.shadowrocket-guide .guide-hero-accent {
    min-height: 100%;
    color: #fff;
    background: linear-gradient(145deg, #1768e5 0%, #5b7cfa 58%, #8c67e8 100%);
    position: relative;
    overflow: hidden;
}

.shadowrocket-guide .guide-hero-accent::before,
.shadowrocket-guide .guide-hero-accent::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
}

.shadowrocket-guide .guide-hero-accent::before {
    width: 15rem;
    height: 15rem;
    top: -7rem;
    right: -5rem;
}

.shadowrocket-guide .guide-hero-accent::after {
    width: 9rem;
    height: 9rem;
    bottom: -4rem;
    left: -2rem;
}

.shadowrocket-guide .guide-number {
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

.shadowrocket-guide .guide-flow {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.75rem;
}

.shadowrocket-guide .guide-flow-item,
.shadowrocket-guide .mode-card {
    border: 1px solid rgba(32, 107, 196, 0.16);
    border-radius: 0.75rem;
    background: rgba(32, 107, 196, 0.04);
}

.shadowrocket-guide .mode-card {
    height: 100%;
}

.shadowrocket-guide .subscription-input {
    font-family: var(--tblr-font-monospace);
}

.shadowrocket-guide .security-list li + li {
    margin-top: 0.65rem;
}

@media (max-width: 991.98px) {
    .shadowrocket-guide .guide-hero-accent {
        min-height: 14rem;
    }
}

@media (max-width: 767.98px) {
    .shadowrocket-guide .guide-flow {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-wrapper shadowrocket-guide">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <div class="page-pretitle mb-2">Shadowrocket</div>
                    <h2 class="page-title">{trans key='docs.shadowrocket.title'}</h2>
                    <div class="page-pretitle mt-3">{trans key='docs.shadowrocket.intro'}</div>
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
                                <span class="badge bg-primary-lt">iPhone / iPad</span>
                                <span class="badge bg-azure-lt">Apple Silicon Mac</span>
                            </div>
                            <h1 class="display-6 mb-3">{trans key='docs.shadowrocket.title'}</h1>
                            <p class="text-secondary fs-3 mb-0">{trans key='docs.shadowrocket.hero_body'}</p>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="guide-hero-accent p-4 p-lg-5 d-flex align-items-center">
                            <div class="position-relative z-1">
                                <i class="ti ti-brand-apple display-3 mb-4"></i>
                                <div class="fs-2 fw-bold mb-2">Shadowrocket</div>
                                <div class="opacity-75">{trans key='docs.shadowrocket.platform_note'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning mb-4" role="alert">
                <div class="d-flex">
                    <div class="me-3"><i class="ti ti-alert-triangle fs-2"></i></div>
                    <div>
                        <h4 class="alert-title">{trans key='docs.shadowrocket.disclaimer_title'}</h4>
                        <div class="text-secondary">{trans key='docs.shadowrocket.disclaimer_body'}</div>
                    </div>
                </div>
            </div>

            <div class="card guide-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="guide-number">1</span>
                        <div>
                            <h2 class="mb-2">{trans key='docs.shadowrocket.copy_title'}</h2>
                            <p class="text-secondary mb-0">{trans key='docs.shadowrocket.copy_body'}</p>
                        </div>
                    </div>
                    <div class="d-grid d-sm-flex mb-3">
                        <a class="btn btn-primary btn-lg"
                           href="{$shadowrocketImportUrl|escape:'html'}">
                            <i class="ti ti-device-mobile-down me-1"></i>
                            {trans key='docs.shadowrocket.one_click_button'}
                        </a>
                    </div>
                    <div class="small text-secondary mb-4">
                        <i class="ti ti-tag me-1"></i>{trans key='docs.shadowrocket.one_click_note'}
                    </div>
                    <div class="input-group">
                        <input type="text" class="form-control subscription-input"
                               value="{$subscriptionUrl|escape:'html'}" readonly
                               aria-label="{trans key='docs.shadowrocket.subscription_label'}">
                        <button class="btn btn-primary copy" type="button"
                                data-clipboard-text="{$subscriptionUrl|escape:'html'}">
                            <i class="ti ti-copy me-1"></i>
                            {trans key='docs.shadowrocket.copy_button'}
                        </button>
                    </div>
                    <div class="small text-secondary mt-2">
                        <i class="ti ti-lock me-1"></i>{trans key='docs.shadowrocket.copy_note'}
                    </div>
                </div>
            </div>

            <div class="card guide-card mb-4">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex align-items-start gap-3 mb-4">
                        <span class="guide-number">2</span>
                        <div>
                            <h2 class="mb-2">{trans key='docs.shadowrocket.import_title'}</h2>
                            <p class="text-secondary mb-0">{trans key='docs.shadowrocket.import_body'}</p>
                        </div>
                    </div>
                    <div class="guide-flow">
                        <div class="guide-flow-item p-3">
                            <div class="badge bg-primary text-primary-fg mb-2">1</div>
                            <div class="fw-bold mb-1">{trans key='docs.shadowrocket.import_open_title'}</div>
                            <div class="small text-secondary">{trans key='docs.shadowrocket.import_open_body'}</div>
                        </div>
                        <div class="guide-flow-item p-3">
                            <div class="badge bg-primary text-primary-fg mb-2">2</div>
                            <div class="fw-bold mb-1">{trans key='docs.shadowrocket.import_type_title'}</div>
                            <div class="small text-secondary mb-2">{trans key='docs.shadowrocket.import_type_body'}</div>
                            <span class="badge bg-azure-lt">{trans key='docs.shadowrocket.type_label'}</span>
                        </div>
                        <div class="guide-flow-item p-3">
                            <div class="badge bg-primary text-primary-fg mb-2">3</div>
                            <div class="fw-bold mb-1">{trans key='docs.shadowrocket.import_url_title'}</div>
                            <div class="small text-secondary">{trans key='docs.shadowrocket.import_url_body'}</div>
                        </div>
                        <div class="guide-flow-item p-3">
                            <div class="badge bg-primary text-primary-fg mb-2">4</div>
                            <div class="fw-bold mb-1">{trans key='docs.shadowrocket.import_save_title'}</div>
                            <div class="small text-secondary">{trans key='docs.shadowrocket.import_save_body'}</div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-4 mb-0">
                        <i class="ti ti-language me-1"></i>{trans key='docs.shadowrocket.version_note'}
                    </div>
                </div>
            </div>

            <div class="row row-cards mb-4">
                <div class="col-lg-6">
                    <div class="card guide-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start gap-3">
                                <span class="guide-number">3</span>
                                <div>
                                    <h2 class="mb-2">{trans key='docs.shadowrocket.update_title'}</h2>
                                    <p class="text-secondary mb-0">{trans key='docs.shadowrocket.update_body'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card guide-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start gap-3">
                                <span class="guide-number">4</span>
                                <div>
                                    <h2 class="mb-2">{trans key='docs.shadowrocket.connect_title'}</h2>
                                    <p class="text-secondary mb-2">{trans key='docs.shadowrocket.connect_body'}</p>
                                    <div class="small text-secondary">
                                        <i class="ti ti-shield-lock me-1"></i>{trans key='docs.shadowrocket.permission_note'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card guide-card mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ti ti-route me-2 text-primary"></i>{trans key='docs.shadowrocket.routing_title'}
                    </h3>
                </div>
                <div class="card-body p-4">
                    <p class="text-secondary mb-4">{trans key='docs.shadowrocket.routing_body'}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mode-card p-3">
                                <div class="fw-bold mb-2">{trans key='docs.shadowrocket.routing_config_title'}</div>
                                <div class="text-secondary">{trans key='docs.shadowrocket.routing_config_body'}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mode-card p-3">
                                <div class="fw-bold mb-2">{trans key='docs.shadowrocket.routing_proxy_title'}</div>
                                <div class="text-secondary">{trans key='docs.shadowrocket.routing_proxy_body'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row row-cards mb-4">
                <div class="col-lg-7">
                    <div class="card guide-card h-100">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="ti ti-lifebuoy me-2 text-primary"></i>{trans key='docs.shadowrocket.troubleshooting_title'}
                            </h3>
                        </div>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item p-4">
                                <div class="fw-bold mb-1">{trans key='docs.shadowrocket.empty_title'}</div>
                                <div class="text-secondary">{trans key='docs.shadowrocket.empty_body'}</div>
                            </div>
                            <div class="list-group-item p-4">
                                <div class="fw-bold mb-1">{trans key='docs.shadowrocket.import_issue_title'}</div>
                                <div class="text-secondary">{trans key='docs.shadowrocket.import_issue_body'}</div>
                            </div>
                            <div class="list-group-item p-4">
                                <div class="fw-bold mb-1">{trans key='docs.shadowrocket.connect_issue_title'}</div>
                                <div class="text-secondary">{trans key='docs.shadowrocket.connect_issue_body'}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card guide-card h-100">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="ti ti-lock me-2 text-primary"></i>{trans key='docs.shadowrocket.security_title'}
                            </h3>
                        </div>
                        <div class="card-body p-4">
                            <ul class="security-list text-secondary ps-4 mb-0">
                                <li>{trans key='docs.shadowrocket.security_subscription'}</li>
                                <li>{trans key='docs.shadowrocket.security_source'}</li>
                                <li>{trans key='docs.shadowrocket.security_support'}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-center pb-4">
                <a class="btn btn-outline-primary btn-lg" href="/user">
                    <i class="ti ti-arrow-left me-1"></i>
                    {trans key='docs.shadowrocket.back'}
                </a>
            </div>
        </div>
    </div>

{include file='user/footer.tpl'}
