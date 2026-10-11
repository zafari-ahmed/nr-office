import { createIcons, icons } from 'lucide';

// Every Lucide icon is registered so Blade views can use any name via <x-icon name="..."/>.
export function renderIcons() {
    createIcons({ icons, attrs: { 'stroke-width': 1.75 } });
}
