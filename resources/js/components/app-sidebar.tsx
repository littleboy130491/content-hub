import { Link, usePage } from '@inertiajs/react';
import {
    FolderKanban,
    LayoutGrid,
    Library,
    MessagesSquare,
    Settings2,
    Sparkles,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import chats from '@/routes/chats';
import context from '@/routes/context';
import outputs from '@/routes/outputs';
import projects from '@/routes/projects';
import workspace from '@/routes/workspace';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';

    const team = page.props.currentTeam?.slug;

    const mainNavItems: NavItem[] = team
        ? [
              { title: 'Dashboard', href: dashboardUrl, icon: LayoutGrid },
              { title: 'Chats', href: chats.index(team), icon: MessagesSquare },
              {
                  title: 'Projects',
                  href: projects.index(team),
                  icon: FolderKanban,
              },
              {
                  title: 'Context library',
                  href: context.index(team),
                  icon: Library,
              },
              { title: 'Outputs', href: outputs.index(team), icon: Sparkles },
              {
                  title: 'Workspace settings',
                  href: workspace.settings(team),
                  icon: Settings2,
              },
          ]
        : [{ title: 'Dashboard', href: dashboardUrl, icon: LayoutGrid }];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
