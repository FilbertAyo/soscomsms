import { AdminHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { Main } from '@/components/layout/main'

const compactTables = [
  '[&_[data-slot=table]]:text-xs',
  '[&_[data-slot=table-head]]:h-8',
  '[&_[data-slot=table-head]]:px-3',
  '[&_[data-slot=table-cell]]:px-3',
  '[&_[data-slot=table-cell]]:py-1.5',
].join(' ')

type AdminPageProps = {
  title: string
  description: string
  actions?: React.ReactNode
  children: React.ReactNode
}

export function AdminPage({
  title,
  description,
  actions,
  children,
}: AdminPageProps) {
  return (
    <>
      <AdminHeader />
      <Main>
        <PageHeader
          title={title}
          description={description}
          actions={actions}
        />
        <div className={compactTables}>{children}</div>
      </Main>
    </>
  )
}
