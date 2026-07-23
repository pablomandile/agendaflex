/**
 * Cliente HTTP del widget: API pública de Agendaflex (stateless,
 * identificada por slug del tenant + clave pública en header).
 */

export interface CatalogCompany {
    name: string;
    timezone: string;
    currency: string;
    locale: string;
    branding: { primary?: string } | null;
}

export interface CatalogBranch {
    slug: string;
    name: string;
    address: string | null;
    timezone: string;
}

export interface CatalogService {
    uuid: string;
    name: string;
    description: string | null;
    duration_minutes: number;
    price: number;
    max_capacity: number;
    category: string | null;
    employee_uuids: string[];
}

export interface CatalogEmployee {
    uuid: string;
    name: string;
    color: string;
    branch_slug: string;
}

export interface Catalog {
    company: CatalogCompany;
    branches: CatalogBranch[];
    services: CatalogService[];
    employees: CatalogEmployee[];
}

export interface Slot {
    starts_at: string;
    label: string;
    employee_uuid: string;
}

export interface BookingResult {
    booking: {
        uuid: string;
        status: string;
        starts_at: string;
        local_time: string;
        service: string;
        employee: string;
        branch: string;
        price: number;
        currency: string;
    };
    manage_url: string;
}

export interface BookingPayload {
    branch: string;
    service: string;
    employee: string;
    starts_at: string;
    customer: { name: string; email: string; phone?: string | null };
    notes?: string | null;
}

export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
    }
}

export function createApi(options: {
    tenant: string;
    publicKey: string;
    apiBase?: string;
}) {
    const base = (options.apiBase ?? '').replace(/\/+$/, '');

    async function request<T>(path: string, init?: RequestInit): Promise<T> {
        const response = await fetch(
            `${base}/api/v1/${options.tenant}/${path}`,
            {
                ...init,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Public-Key': options.publicKey,
                    ...(init?.headers ?? {}),
                },
            },
        );

        if (!response.ok) {
            const body = (await response.json().catch(() => null)) as {
                message?: string;
            } | null;

            throw new ApiError(
                body?.message ?? `Error ${response.status}`,
                response.status,
            );
        }

        return response.json() as Promise<T>;
    }

    return {
        catalog: () => request<Catalog>('catalog'),

        availability: (params: {
            branch: string;
            service: string;
            employee?: string;
            date: string;
        }) => {
            const query = new URLSearchParams({
                branch: params.branch,
                service: params.service,
                date: params.date,
            });

            if (params.employee) {
                query.set('employee', params.employee);
            }

            return request<Slot[]>(`availability?${query.toString()}`);
        },

        book: (payload: BookingPayload) =>
            request<BookingResult>('bookings', {
                method: 'POST',
                body: JSON.stringify(payload),
            }),
    };
}

export type WidgetApi = ReturnType<typeof createApi>;
