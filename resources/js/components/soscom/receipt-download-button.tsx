import { useState } from 'react'
import { Download } from 'lucide-react'
import { toast } from 'sonner'
import { fetchPurchaseReceipt } from '@/lib/credits'
import { downloadReceiptPdf } from '@/lib/download-receipt-pdf'
import { Button } from '@/components/ui/button'

export function ReceiptDownloadButton({
  paymentId,
  transactionId,
  admin = false,
  size = 'sm',
  className,
  label = 'Receipt',
}: {
  paymentId?: number | null
  transactionId?: number | null
  admin?: boolean
  size?: 'sm' | 'default'
  className?: string
  label?: string
}) {
  const [downloading, setDownloading] = useState(false)
  const ready = paymentId != null || transactionId != null

  async function download() {
    if (!ready || downloading) {
      return
    }

    setDownloading(true)
    try {
      const receipt = await fetchPurchaseReceipt({
        paymentId,
        transactionId,
        admin,
      })
      await downloadReceiptPdf(receipt)
    } catch {
      toast.error('Could not download the receipt.')
    } finally {
      setDownloading(false)
    }
  }

  return (
    <Button
      type='button'
      variant='outline'
      size={size}
      className={className}
      disabled={!ready || downloading}
      onClick={() => void download()}
    >
      <Download />
      {downloading ? 'Preparing…' : label}
    </Button>
  )
}
