import './bootstrap';
import Alpine from 'alpinejs';
import { lineItems } from './line-items';
import { renderCharts } from './charts';
import { renderIcons } from './icons';

window.Alpine = Alpine;

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
/* Return date = departure + days (weekdays only when weekends aren't counted). Mirrors SiteVisit::returnDateFor. */
function returnDate(departure, days, countWeekends) {
    if (!departure || !days) return null;
    const d = new Date(departure + 'T00:00:00');
    if (countWeekends) {
        d.setDate(d.getDate() + Number(days));
    } else {
        for (let left = Number(days); left > 0;) {
            d.setDate(d.getDate() + 1);
            if (d.getDay() !== 0 && d.getDay() !== 6) left--;
        }
    }
    return d;
}
const longDate = (d) => (d ? d.toLocaleDateString('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }) : '');
const isoToday = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };

/* Site rotation: schedule drawer. */
Alpine.data('visitForm', (defaults) => ({
    departure: defaults.departure,
    days: defaults.days,
    countWeekends: defaults.countWeekends !== false,
    get returnDate() { return longDate(returnDate(this.departure, this.days, this.countWeekends)); },
}));

/* Site rotation: edit drawer, filled from the visit passed to $store.drawer.open('visit-edit', visit). */
Alpine.data('visitEdit', (defaults) => ({
    v: {},
    ended: false,
    today: isoToday(),
    countWeekends: defaults.countWeekends !== false,
    init() {
        this.$watch('$store.drawer.payload', (p) => {
            if (this.$store.drawer.name !== 'visit-edit') return;
            this.v = { ...p };
            this.ended = !!p.returned_on;
        });
    },
    get returnLabel() { return longDate(returnDate(this.v.departure, this.v.days, this.countWeekends)); },
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
