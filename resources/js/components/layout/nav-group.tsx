import { type ReactNode } from 'react'
import { Link, useLocation } from '@tanstack/react-router'
import { ChevronRight, Lock } from 'lucide-react'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  useSidebar,
} from '@/components/ui/sidebar'
import { Badge } from '../ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '../ui/dropdown-menu'
import {
  type NavCollapsible,
  type NavItem,
  type NavLink,
  type NavGroup as NavGroupProps,
} from './types'

export function NavGroup({ title, items }: NavGroupProps) {
  const { state, isMobile } = useSidebar()
  const href = useLocation({ select: (location) => location.href })
  return (
    <SidebarGroup className='py-1'>
      {title ? <SidebarGroupLabel className='h-7'>{title}</SidebarGroupLabel> : null}
      <SidebarMenu className='gap-0.5'>
        {items.map((item) => {
          const key = `${item.title}-${item.url ?? 'group'}`

          if (item.locked && item.items) {
            return (
              <SidebarMenuLink
                key={key}
                item={{
                  title: item.title,
                  url: '/settings/business',
                  icon: item.icon,
                  locked: true,
                }}
                href={href}
              />
            )
          }

          if (!item.items)
            return <SidebarMenuLink key={key} item={item} href={href} />

          if (state === 'collapsed' && !isMobile)
            return (
              <SidebarMenuCollapsedDropdown key={key} item={item} href={href} />
            )

          return <SidebarMenuCollapsible key={key} item={item} href={href} />
        })}
      </SidebarMenu>
    </SidebarGroup>
  )
}

function NavBadge({ children }: { children: ReactNode }) {
  return <Badge className='rounded-full px-1 py-0 text-xs'>{children}</Badge>
}

function SidebarMenuLink({ item, href }: { item: NavLink; href: string }) {
  const { setOpenMobile } = useSidebar()
  const to = item.locked ? '/settings/business' : item.url
  return (
    <SidebarMenuItem>
      <SidebarMenuButton
        asChild
        isActive={!item.locked && checkIsActive(href, item)}
        tooltip={
          item.locked
            ? 'Complete your business name and phone first'
            : item.title
        }
      >
        <Link to={to} onClick={() => setOpenMobile(false)}>
          {item.icon && <item.icon />}
          <span>{item.title}</span>
          {item.locked ? (
            <Lock className='ms-auto size-3.5 opacity-70' />
          ) : (
            item.badge && <NavBadge>{item.badge}</NavBadge>
          )}
        </Link>
      </SidebarMenuButton>
    </SidebarMenuItem>
  )
}

function SidebarMenuCollapsible({
  item,
  href,
}: {
  item: NavCollapsible
  href: string
}) {
  const { setOpenMobile } = useSidebar()
  return (
    <Collapsible
      asChild
      defaultOpen={checkIsActive(href, item, true)}
      className='group/collapsible'
    >
      <SidebarMenuItem>
        <CollapsibleTrigger asChild>
          <SidebarMenuButton tooltip={item.title}>
            {item.icon && <item.icon />}
            <span>{item.title}</span>
            {item.badge && <NavBadge>{item.badge}</NavBadge>}
            <ChevronRight className='ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 rtl:rotate-180' />
          </SidebarMenuButton>
        </CollapsibleTrigger>
        <CollapsibleContent className='CollapsibleContent'>
          <SidebarMenuSub>
            {item.items.map((subItem) => (
              <SidebarMenuSubItem key={subItem.title}>
                <SidebarMenuSubButton
                  asChild
                  isActive={!subItem.locked && checkIsActive(href, subItem)}
                >
                  <Link
                    to={subItem.locked ? '/settings/business' : subItem.url}
                    onClick={() => setOpenMobile(false)}
                  >
                    {subItem.icon && <subItem.icon />}
                    <span>{subItem.title}</span>
                    {subItem.locked ? (
                      <Lock className='ms-auto size-3.5 opacity-70' />
                    ) : (
                      subItem.badge && <NavBadge>{subItem.badge}</NavBadge>
                    )}
                  </Link>
                </SidebarMenuSubButton>
              </SidebarMenuSubItem>
            ))}
          </SidebarMenuSub>
        </CollapsibleContent>
      </SidebarMenuItem>
    </Collapsible>
  )
}

function SidebarMenuCollapsedDropdown({
  item,
  href,
}: {
  item: NavCollapsible
  href: string
}) {
  return (
    <SidebarMenuItem>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <SidebarMenuButton
            tooltip={item.title}
            isActive={checkIsActive(href, item)}
          >
            {item.icon && <item.icon />}
            <span>{item.title}</span>
            {item.badge && <NavBadge>{item.badge}</NavBadge>}
            <ChevronRight className='ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90' />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent side='right' align='start' sideOffset={4}>
          <DropdownMenuLabel>
            {item.title} {item.badge ? `(${item.badge})` : ''}
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          {item.items.map((sub) => (
            <DropdownMenuItem key={`${sub.title}-${sub.url}`} asChild>
              <Link
                to={sub.locked ? '/settings/business' : sub.url}
                className={`${checkIsActive(href, sub) ? 'bg-secondary' : ''}`}
              >
                {sub.icon && <sub.icon />}
                <span className='max-w-52 text-wrap'>{sub.title}</span>
                {sub.badge && (
                  <span className='ms-auto text-xs'>{sub.badge}</span>
                )}
              </Link>
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  )
}

function navPath(href: string) {
  return href.split('?')[0]
}

function urlIsActive(href: string, url?: string) {
  if (!url) {
    return false
  }

  const path = navPath(href)
  if (path === url || href === url) {
    return true
  }

  if (url === '/campaigns' && path.startsWith('/campaigns/')) {
    return true
  }

  if (
    url === '/sender-ids' &&
    path.startsWith('/sender-ids/') &&
    path !== '/sender-ids/request'
  ) {
    return true
  }

  return false
}

function checkIsActive(href: string, item: NavItem, mainNav = false) {
  if (urlIsActive(href, item.url)) {
    return true
  }

  if (item.items?.some((child) => checkIsActive(href, child))) {
    return true
  }

  return Boolean(
    mainNav &&
      href.split('/')[1] !== '' &&
      href.split('/')[1] === item.url?.split('/')[1]
  )
}
