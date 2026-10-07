import { IdentityDocumentActions } from '@/features/sender-ids/identity-document'
import {
  identityDocumentTypeLabel,
  senderIdUseCaseLabel,
  type SenderIdRequestRecord,
} from '@/types/sender-ids'

export function SenderIdRequestFacts({
  request,
  admin = false,
}: {
  request: SenderIdRequestRecord
  admin?: boolean
}) {
  return (
    <dl className='mt-4 space-y-3 text-sm'>
      <Fact label='Purpose' value={senderIdUseCaseLabel(request.purpose)} />
      {request.description ? (
        <Fact label='Description' value={request.description} />
      ) : null}
      {request.sampleContent ? (
        <Fact label='Sample SMS' value={request.sampleContent} />
      ) : null}
      {request.identityDocumentType ? (
        <div>
          <dt className='text-xs font-medium text-muted-foreground'>
            Identity document
          </dt>
          <dd className='mt-1'>
            {identityDocumentTypeLabel(request.identityDocumentType)}
            {request.identityDocumentName
              ? ` · ${request.identityDocumentName}`
              : ''}
          </dd>
          {request.hasIdentityDocument ? (
            <div className='mt-2'>
              <IdentityDocumentActions
                key={request.id}
                requestId={request.id}
                fileName={request.identityDocumentName}
                admin={admin}
              />
            </div>
          ) : null}
        </div>
      ) : null}
    </dl>
  )
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className='text-xs font-medium text-muted-foreground'>{label}</dt>
      <dd className='mt-1 whitespace-pre-wrap'>{value}</dd>
    </div>
  )
}
