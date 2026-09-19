import {
    BarController, BarElement, CategoryScale, Chart, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

const PALETTE = ['#2563EB', '#0F766E', '#0891B2', '#B45309', '#B91C1C', '#15803D', '#0F2742', '#475569'];

/**
 * Gráficos declarativos: <canvas data-chart='{"type":"bar","labels":[...],"datasets":[{"label":"...","data":[...]}]}'>.
 * El JSON lo genera Blade con @json (escapado); cada canvas tiene una tabla equivalente accesible.
 */
export default function initCharts() {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = '#475569';

    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        let config;
        try {
            config = JSON.parse(canvas.dataset.chart);
        } catch {
            return;
        }
        const datasets = (config.datasets || []).map((ds, i) => ({
            borderColor: PALETTE[i % PALETTE.length],
            backgroundColor: config.type === 'line' ? `${PALETTE[i % PALETTE.length]}22` : PALETTE[i % PALETTE.length],
            borderWidth: 2,
            tension: 0.3,
            fill: config.type === 'line',
            pointRadius: 2,
            borderRadius: 4,
            ...ds,
        }));

        new Chart(canvas, {
            type: config.type || 'bar',
            data: { labels: config.labels || [], datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: reduceMotion ? false : { duration: 400 },
                indexAxis: config.horizontal ? 'y' : 'x',
                plugins: { legend: { display: datasets.length > 1, position: 'bottom' } },
                scales: {
                    x: { stacked: !!config.stacked, grid: { display: false } },
                    y: { stacked: !!config.stacked, beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
    });
}
