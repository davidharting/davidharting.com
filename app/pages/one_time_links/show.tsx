import { FunctionComponent } from 'preact'
import { AppLayout } from 'App/pages/app_layout'

type Props = {
  message: string
}

export const ShowOneTimeLinkPage: FunctionComponent<Props> = ({ message }) => {
  return (
    <AppLayout>
      <div className="prose">
        <h1>Someone Shared an Encrypted Message with You</h1>
        <p>
          You are visiting a one-time link, which is an (arguably) secure way to share sensitive
          information. The data was encrypted on our servers, and decrypted for you to see it. This
          URL only works one time. The message will be deleted off of our servers.
        </p>

        <div className="card bg-base-100 shadow-xl">
          <div className="card-body">{message}</div>
        </div>
      </div>
    </AppLayout>
  )
}
