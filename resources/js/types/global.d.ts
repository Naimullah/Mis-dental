import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            locale: string;
            direction: 'ltr' | 'rtl';
            locales: { code: string; name: string }[];
            translations: Record<string, string>;
            can: {
                manageMasterData: boolean;
                viewPermissionCatalog: boolean;
                managePlatformUsers: boolean;
                manageRoles: boolean;
            };
            localeManagementUrl: string | null;
            [key: string]: unknown;
        };
    }
}
