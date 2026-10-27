import { useNavigate, useRouter } from '@tanstack/react-router'
import { Button } from '@/components/ui/button'

type FriendlyErrorProps = {
  title: string
  description: string
  homeTo?: '/' | '/dashboard'
  homeLabel?: string
}

export function FriendlyError({
  title,
  description,
  homeTo = '/',
  homeLabel = 'Back to Home',
}: FriendlyErrorProps) {
  const navigate = useNavigate()
  const { history } = useRouter()

  return (
    <div className='h-svh w-full'>
      <div className='m-auto flex h-full w-full max-w-md flex-col items-center justify-center gap-2 px-6 text-center'>
        <h1 className='text-xl font-semibold tracking-tight'>{title}</h1>
        <p className='text-sm text-muted-foreground'>{description}</p>
        <div className='mt-6 flex flex-wrap justify-center gap-3'>
          <Button variant='outline' onClick={() => history.go(-1)}>
            Go back
          </Button>
          <Button variant='outline' onClick={() => window.location.reload()}>
            Try again
          </Button>
          <Button onClick={() => navigate({ to: homeTo })}>{homeLabel}</Button>
        </div>
      </div>
    </div>
  )
}
