import type { FunctionComponent } from 'preact'

export const AppLayout: FunctionComponent = ({ children }) => {
  return <div className="antialiased mx-4 sm:mx-auto max-w-2xl my-16">{children}</div>
}
