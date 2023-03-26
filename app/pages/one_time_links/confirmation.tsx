import { FunctionalComponent } from 'preact'
import { AppLayout } from '../app_layout'

type Props = {
  /**
   * The URL to the encrypted message
   */
  url: string
}

export const OneTimeLinkConfirmationPage: FunctionalComponent<Props> = ({ url }) => {
  return (
    <AppLayout>
      <h1>You successfully encrypted a message</h1>
      <p>
        We generated a link for you to share. The link can only be used once, and it will only work
        for the next 30 minutes. If you use the link yourself, your recipient will not be able to
        use it.
      </p>

      <pre>{url}</pre>
    </AppLayout>
  )
}
