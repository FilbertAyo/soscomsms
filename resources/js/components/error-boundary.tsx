import { Component, type ErrorInfo, type ReactNode } from 'react'
import { logClientError } from '@/lib/log-client-error'

type Props = {
  children: ReactNode
}

type State = {
  failed: boolean
}

export class ErrorBoundary extends Component<Props, State> {
  state: State = { failed: false }

  static getDerivedStateFromError(): State {
    return { failed: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    logClientError(error, {
      source: 'react-render',
      componentStack: info.componentStack,
    })
  }

  render(): ReactNode {
    if (this.state.failed) {
      return (
        <div className='h-svh w-full'>
          <div className='m-auto flex h-full w-full max-w-md flex-col items-center justify-center gap-2 px-6 text-center'>
            <h1 className='text-xl font-semibold tracking-tight'>
              Something went wrong
            </h1>
            <p className='text-sm text-muted-foreground'>
              We could not finish that. Try again in a moment. If it keeps
              happening, contact Soscom.
            </p>
            <div className='mt-6 flex flex-wrap justify-center gap-3'>
              <button
                type='button'
                className='inline-flex h-9 items-center rounded-md border bg-background px-4 text-sm font-medium'
                onClick={() => window.location.reload()}
              >
                Try again
              </button>
              <a
                href='/dashboard'
                className='inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground'
              >
                Back to dashboard
              </a>
            </div>
          </div>
        </div>
      )
    }

    return this.props.children
  }
}
