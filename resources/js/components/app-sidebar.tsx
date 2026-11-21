import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Coins,
    FolderGit2,
    Globe,
    Languages,
    LayoutGrid,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTrans } from '@/lib/i18n';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

function NavGroup({ label, items }: { label: string; items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href, undefined, true)}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

export function AppSidebar() {
    const { can, localeManagementUrl } = usePage().props;
    const { t, isRtl } = useTrans();

    const mainNavItems: NavItem[] = [
        {
            title: t('Dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    const masterDataItems: NavItem[] = can?.manageMasterData
        ? [
              {
                  title: t('Countries'),
                  href: '/platform/master-data/countries',
                  icon: Globe,
              },
              {
                  title: t('Currencies'),
                  href: '/platform/master-data/currencies',
                  icon: Coins,
              },
          ]
        : [];

    if (localeManagementUrl) {
        masterDataItems.push({
            title: t('Locales'),
            href: localeManagementUrl,
            icon: Languages,
        });
    }

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            side={isRtl ? 'right' : 'left'}
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {masterDataItems.length > 0 && (
                    <section aria-label={t('Platform Admin')}>
                        {localeManagementUrl && (
                            <p className="px-4 pt-2 text-sm font-medium group-data-[collapsible=icon]:hidden">
                                {t('Platform Admin')}
                            </p>
                        )}
                        <NavGroup
                            label={t('Master Data')}
                            items={masterDataItems}
                        />
                    </section>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <LocaleSwitcher />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
