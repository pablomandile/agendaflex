import Aura from '@primeuix/themes/aura';
import PrimeVue from 'primevue/config';
import { createApp } from 'vue';
import type { App } from 'vue';
import Widget from './Widget.vue';

export type AgendaflexOptions = {
    /** Slug público de la empresa (tenant). */
    tenant: string;
    /** Clave pública de la empresa (pk_...). */
    publicKey: string;
    /** Base de la API, ej. https://agendaflex.test — por defecto el origen actual. */
    apiBase?: string;
    /** Sucursal preseleccionada (slug), opcional. */
    branch?: string;
};

/**
 * Monta el widget de reservas dentro del elemento indicado.
 * Uso: Agendaflex.mount('#agendaflex', { tenant: 'salon-x', publicKey: 'pk_...' })
 */
function mount(el: string | HTMLElement, options: AgendaflexOptions): App {
    const target =
        typeof el === 'string' ? document.querySelector<HTMLElement>(el) : el;

    if (!target) {
        throw new Error(`[Agendaflex] No se encontró el elemento "${el}"`);
    }

    const app = createApp(Widget, { ...options });

    app.use(PrimeVue, {
        theme: {
            preset: Aura,
            options: {
                prefix: 'p',
                // El tema del widget lo controla el sitio anfitrión vía prefers-color-scheme
                darkModeSelector: false,
                cssLayer: false,
            },
        },
    });

    app.mount(target);

    return app;
}

declare global {
    interface Window {
        Agendaflex: { mount: typeof mount };
    }
}

window.Agendaflex = { mount };

export { mount };
