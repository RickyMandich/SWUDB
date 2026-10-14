<div id="statistiche" class="pt-8 border-t border-gray-200 dark:border-gray-700 space-y-6">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Statistiche del mazzo</h3>
        <span class="text-xs text-gray-500 dark:text-gray-400">Leader e base esclusi</span>
    </div>

    @if ($total === 0)
        <x-bladewind.alert type="info">Il mazzo non ha ancora carte oltre a leader e base: niente da misurare.</x-bladewind.alert>
    @else
        <div class="grid gap-6 md:grid-cols-2">
            {{-- 1. Curva dei costi con Toggle interattivi per tipo carta --}}
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700"
                x-data="deckCostCurveManager(@js($costCurve->keys()), @js($costCurveByType))">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">Curva dei costi</h4>
                    <div class="flex items-center gap-1.5">
                        <template x-for="t in ['Unità', 'Miglioria', 'Evento']" :key="t">
                            <button type="button" @click="toggle(t)" class="cursor-pointer select-none">
                                <x-badge
                                    x-bind:style="active.includes(t) ? 'background-color: #6366f1; color: #ffffff;' : 'background-color: #9ca3af; color: #ffffff; opacity: 0.4;'"
                                    class="transition-all hover:scale-105">
                                    <span x-text="t"></span>
                                </x-badge>
                            </button>
                        </template>
                    </div>
                </div>
                <div class="relative h-64">
                    <canvas id="cost-curve-chart"></canvas>
                </div>
            </x-bladewind.card>

            {{-- 2. Potenza e Vita medie per costo (grafico line/diffusione) --}}
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">Potenza e Vita medie per costo</h4>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Solo unità</span>
                </div>
                <div class="relative h-64">
                    @if (empty($powerHealthByCost))
                        <div class="flex h-full items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                            Nessuna unità presente nel mazzo.
                        </div>
                    @else
                        <canvas id="power-health-chart"></canvas>
                    @endif
                </div>
            </x-bladewind.card>

            {{-- 3. Grafico a torta degli Aspetti (fette zebrate per carte multi-aspetto) --}}
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">Ripartizione Aspetti</h4>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Fette zebrate per carte multi-aspetto</span>
                </div>
                <div class="relative h-64 flex items-center justify-center">
                    <canvas id="aspects-pie-chart"></canvas>
                </div>
            </x-bladewind.card>

            {{-- 4. Grafico a torta delle Rarità con colori specificati --}}
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">Ripartizione Rarità</h4>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Palette ufficiale</span>
                </div>
                <div class="relative h-64 flex items-center justify-center">
                    <canvas id="rarity-pie-chart"></canvas>
                </div>
            </x-bladewind.card>
        </div>

        @if ($deckTraits->isNotEmpty())
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <h4 class="mb-3 font-semibold text-gray-800 dark:text-gray-100">Tratti più presenti</h4>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($deckTraits->take(10) as $name => $count)
                        <li class="flex items-center justify-between py-2 text-sm text-gray-800 dark:text-gray-100">
                            <span>{{ $name }}</span>
                            <span class="text-gray-500 dark:text-gray-400 font-medium">×{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-bladewind.card>
        @endif

        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
        <script>
            /**
             * Genera un pattern zebrato a strisce diagonali per carte multi-aspetto
             */
            function createZebraPattern(colors) {
                if (!colors || colors.length === 0) return '#9ca3af';
                if (colors.length === 1) return colors[0];

                const stripeWidth = 14;
                const n = colors.length;
                const patternCanvas = document.createElement('canvas');
                const size = stripeWidth * n * 2;
                patternCanvas.width = size;
                patternCanvas.height = size;
                const pctx = patternCanvas.getContext('2d');

                pctx.save();
                pctx.translate(size / 2, size / 2);
                pctx.rotate((45 * Math.PI) / 180);
                pctx.translate(-size, -size);

                for (let x = -size; x < size * 3; x += stripeWidth * n) {
                    for (let i = 0; i < n; i++) {
                        pctx.fillStyle = colors[i];
                        pctx.fillRect(x + i * stripeWidth, -size, stripeWidth, size * 4);
                    }
                }
                pctx.restore();

                return pctx.createPattern(patternCanvas, 'repeat');
            }

            // Gestore Alpine per la curva dei costi interattiva
            document.addEventListener('alpine:init', () => {
                Alpine.data('deckCostCurveManager', (costLabels, byType) => ({
                    active: ['Unità', 'Miglioria', 'Evento'],
                    chart: null,
                    labels: costLabels,
                    byType: byType,

                    init() {
                        this.$nextTick(() => {
                            window.addEventListener('cost-curve-chart-ready', (e) => {
                                this.chart = e.detail;
                                this.update();
                            });
                        });
                    },

                    toggle(type) {
                        if (this.active.includes(type)) {
                            this.active = this.active.filter(t => t !== type);
                        } else {
                            this.active.push(type);
                        }
                        this.update();
                    },

                    update() {
                        if (!this.chart) return;
                        const combined = this.labels.map((_, i) => {
                            return this.active.reduce((sum, t) => sum + (this.byType[t]?.[i] || 0), 0);
                        });
                        this.chart.data.datasets[0].data = combined;
                        this.chart.update();
                    }
                }));
            });

            (() => {
                const initStatisticsCharts = () => {
                    if (typeof Chart === 'undefined') return;

                    const dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    Chart.defaults.color = dark ? '#d1d5db' : '#374151';
                    const gridColor = dark ? '#374151' : '#e5e7eb';
                    const borderColor = dark ? '#1f2937' : '#ffffff';

                    // 1. Curva dei costi (Bar Chart)
                    const costEl = document.getElementById('cost-curve-chart');
                    if (costEl && !costEl._chartInstance) {
                        const costChart = new Chart(costEl, {
                            type: 'bar',
                            data: {
                                labels: @json($costCurve->keys()),
                                datasets: [{
                                    data: @json($costCurve->values()),
                                    backgroundColor: '#6366f1',
                                    borderRadius: 4,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { grid: { color: gridColor } },
                                    y: {
                                        beginAtZero: true,
                                        ticks: { precision: 0 },
                                        grid: { color: gridColor }
                                    }
                                }
                            }
                        });
                        costEl._chartInstance = costChart;
                        window.dispatchEvent(new CustomEvent('cost-curve-chart-ready', { detail: costChart }));
                    }

                    // 2. Potenza e Vita medie per costo (Line Chart: rossa potenza, blu vita)
                    const phEl = document.getElementById('power-health-chart');
                    const phData = @json($powerHealthByCost);
                    if (phEl && phData && phData.length > 0 && !phEl._chartInstance) {
                        phEl._chartInstance = new Chart(phEl, {
                            type: 'line',
                            data: {
                                labels: phData.map(d => 'Costo ' + d.cost),
                                datasets: [
                                    {
                                        label: 'Potenza media',
                                        data: phData.map(d => d.power),
                                        borderColor: '#ef4444',
                                        backgroundColor: '#ef4444',
                                        tension: 0.15,
                                        pointRadius: 5,
                                        pointHoverRadius: 7,
                                    },
                                    {
                                        label: 'Vita media',
                                        data: phData.map(d => d.health),
                                        borderColor: '#3b82f6',
                                        backgroundColor: '#3b82f6',
                                        tension: 0.15,
                                        pointRadius: 5,
                                        pointHoverRadius: 7,
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                        labels: { boxWidth: 12, font: { size: 12 } }
                                    }
                                },
                                scales: {
                                    x: { grid: { color: gridColor } },
                                    y: {
                                        beginAtZero: true,
                                        grid: { color: gridColor }
                                    }
                                }
                            }
                        });
                    }

                    // 3. Aspetti delle carte (Pie Chart con fette zebrate)
                    const aspectEl = document.getElementById('aspects-pie-chart');
                    const aspectData = @json($aspectStats);
                    if (aspectEl && aspectData && aspectData.length > 0 && !aspectEl._chartInstance) {
                        const bgColors = aspectData.map(item => createZebraPattern(item.colors));
                        aspectEl._chartInstance = new Chart(aspectEl, {
                            type: 'pie',
                            data: {
                                labels: aspectData.map(item => item.label),
                                datasets: [{
                                    data: aspectData.map(item => item.count),
                                    backgroundColor: bgColors,
                                    borderColor: borderColor,
                                    borderWidth: 2,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'right',
                                        labels: {
                                            boxWidth: 14,
                                            font: { size: 11 }
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // 4. Rarità delle carte (Pie Chart con colori ufficiali specificati)
                    const rarityEl = document.getElementById('rarity-pie-chart');
                    const rarityData = @json($rarityStats);
                    if (rarityEl && rarityData && rarityData.length > 0 && !rarityEl._chartInstance) {
                        rarityEl._chartInstance = new Chart(rarityEl, {
                            type: 'pie',
                            data: {
                                labels: rarityData.map(item => item.label),
                                datasets: [{
                                    data: rarityData.map(item => item.count),
                                    backgroundColor: rarityData.map(item => item.color),
                                    borderColor: borderColor,
                                    borderWidth: 2,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'right',
                                        labels: {
                                            boxWidth: 14,
                                            font: { size: 11 }
                                        }
                                    }
                                }
                            }
                        });
                    }
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initStatisticsCharts);
                } else {
                    initStatisticsCharts();
                }
            })();
        </script>
    @endif
</div>
