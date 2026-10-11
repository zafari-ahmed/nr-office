import {
    Chart, BarController, BarElement, LineController, LineElement, PointElement,
    CategoryScale, LinearScale, Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);

const PALETTE = {
    teal: '#0d9488', indigo: '#6366f1', amber: '#f59e0b', rose: '#f43f5e',
    sky: '#0ea5e9', violet: '#8b5cf6', emerald: '#10b981', slate: '#64748b',
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
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.color = muted;

    const base = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, padding: 16 } },
            tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: PKR ${Number(ctx.raw).toLocaleString('en-PK')}` } },
        },
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { grid: { color: grid }, border: { display: false }, ticks: { callback: compact } },
        },
    };

    document.querySelectorAll('canvas[data-chart]').forEach((el) => {
        const cfg = JSON.parse(el.dataset.chart);
        if (cfg.type === 'stacked') {
            instances.push(new Chart(el, {
                type: 'bar',
                data: {
                    labels: cfg.labels,
                    datasets: cfg.series.map((s) => ({ label: s.label, data: s.data, backgroundColor: color(s.color), borderRadius: 4, maxBarThickness: 28 })),
                },
                options: { ...base, scales: { x: { ...base.scales.x, stacked: true }, y: { ...base.scales.y, stacked: true } } },
            }));
        } else {
            instances.push(new Chart(el, {
                type: 'line',
                data: {
                    labels: cfg.labels,
                    datasets: cfg.series.map((s) => ({
                        label: s.label, data: s.data, borderColor: color(s.color), backgroundColor: color(s.color) + '22',
                        fill: !!s.fill, tension: 0.35, pointRadius: 3, borderWidth: 2,
                    })),
                },
                options: base,
            }));
        }
    });
}
