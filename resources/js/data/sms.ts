export const SMS_COST_TZS = 30
export const SMS_SEGMENT_LENGTH = 160
export const LOW_BALANCE_THRESHOLD = 1000

export type SenderIdStatus = 'active' | 'pending' | 'rejected'
export type CampaignStatus =
  | 'draft'
  | 'scheduled'
  | 'sending'
  | 'completed'
  | 'partially_delivered'
  | 'failed'
export type DeliveryStatus = 'delivered' | 'pending' | 'failed' | 'rejected'
export type TransactionStatus = 'successful' | 'pending' | 'failed'
export type TransactionType = 'purchase' | 'usage' | 'refund'
export type ContactStatus = 'active' | 'inactive'
export type SenderRequestStep =
  | 'submitted'
  | 'under_review'
  | 'submitted_to_provider'
  | 'approved'
  | 'active'

export type CurrentUser = {
  name: string
  email: string
  phone: string
  businessName: string
  role: string
  avatar: string
}

export type AccountSummary = {
  smsBalance: number
  sentThisMonth: number
  deliveredThisMonth: number
  failedThisMonth: number
}

export type SenderId = {
  id: string
  name: string
  status: SenderIdStatus
  purpose: string
  organization: string
  requestedAt: string
  currentStep: SenderRequestStep
}

export type Campaign = {
  id: string
  name: string
  senderId: string
  message: string
  recipients: number
  sent: number
  delivered: number
  failed: number
  status: CampaignStatus
  date: string
}

export type Contact = {
  id: string
  name: string
  phone: string
  group: string
  status: ContactStatus
  created: string
}

export type ContactGroup = {
  id: string
  name: string
  contacts: number
  description: string
}

export type DeliveryReport = {
  id: string
  campaignId: string
  campaign: string
  recipient: string
  message: string
  senderId: string
  status: DeliveryStatus
  sentAt: string
  deliveredAt: string | null
}

export type SmsPackage = {
  id: string
  sms: number
  priceTzs: number
  popular?: boolean
}

export type Transaction = {
  id: string
  date: string
  description: string
  sms: number
  amountTzs: number
  status: TransactionStatus
  type: TransactionType
  reference: string
}

export type ApiKey = {
  id: string
  name: string
  maskedKey: string
  created: string
  lastUsed: string
  status: 'active' | 'revoked'
}

export type AdminCustomer = {
  id: string
  name: string
  email: string
  business: string
  smsBalance: number
  sentThisMonth: number
  status: 'active' | 'pending' | 'suspended'
  joined: string
}

export const currentUser: CurrentUser = {
  name: 'Amina Mwamba',
  email: 'amina@linkadi.co.tz',
  phone: '+255 754 221 890',
  businessName: 'Linkadi Ltd',
  role: 'Owner',
  avatar: '',
}

export const account: AccountSummary = {
  smsBalance: 24850,
  sentThisMonth: 12450,
  deliveredThisMonth: 11982,
  failedThisMonth: 468,
}

export const deliveryRate = Number(
  ((account.deliveredThisMonth / account.sentThisMonth) * 100).toFixed(1)
)

export const senderIds: SenderId[] = [
  {
    id: 'sid_mwaliko',
    name: 'MWALIKO',
    status: 'active',
    purpose: 'School fee reminders',
    organization: 'Linkadi Ltd',
    requestedAt: '2026-03-12',
    currentStep: 'active',
  },
  {
    id: 'sid_mchango',
    name: 'MCHANGO',
    status: 'active',
    purpose: 'Contribution collections',
    organization: 'Linkadi Ltd',
    requestedAt: '2026-05-02',
    currentStep: 'active',
  },
  {
    id: 'sid_taarifa',
    name: 'TAARIFA',
    status: 'active',
    purpose: 'Business notifications',
    organization: 'Linkadi Ltd',
    requestedAt: '2026-06-18',
    currentStep: 'active',
  },
  {
    id: 'sid_linkadi',
    name: 'LINKADI',
    status: 'pending',
    purpose: 'Customer updates',
    organization: 'Linkadi Ltd',
    requestedAt: '2026-09-17',
    currentStep: 'under_review',
  },
]

export const campaigns: Campaign[] = [
  {
    id: 'camp_001',
    name: 'School Fee Reminder',
    senderId: 'MWALIKO',
    message:
      'Habari, ada ya shule inapaswa kulipwa kabla ya tarehe 25 Septemba. Asante.',
    recipients: 1250,
    sent: 1250,
    delivered: 1198,
    failed: 52,
    status: 'completed',
    date: '2026-09-12T08:30:00',
  },
  {
    id: 'camp_002',
    name: 'Wedding Invitation',
    senderId: 'TAARIFA',
    message:
      'You are invited to the wedding of Amina & Juma on 3 Oct 2026 at Mlimani City.',
    recipients: 420,
    sent: 420,
    delivered: 406,
    failed: 14,
    status: 'completed',
    date: '2026-09-10T14:15:00',
  },
  {
    id: 'camp_003',
    name: 'Customer Update',
    senderId: 'MCHANGO',
    message: 'Your monthly contribution of TZS 10,000 is due this Friday.',
    recipients: 860,
    sent: 540,
    delivered: 512,
    failed: 8,
    status: 'sending',
    date: '2026-09-17T09:05:00',
  },
  {
    id: 'camp_004',
    name: 'Monthly Announcement',
    senderId: 'TAARIFA',
    message: 'Our offices will be closed on 9 December for Independence Day.',
    recipients: 2100,
    sent: 0,
    delivered: 0,
    failed: 0,
    status: 'scheduled',
    date: '2026-09-20T07:00:00',
  },
  {
    id: 'camp_005',
    name: 'Event Reminder',
    senderId: 'MWALIKO',
    message: 'Reminder: Parents meeting starts at 9:00 AM tomorrow in Hall B.',
    recipients: 980,
    sent: 980,
    delivered: 910,
    failed: 70,
    status: 'partially_delivered',
    date: '2026-09-08T16:40:00',
  },
  {
    id: 'camp_006',
    name: 'Network Maintenance Notice',
    senderId: 'SOSCOM',
    message: 'We will carry out scheduled maintenance tonight from 1:00 to 3:00 AM.',
    recipients: 300,
    sent: 0,
    delivered: 0,
    failed: 300,
    status: 'failed',
    date: '2026-09-05T22:10:00',
  },
]

export const contactGroups: ContactGroup[] = [
  {
    id: 'grp_parents',
    name: 'Parents',
    contacts: 1250,
    description: 'School parents and guardians',
  },
  {
    id: 'grp_customers',
    name: 'Customers',
    contacts: 860,
    description: 'Active paying customers',
  },
  {
    id: 'grp_staff',
    name: 'Staff',
    contacts: 48,
    description: 'Internal staff announcements',
  },
  {
    id: 'grp_wedding',
    name: 'Wedding guests',
    contacts: 420,
    description: 'Invitation list for October wedding',
  },
]

export const contacts: Contact[] = [
  {
    id: 'ct_001',
    name: 'Juma Hassan',
    phone: '+255 712 445 221',
    group: 'Parents',
    status: 'active',
    created: '2026-04-11',
  },
  {
    id: 'ct_002',
    name: 'Neema Mushi',
    phone: '+255 754 889 102',
    group: 'Customers',
    status: 'active',
    created: '2026-05-02',
  },
  {
    id: 'ct_003',
    name: 'Baraka John',
    phone: '+255 765 330 918',
    group: 'Staff',
    status: 'active',
    created: '2026-06-19',
  },
  {
    id: 'ct_004',
    name: 'Grace Kimaro',
    phone: '+255 746 201 554',
    group: 'Wedding guests',
    status: 'active',
    created: '2026-07-08',
  },
  {
    id: 'ct_005',
    name: 'Peter Mallya',
    phone: '+255 713 667 440',
    group: 'Parents',
    status: 'inactive',
    created: '2026-03-22',
  },
  {
    id: 'ct_006',
    name: 'Fatma Ally',
    phone: '+255 758 112 309',
    group: 'Customers',
    status: 'active',
    created: '2026-08-14',
  },
  {
    id: 'ct_007',
    name: 'Daniel Mkude',
    phone: '+255 767 904 218',
    group: 'Parents',
    status: 'active',
    created: '2026-08-29',
  },
  {
    id: 'ct_008',
    name: 'Asha Juma',
    phone: '+255 755 441 873',
    group: 'Customers',
    status: 'active',
    created: '2026-09-01',
  },
]

export const deliveryReports: DeliveryReport[] = [
  {
    id: 'msg_1001',
    campaignId: 'camp_001',
    campaign: 'School Fee Reminder',
    recipient: '+255 712 445 221',
    message:
      'Habari, ada ya shule inapaswa kulipwa kabla ya tarehe 25 Septemba. Asante.',
    senderId: 'MWALIKO',
    status: 'delivered',
    sentAt: '2026-09-12T08:30:12',
    deliveredAt: '2026-09-12T08:30:18',
  },
  {
    id: 'msg_1002',
    campaignId: 'camp_001',
    campaign: 'School Fee Reminder',
    recipient: '+255 754 889 102',
    message:
      'Habari, ada ya shule inapaswa kulipwa kabla ya tarehe 25 Septemba. Asante.',
    senderId: 'MWALIKO',
    status: 'delivered',
    sentAt: '2026-09-12T08:30:13',
    deliveredAt: '2026-09-12T08:30:21',
  },
  {
    id: 'msg_1003',
    campaignId: 'camp_001',
    campaign: 'School Fee Reminder',
    recipient: '+255 765 330 918',
    message:
      'Habari, ada ya shule inapaswa kulipwa kabla ya tarehe 25 Septemba. Asante.',
    senderId: 'MWALIKO',
    status: 'failed',
    sentAt: '2026-09-12T08:30:14',
    deliveredAt: null,
  },
  {
    id: 'msg_1004',
    campaignId: 'camp_002',
    campaign: 'Wedding Invitation',
    recipient: '+255 746 201 554',
    message:
      'You are invited to the wedding of Amina & Juma on 3 Oct 2026 at Mlimani City.',
    senderId: 'TAARIFA',
    status: 'delivered',
    sentAt: '2026-09-10T14:15:04',
    deliveredAt: '2026-09-10T14:15:09',
  },
  {
    id: 'msg_1005',
    campaignId: 'camp_003',
    campaign: 'Customer Update',
    recipient: '+255 758 112 309',
    message: 'Your monthly contribution of TZS 10,000 is due this Friday.',
    senderId: 'MCHANGO',
    status: 'pending',
    sentAt: '2026-09-17T09:05:22',
    deliveredAt: null,
  },
  {
    id: 'msg_1006',
    campaignId: 'camp_003',
    campaign: 'Customer Update',
    recipient: '+255 755 441 873',
    message: 'Your monthly contribution of TZS 10,000 is due this Friday.',
    senderId: 'MCHANGO',
    status: 'rejected',
    sentAt: '2026-09-17T09:05:23',
    deliveredAt: null,
  },
  {
    id: 'msg_1007',
    campaignId: 'camp_005',
    campaign: 'Event Reminder',
    recipient: '+255 713 667 440',
    message: 'Reminder: Parents meeting starts at 9:00 AM tomorrow in Hall B.',
    senderId: 'MWALIKO',
    status: 'delivered',
    sentAt: '2026-09-08T16:40:08',
    deliveredAt: '2026-09-08T16:40:14',
  },
  {
    id: 'msg_1008',
    campaignId: 'camp_006',
    campaign: 'Network Maintenance Notice',
    recipient: '+255 767 904 218',
    message: 'We will carry out scheduled maintenance tonight from 1:00 to 3:00 AM.',
    senderId: 'SOSCOM',
    status: 'failed',
    sentAt: '2026-09-05T22:10:02',
    deliveredAt: null,
  },
]

export const smsPackages: SmsPackage[] = [
  { id: 'pkg_5k', sms: 5000, priceTzs: 150000 },
  { id: 'pkg_10k', sms: 10000, priceTzs: 300000, popular: true },
  { id: 'pkg_50k', sms: 50000, priceTzs: 1500000 },
  { id: 'pkg_100k', sms: 100000, priceTzs: 3000000 },
]

export const transactions: Transaction[] = [
  {
    id: 'txn_001',
    date: '2026-09-14T11:20:00',
    description: 'SMS Purchase',
    sms: 10000,
    amountTzs: 300000,
    status: 'successful',
    type: 'purchase',
    reference: 'TZ-SMS-88421',
  },
  {
    id: 'txn_002',
    date: '2026-09-12T08:30:00',
    description: 'School Fee Reminder',
    sms: -1250,
    amountTzs: 37500,
    status: 'successful',
    type: 'usage',
    reference: 'TZ-SMS-88302',
  },
  {
    id: 'txn_003',
    date: '2026-09-10T14:15:00',
    description: 'Wedding Invitation',
    sms: -420,
    amountTzs: 12600,
    status: 'successful',
    type: 'usage',
    reference: 'TZ-SMS-88110',
  },
  {
    id: 'txn_004',
    date: '2026-08-28T16:04:00',
    description: 'SMS Purchase',
    sms: 5000,
    amountTzs: 150000,
    status: 'pending',
    type: 'purchase',
    reference: 'TZ-SMS-87044',
  },
  {
    id: 'txn_005',
    date: '2026-08-02T09:41:00',
    description: 'Failed campaign refund',
    sms: 300,
    amountTzs: 9000,
    status: 'successful',
    type: 'refund',
    reference: 'TZ-SMS-86019',
  },
]

export const apiKeys: ApiKey[] = [
  {
    id: 'key_001',
    name: 'Production website',
    maskedKey: 'sk_live_••••••••••••••••',
    created: '2026-06-04',
    lastUsed: '2026-09-17',
    status: 'active',
  },
  {
    id: 'key_002',
    name: 'Mobile app',
    maskedKey: 'sk_live_••••••••••••a91c',
    created: '2026-07-22',
    lastUsed: '2026-09-12',
    status: 'active',
  },
]

export const onboardingSteps = [
  { id: 'profile', label: 'Complete your profile', href: '/settings' },
  { id: 'buy', label: 'Buy SMS', href: '/wallet/buy' },
  { id: 'send', label: 'Send your first SMS', href: '/send-sms' },
] as const

export type OnboardingStepId = (typeof onboardingSteps)[number]['id']

export const adminOverview = {
  customers: 186,
  smsTraffic: 842310,
  revenueTzs: 21450000,
  providerBalance: 1250000,
  pendingSenderIds: 7,
  failedDeliveries: 1244,
}

export const adminCustomers: AdminCustomer[] = [
  {
    id: 'cus_001',
    name: 'Amina Mwamba',
    email: 'amina@linkadi.co.tz',
    business: 'Linkadi Ltd',
    smsBalance: 24850,
    sentThisMonth: 12450,
    status: 'active',
    joined: '2026-03-12',
  },
  {
    id: 'cus_002',
    name: 'Joseph Mushi',
    email: 'joseph@mchango.co.tz',
    business: 'Mchango Group',
    smsBalance: 850,
    sentThisMonth: 4200,
    status: 'active',
    joined: '2026-04-03',
  },
  {
    id: 'cus_003',
    name: 'Sarah Kimaro',
    email: 'sarah@taarifa.or.tz',
    business: 'Taarifa NGO',
    smsBalance: 0,
    sentThisMonth: 110,
    status: 'pending',
    joined: '2026-09-16',
  },
  {
    id: 'cus_004',
    name: 'Hassan Ally',
    email: 'hassan@mwaliko.ac.tz',
    business: 'Mwaliko Academy',
    smsBalance: 54000,
    sentThisMonth: 22100,
    status: 'active',
    joined: '2026-01-19',
  },
  {
    id: 'cus_005',
    name: 'Rehema Nchimbi',
    email: 'rehema@eventz.co.tz',
    business: 'Eventz TZ',
    smsBalance: 3200,
    sentThisMonth: 0,
    status: 'suspended',
    joined: '2026-02-27',
  },
]

export const adminSenderRequests = [
  {
    id: 'req_001',
    senderId: 'LINKADI',
    customer: 'Linkadi Ltd',
    purpose: 'Customer updates',
    date: '2026-09-17',
    status: 'under_review' as SenderRequestStep,
  },
  {
    id: 'req_002',
    senderId: 'SHULE',
    customer: 'Mwaliko Academy',
    purpose: 'School announcements',
    date: '2026-09-15',
    status: 'submitted_to_provider' as SenderRequestStep,
  },
  {
    id: 'req_003',
    senderId: 'HUDUMA',
    customer: 'Taarifa NGO',
    purpose: 'Service alerts',
    date: '2026-09-10',
    status: 'submitted' as SenderRequestStep,
  },
]

export const systemLogs = [
  {
    id: 'log_001',
    time: '2026-09-17T21:14:00',
    level: 'info' as const,
    message: 'Campaign camp_003 started sending to 860 recipients.',
  },
  {
    id: 'log_002',
    time: '2026-09-17T18:02:00',
    level: 'warning' as const,
    message: 'Provider latency above 2s for 3 minutes.',
  },
  {
    id: 'log_003',
    time: '2026-09-16T11:40:00',
    level: 'info' as const,
    message: 'Sender ID LINKADI submitted for review.',
  },
  {
    id: 'log_004',
    time: '2026-09-15T08:22:00',
    level: 'error' as const,
    message: 'Delivery callback failed for message msg_1003.',
  },
]

export const faqs = [
  {
    category: 'Sending SMS',
    items: [
      {
        q: 'How do I send an SMS?',
        a: 'Open Send SMS, choose a Sender ID, add Tanzanian numbers, type your message, then send.',
      },
      {
        q: 'Why does a long message use more than 1 SMS?',
        a: 'A long message is split into parts. Each part uses 1 SMS per person. Some letters and symbols make each part shorter, so a long message can use more SMS.',
      },
      {
        q: 'When are credits deducted?',
        a: 'SMS is taken from your balance when you confirm send. If a number cannot be sent, that SMS is returned. If we do not get a send result, it stays as not confirmed and is not returned. If a phone later fails to receive the message, the SMS is not returned.',
      },
      {
        q: 'What does Sent mean on a delivery report?',
        a: 'Sent means the SMS was accepted by the network. It does not mean the phone received it. A delivery report may still arrive later and change the status to Delivered or Failed.',
      },
      {
        q: 'What does Not confirmed mean on a delivery report?',
        a: 'Not confirmed means we have no delivery report yet. The SMS may still have reached the phone. If a later confirmation arrives, the status can change to Delivered or Failed.',
      },
    ],
  },
  {
    category: 'SMS Balance',
    items: [
      {
        q: 'How do I buy more SMS?',
        a: 'Go to Buy SMS, enter how many SMS you need (from 100), pay the quoted amount to Lipa namba 123456, then submit the mobile number you paid with. Credits appear after Soscom confirms the payment.',
      },
      {
        q: 'Do unused SMS expire?',
        a: 'SMS credits stay in your account until you use them.',
      },
    ],
  },
  {
    category: 'Sender IDs',
    items: [
      {
        q: 'What is a Sender ID?',
        a: 'A Sender ID is the name people see when they receive your SMS, such as HARUSI or MWALIKO. Shared SOSCOM names are ready to use. A custom name needs Soscom review.',
      },
      {
        q: 'How long does approval take?',
        a: 'Most Sender ID requests are reviewed within 1–3 working days. Follow progress on My Sender IDs.',
      },
    ],
  },
  {
    category: 'API',
    items: [
      {
        q: 'How do I connect my application?',
        a: 'Create an API key under Developer, then call https://sms.soscom.co.tz/api/v1 with that key. Your application does not need its IP whitelisted. See API Documentation for request examples.',
      },
    ],
  },
  {
    category: 'Payments',
    items: [
      {
        q: 'Which payment methods can I use?',
        a: 'Pay the quoted amount to Lipa namba 123456 (mobile money), then submit the name or number you paid with. Soscom confirms the payment before credits are added.',
      },
    ],
  },
  {
    category: 'Account',
    items: [
      {
        q: 'How do I change my password?',
        a: 'Open Settings, then Password. Enter your current password and choose a new one.',
      },
    ],
  },
]
