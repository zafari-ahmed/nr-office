import {
    Chart, BarController, BarElement, LineController, LineElement, PointElement,
    CategoryScale, LinearScale, Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);

// Chart colours from the design system (c1 to c5); older head colour names map onto them.
const PALETTE = {
    teal: '#0e6b69', blue: '#5b8fd6', amber: '#e0a33a', grey: '#9aa3ad', pink: '#c97b9b',
    indigo: '#5b8fd6', sky: '#5b8fd6', slate: '#9aa3ad', rose: '#c97b9b', violet: '#c97b9b', emerald: '#0e6b69',
};
export const color = (name) => PALETTE[name] || name || PALETTE.slate;

const instances = [];

function compact(v) {
    if (Math.abs(v) >= 1e7) return (v / 1e7).toFixed(1) + ' Cr';
    if (Math.abs(v) >= 1e5) return (v / 1e5).toFixed(1) + ' L';
    if (Math.abs(v) >= 1e3) return (v / 1e3).toFixed(0) + 'K';
    return v;
}

export function renderCharts() {
    instances.splice(0).forEach((c) => c.destroy());
    const style = getComputedStyle(document.documentElement);
    const muted = style.getPropertyValue('--muted').trim();
    const grid = style.getPropertyValue('--border').trim();
    Chart.defaults.font.family = 'Geist, system-ui, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = muted;

    const base = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', align: 'start', labels: { boxWidth: 10, boxHeight: 10, padding: 14, color: style.getPropertyValue('--text-2').trim() } },
            tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: PKR ${Number(ctx.raw).toLocaleString('en-PK')}` } },
        },
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { grid: { color: grid, borderDash: [3, 3] }, border: { display: false, dash: [3, 3] }, ticks: { callback: compact, maxTicksLimit: 4 } },
        },
    };

    document.querySelectorAll('canvas[data-chart]').forEach((el) => {
        const cfg = JSON.parse(el.dataset.chart);
        if (cfg.type === 'stacked') {
            instances.push(new Chart(el, {
                type: 'bar',
                data: {
                    labels: cfg.labels,
                    datasets: cfg.series.map((s) => ({ label: s.label, data: s.data, backgroundColor: color(s.color), borderRadius: 2, borderColor: style.getPropertyValue('--surface').trim(), borderWidth: { top: 1 }, maxBarThickness: 40 })),
                },
                options: { ...base, scales: { x: { ...base.scales.x, stacked: true }, y: { ...base.scales.y, stacked: true } } },
            }));
        } else {
            instances.push(new Chart(el, {
                type: 'line',
                data: {
                    labels: cfg.labels,
                    datasets: cfg.series.map((s) => ({
                        label: s.label, data: s.data, borderColor: color(s.color), backgroundColor: color(s.color) + '14',
                        fill: !!s.fill, tension: 0, pointRadius: 0, pointStyle: 'line', pointHoverRadius: 4, borderWidth: 2,
                    })),
                },
                options: { ...base, plugins: { ...base.plugins, legend: { ...base.plugins.legend, labels: { ...base.plugins.legend.labels, usePointStyle: true, boxWidth: 18 } } } },
            }));
        }
    });
}
