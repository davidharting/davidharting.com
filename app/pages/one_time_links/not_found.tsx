import { FunctionComponent } from 'preact'
import { Layout } from './layout'

export const NotFoundPage: FunctionComponent = () => {
  return (
    <Layout>
      <h2>Link Not Found</h2>
      <p>The link you have is expired or invalid.</p>
    </Layout>
  )
}
