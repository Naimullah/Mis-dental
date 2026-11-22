import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    BookOpen,
    CalendarDays,
    Coins,
    FolderGit2,
    Globe,
    IdCard,
    Languages,
    LayoutGrid,
    MapPinned,
    Percent,
    ShieldCheck,
    UserCog,
    Users,
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
              {
                  title: t('Administrative areas'),
                  href: '/platform/master-data/administrative-areas',
                  icon: MapPinned,
              },
              {
                  title: t('Public holidays'),
                  href: '/platform/master-data/public-holidays',
                  icon: CalendarDays,
              },
              {
                  title: t('License types'),
                  href: '/platform/master-data/license-types',
                  icon: BadgeCheck,
              },
              {
                  title: t('Identity document types'),
                  href: '/platform/master-data/identity-document-types',
                  icon: IdCard,
              },
              {
                  title: t('Tax codes'),
                  href: '/platform/master-data/tax-codes',
                  icon: Percent,
              },
          ]
        : [];

    const securityItems: NavItem[] = can?.viewPermissionCatalog
        ? [
              {
                  title: t('Permissions'),
                  href: '/platform/security/permissions',
                  icon: ShieldCheck,
              },
          ]
        : [];

    if (can?.managePlatformUsers) {
        securityItems.push({
            title: t('Users'),
            href: '/platform/security/users',
            icon: Users,
        });
    }

    if (can?.manageRoles) {
        securityItems.push({
            title: t('Roles'),
            href: '/platform/security/roles',
            icon: UserCog,
        });
    }

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
                {(masterDataItems.length > 0 || securityItems.length > 0) && (
                    <section aria-label={t('Platform Admin')}>
                        {(localeManagementUrl || securityItems.length > 0) && (
                            <p className="px-4 pt-2 text-sm font-medium group-data-[collapsible=icon]:hidden">
                                {t('Platform Admin')}
                            </p>
                        )}
                        {masterDataItems.length > 0 && (
                            <NavGroup
                                label={t('Master Data')}
                                items={masterDataItems}
                            />
                        )}
                        {securityItems.length > 0 && (
                            <NavGroup
                                label={t('Security')}
                                items={securityItems}
                            />
                        )}
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
