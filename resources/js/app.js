import './bootstrap';
import Alpine from 'alpinejs';
import { lineItems } from './line-items';
import { renderCharts } from './charts';
import { renderIcons } from './icons';

window.Alpine = Alpine;

/* Theme: remembered per browser, defaults to the OS preference. */
Alpine.store('theme', {
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        try { localStorage.setItem('nr-theme', this.dark ? 'dark' : 'light'); } catch (e) {}
        renderCharts();
    },
});

/* Sidebar collapse, remembered per browser. */
Alpine.store('sidebar', {
    collapsed: (() => { try { return localStorage.getItem('nr-sidebar') === '1'; } catch (e) { return false; } })(),
    mobileOpen: false,
    toggle() {
        this.collapsed = !this.collapsed;
        const root = document.documentElement;
        root.classList.add('sb-anim');
        root.classList.toggle('sb-collapsed', this.collapsed);
        try { localStorage.setItem('nr-sidebar', this.collapsed ? '1' : '0'); } catch (e) {}
        setTimeout(() => { root.classList.remove('sb-anim'); renderCharts(); }, 220);
    },
});

/* Right-side drawers: open by name from anywhere with $dispatch('drawer', 'name'). */
Alpine.store('drawer', {
    name: null,
    payload: {},
    open(name, payload = {}) { this.name = name; this.payload = payload; },
    close() { this.name = null; },
});

/* Toasts. */
Alpine.store('toasts', {
    items: [],
    push(message, tone = 'success') {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, tone });
        setTimeout(() => this.remove(id), 5000);
    },
    remove(id) { this.items = this.items.filter((t) => t.id !== id); },
});

/* Ctrl+K command palette backed by /search JSON. */
Alpine.data('palette', () => ({
    open: false,
    q: '',
    results: [],
    active: 0,
    timer: null,
    init() {
        window.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.show(); }
            if (e.key === 'Escape') this.open = false;
        });
    },
    show() { this.open = true; this.$nextTick(() => this.$refs.input?.focus()); },
    search() {
        clearTimeout(this.timer);
        if (this.q.trim().length < 2) { this.results = []; return; }
        this.timer = setTimeout(async () => {
            const { data } = await window.axios.get('/search', { params: { q: this.q }, headers: { Accept: 'application/json' } });
            this.results = data;
            this.active = 0;
        }, 180);
    },
    move(step) { this.active = Math.max(0, Math.min(this.results.length - 1, this.active + step)); },
    go() {
        const hit = this.results[this.active];
        window.location = hit ? hit.url : `/search?q=${encodeURIComponent(this.q)}`;
    },
}));

/* Site rotation drawer: return date = departure + days. */
Alpine.data('visitForm', (defaults) => ({
    departure: defaults.departure,
    days: defaults.days,
    countWeekends: defaults.countWeekends !== false,
    get returnDate() {
        if (!this.departure || !this.days) return '';
        const d = new Date(this.departure + 'T00:00:00');
        if (this.countWeekends) {
            d.setDate(d.getDate() + Number(this.days));
        } else {
            for (let left = Number(this.days); left > 0;) {
                d.setDate(d.getDate() + 1);
                if (d.getDay() !== 0 && d.getDay() !== 6) left--;
            }
        }
        return d.toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
    },
}));

/* Drag-and-drop multi-file input. */
Alpine.data('dropzone', () => ({
    files: [],
    over: false,
    pick(e) { this.files = Array.from(e.target.files); },
    drop(e) {
        this.over = false;
        const dt = new DataTransfer();
        Array.from(e.dataTransfer.files).forEach((f) => dt.items.add(f));
        this.$refs.input.files = dt.files;
        this.files = Array.from(dt.files);
    },
    size(bytes) { return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.ceil(bytes / 1024) + ' KB'; },
}));

Alpine.data('lineItems', lineItems);
window.lineItems = lineItems;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    renderIcons();
    renderCharts();
});
