{include file='user/header.tpl'}

<div class="page-wrapper">
    <div class="container-xl">
        <div class="page-header d-print-none text-white">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <span class="home-title">{trans key='rate.title'}</span>
                    </h2>
                    <div class="page-pretitle my-3">
                        <span class="home-subtitle">{trans key='rate.description'}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-deck row-cards">
                <div class="col-sm-12 col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex">
                                <h3 class="card-title">{trans key='rate.chart_title'}</h3>
                                <div class="ms-auto">
                                    <div class="dropdown">
                                        <a id="dropdown-toggle" class="dropdown-toggle text-secondary" href="#" data-bs-toggle="dropdown"
                                           aria-haspopup="true" aria-expanded="false">{$node_list[0]['name']}</a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            {foreach $node_list as $node}
                                            <a class="dropdown-item" hx-post="/user/rate" hx-swap="none"
                                                hx-vals='{ "node_id": "{$node['id']}" }'>
                                                {$node['name']}
                                            </a>
                                            {/foreach}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="rate-chart"
                                 data-empty-message="{trans key='rate.no_data'}"
                                 data-error-message="{trans key='rate.chart_unavailable'}">
                                <div class="py-5 text-center text-secondary">{trans key='common.loading'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://{$config['jsdelivr_url']}/npm/@tabler/core@1.4.0/dist/libs/apexcharts/dist/apexcharts.min.js"></script>

    <script>
        (() => {
            const chartElement = document.getElementById('rate-chart');
            const dropdownToggle = document.getElementById('dropdown-toggle');
            const initialChartData = {$initial_chart};
            let chart = null;

            const showStatus = (message) => {
                chartElement.replaceChildren();

                const status = document.createElement('div');
                status.className = 'py-5 text-center text-secondary';
                status.textContent = message;
                chartElement.appendChild(status);
            };

            const destroyChart = () => {
                if (chart === null) {
                    return;
                }

                try {
                    chart.destroy();
                } catch (error) {
                    console.error('Traffic rate chart cleanup failed:', error);
                } finally {
                    chart = null;
                }
            };

            const chartOptions = (data) => ({
                chart: {
                    type: "bar",
                    fontFamily: 'inherit',
                    height: 288,
                    parentHeightOffset: 0,
                    toolbar: {
                        show: false,
                    },
                    animations: {
                        enabled: false,
                    },
                },
                plotOptions: {
                    bar: {
                        columnWidth: '70%',
                        borderRadius: 5,
                        dataLabels: {
                            position: 'top'
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    style: {
                        fontSize: '13px',
                    }
                },
                fill: {
                    opacity: 1,
                },
                series: [{
                    name: "{trans key='rate.multiplier'}",
                    data: data
                }],
                tooltip: {
                    theme: 'dark'
                },
                grid: {
                    padding: {
                        top: -20,
                        right: 0,
                        left: -4,
                        bottom: -4
                    },
                    strokeDashArray: 4,
                },
                xaxis: {
                    title: {
                        text: "{trans key='rate.hour'}",
                    },
                    labels: {
                        padding: 0,
                    },
                    tooltip: {
                        enabled: false
                    },
                    axisBorder: {
                        show: false,
                    },
                    categories: ['00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12',
                        '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23'],
                },
                yaxis: {
                    title: {
                        text: "{trans key='rate.multiplier'}",
                        rotate: 0,
                    },
                    labels: {
                        padding: 4,
                    },
                },
                colors: ["#FF0000"],
                legend: {
                    show: false,
                },
            });

            const handleChartFailure = (error) => {
                console.error('Traffic rate chart rendering failed:', error);
                destroyChart();
                showStatus(chartElement.dataset.errorMessage);
            };

            const drawChart = (detail) => {
                const data = detail && Array.isArray(detail.data) ? detail.data : [];

                if (detail && typeof detail.msg === 'string') {
                    dropdownToggle.textContent = detail.msg;
                }

                if (data.length === 0) {
                    destroyChart();
                    showStatus(chartElement.dataset.emptyMessage);
                    return;
                }

                if (!window.ApexCharts) {
                    showStatus(chartElement.dataset.errorMessage);
                    return;
                }

                try {
                    if (chart !== null) {
                        Promise.resolve(chart.updateSeries([{
                            name: "{trans key='rate.multiplier'}",
                            data: data,
                        }])).catch(handleChartFailure);
                        return;
                    }

                    chartElement.replaceChildren();
                    chart = new ApexCharts(chartElement, chartOptions(data));
                    Promise.resolve(chart.render()).catch(handleChartFailure);
                } catch (error) {
                    handleChartFailure(error);
                }
            };

            document.body.addEventListener('drawChart', (event) => drawChart(event.detail));
            drawChart(initialChartData);
        })();
    </script>

{include file='user/footer.tpl'}
