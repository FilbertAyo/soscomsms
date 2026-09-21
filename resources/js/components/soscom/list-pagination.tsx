import { Button } from '@/components/ui/button'
import { type PageMeta } from '@/types/pagination'

function visiblePages(current: number, last: number): number[] {
  if (last <= 7) {
    return Array.from({ length: last }, (_, index) => index + 1)
  }

  const pages = new Set<number>([1, last, current, current - 1, current + 1])
  if (current <= 3) {
    pages.add(2)
    pages.add(3)
    pages.add(4)
  }
  if (current >= last - 2) {
    pages.add(last - 1)
    pages.add(last - 2)
    pages.add(last - 3)
  }

  return [...pages].filter((page) => page >= 1 && page <= last).sort((a, b) => a - b)
}

export function ListPagination({
  meta,
  onPageChange,
}: {
  meta: PageMeta
  onPageChange: (page: number) => void
}) {
  if (meta.total <= 0) {
    return null
  }

  const pages = visiblePages(meta.currentPage, meta.lastPage)
  const from = (meta.currentPage - 1) * meta.perPage + 1
  const to = Math.min(meta.currentPage * meta.perPage, meta.total)

  return (
    <div className='mt-4 flex flex-wrap items-center justify-between gap-3'>
      <p className='text-sm text-muted-foreground'>
        Showing {from}–{to} of {meta.total}
      </p>
      <div className='flex flex-wrap items-center gap-1'>
        <Button
          variant='outline'
          size='sm'
          disabled={meta.currentPage <= 1}
          onClick={() => onPageChange(meta.currentPage - 1)}
        >
          Previous
        </Button>
        {pages.map((page, index) => {
          const previous = pages[index - 1]
          const showGap = previous !== undefined && page - previous > 1

          return (
            <span key={page} className='flex items-center gap-1'>
              {showGap ? (
                <span className='px-1 text-sm text-muted-foreground'>…</span>
              ) : null}
              <Button
                variant={page === meta.currentPage ? 'default' : 'outline'}
                size='sm'
                onClick={() => onPageChange(page)}
              >
                {page}
              </Button>
            </span>
          )
        })}
        <Button
          variant='outline'
          size='sm'
          disabled={meta.currentPage >= meta.lastPage}
          onClick={() => onPageChange(meta.currentPage + 1)}
        >
          Next
        </Button>
      </div>
    </div>
  )
}
