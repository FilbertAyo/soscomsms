import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { fetchIdentityDocument } from '@/lib/sender-ids'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

const PDF_TYPE = 'application/pdf'

export function IdentityDocumentActions({
  requestId,
  fileName,
  admin = false,
}: {
  requestId: number
  fileName: string | null
  admin?: boolean
}) {
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState<'view' | 'download' | null>(null)
  const [url, setUrl] = useState<string | null>(null)
  const downloadName = fileName?.trim() || 'identity-document.pdf'

  useEffect(() => {
    return () => {
      if (url) {
        URL.revokeObjectURL(url)
      }
    }
  }, [url])

  async function loadPdf(): Promise<string | null> {
    if (url) {
      return url
    }

    const blob = await fetchIdentityDocument(requestId, admin)
    if (blob.type.includes('json') || blob.type.startsWith('text/')) {
      toast.error('Could not open the identity document.')
      return null
    }

    const pdf =
      blob.type === PDF_TYPE ? blob : new Blob([blob], { type: PDF_TYPE })
    const next = URL.createObjectURL(pdf)
    setUrl(next)
    return next
  }

  async function view() {
    setLoading('view')
    try {
      const next = await loadPdf()
      if (next) {
        setOpen(true)
      }
    } catch {
      toast.error('Could not open the identity document.')
    } finally {
      setLoading(null)
    }
  }

  async function download() {
    setLoading('download')
    try {
      const next = await loadPdf()
      if (!next) {
        return
      }
      const link = document.createElement('a')
      link.href = next
      link.download = downloadName
      link.click()
    } catch {
      toast.error('Could not download the identity document.')
    } finally {
      setLoading(null)
    }
  }

  return (
    <>
      <div className='flex flex-wrap gap-2'>
        <Button
          type='button'
          variant='outline'
          size='sm'
          disabled={loading !== null}
          onClick={() => void view()}
        >
          {loading === 'view' ? 'Opening…' : 'View document'}
        </Button>
        <Button
          type='button'
          variant='outline'
          size='sm'
          disabled={loading !== null}
          onClick={() => void download()}
        >
          {loading === 'download' ? 'Downloading…' : 'Download'}
        </Button>
      </div>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className='sm:max-w-3xl'>
          <DialogHeader>
            <DialogTitle>{downloadName}</DialogTitle>
          </DialogHeader>
          {url ? (
            <iframe
              title={downloadName}
              src={url}
              className='h-[70vh] w-full rounded-md border bg-muted'
            />
          ) : null}
          <DialogFooter>
            <Button
              type='button'
              variant='outline'
              disabled={loading !== null}
              onClick={() => void download()}
            >
              Download
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
