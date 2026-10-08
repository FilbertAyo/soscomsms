import {
  CreditCard,
  FileText,
  KeyRound,
  LayoutDashboard,
  MessageSquare,
  Receipt,
  Send,
  Shield,
  Users,
  Wallet,
  Tag,
  BadgeCheck,
  Activity,
  Building2,
  ScrollText,
  Radio,
} from 'lucide-react'
import { developerApiVisible, webhooksVisible } from '@/lib/features'
import {
  isAdmin,
  needsProfileCompletion,
  type AuthUser,
} from '@/types/auth'
import { type SidebarData } from '../types'

const customerNavGroups: SidebarData['navGroups'] = [
  {
    title: 'Overview',
    items: [
      {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutDashboard,
      },
      {
        title: 'Send SMS',
        url: '/send-sms',
        icon: Send,
      },
      {
        title: 'Templates',
        url: '/templates',
        icon: FileText,
      },
    ],
  },
  {
    title: 'Manage',
    items: [
      {
        title: 'Campaigns',
        icon: MessageSquare,
        items: [
          { title: 'All campaigns', url: '/campaigns' },
          { title: 'Delivery reports', url: '/reports' },
        ],
      },
      {
        title: 'Contacts',
        icon: Users,
        items: [
          { title: 'All contacts', url: '/contacts' },
          { title: 'Groups', url: '/contacts/groups' },
          { title: 'Import', url: '/contacts/import' },
        ],
      },
      {
        title: 'Sender IDs',
        icon: BadgeCheck,
        items: [
          { title: 'My Sender IDs', url: '/sender-ids' },
          { title: 'Request new', url: '/sender-ids/request' },
        ],
      },
      {
        title: 'Wallet',
        icon: Wallet,
        items: [
          { title: 'Balance', url: '/wallet' },
          { title: 'Buy SMS', url: '/wallet/buy' },
          { title: 'Transactions', url: '/wallet/transactions' },
        ],
      },
      ...(developerApiVisible
        ? [
            {
              title: 'Developer',
              icon: KeyRound,
              items: [
                { title: 'API keys', url: '/developer' },
                { title: 'Documentation', url: '/developer/docs' },
                ...(webhooksVisible
                  ? [{ title: 'Webhooks', url: '/developer/webhooks' }]
                  : []),
              ],
            },
          ]
        : []),
    ],
  },
]

const adminNavGroups: SidebarData['navGroups'] = [
  {
    title: 'Admin',
    items: [
      {
        title: 'Overview',
        url: '/admin',
        icon: LayoutDashboard,
      },
      {
        title: 'Customers',
        url: '/admin/customers',
        icon: Building2,
      },
      {
        title: 'SMS Traffic',
        url: '/admin/traffic',
        icon: Activity,
      },
      {
        title: 'Pricing',
        url: '/admin/revenue',
        icon: CreditCard,
      },
      {
        title: 'SMS Provider Balance',
        url: '/admin/provider-balance',
        icon: Radio,
      },
      {
        title: 'Production',
        url: '/admin/production',
        icon: Shield,
      },
      {
        title: 'Sender IDs',
        url: '/admin/sender-id-catalog',
        icon: Tag,
      },
      {
        title: 'Sender ID Requests',
        url: '/admin/sender-ids',
        icon: BadgeCheck,
      },
      {
        title: 'Transactions',
        url: '/admin/transactions',
        icon: Receipt,
      },
      {
        title: 'Payments',
        url: '/admin/payments',
        icon: CreditCard,
      },
      {
        title: 'API Usage',
        url: '/admin/api-usage',
        icon: KeyRound,
      },
      {
        title: 'Delivery Reports',
        url: '/admin/reports',
        icon: FileText,
      },
      {
        title: 'System Logs',
        icon: ScrollText,
        items: [
          { title: 'Audit', url: '/admin/logs/audit' },
          { title: 'Gateway', url: '/admin/logs/gateway' },
        ],
      },
    ],
  },
]

function sidebarUser(user: AuthUser): SidebarData['user'] {
  return {
    name: user.name,
    email: user.email,
    avatar: '',
  }
}

const lockableNavTitles = new Set([
  'Send SMS',
  'Templates',
  'Campaigns',
  'Contacts',
  'Sender IDs',
  'Wallet',
  'Developer',
])

function lockNavItem(item: SidebarData['navGroups'][number]['items'][number]) {
  if (item.items) {
    return {
      ...item,
      locked: true,
      items: item.items.map((subItem) => ({ ...subItem, locked: true })),
    }
  }

  return { ...item, locked: true }
}

function withProfileLocks(
  groups: SidebarData['navGroups'],
  user: AuthUser
): SidebarData['navGroups'] {
  if (!needsProfileCompletion(user)) {
    return groups
  }

  return groups.map((group) => ({
    ...group,
    items: group.items.map((item) =>
      lockableNavTitles.has(item.title) ? lockNavItem(item) : item
    ),
  }))
}

export function getSidebarData(user: AuthUser): SidebarData {
  return {
    user: sidebarUser(user),
    teams: [],
    navGroups: withProfileLocks([...customerNavGroups], user),
  }
}

export function getAdminSidebarData(user: AuthUser): SidebarData {
  return {
    user: sidebarUser(user),
    teams: [],
    navGroups: adminNavGroups,
  }
}

export function sidebarForPath(user: AuthUser, pathname: string): SidebarData {
  if (pathname.startsWith('/admin') && isAdmin(user)) {
    return getAdminSidebarData(user)
  }

  return getSidebarData(user)
}
