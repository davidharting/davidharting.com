import { FunctionComponent } from 'preact'
import { AppLayout } from 'App/pages/app_layout'

export const Layout: FunctionComponent = ({ children }) => {
  return (
    <AppLayout>
      <div className="text-sm breadcrumbs">
        <ul>
          <li>
            <a href={'/'}>Home</a>
          </li>
          <li>
            <a href={'/1tl/new'}>One Time Links</a>
          </li>
        </ul>
      </div>
      <div className="prose">
        <h1>One-Time Links</h1>
        {children}
      </div>
    </AppLayout>
  )
}
