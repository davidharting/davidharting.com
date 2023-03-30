import { FunctionComponent } from 'preact'
import { Layout } from './layout'

type Props = {
  message: string
}

export const ShowOneTimeLinkPage: FunctionComponent<Props> = ({ message }) => {
  return (
    <Layout>
      <h2 className="text-2xl">Someone Shared an Encrypted Message with You</h2>
      <p>
        You are visiting a one-time link, which is an (arguably) secure way to share sensitive
        information. The data was encrypted on our servers, and decrypted for you to see it. This
        URL only works one time. The message will be deleted off of our servers.
      </p>

      <div className="card bg-base-100 shadow-xl">
        <div className="card-body">{message}</div>
      </div>
    </Layout>
  )
}
