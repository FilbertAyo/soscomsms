import { AdminHeader } from '@/components/soscom/app-header'
import { PageHeader } from '@/components/soscom/page-header'
import { Main } from '@/components/layout/main'

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
        {children}
      </Main>
    </>
  )
}
