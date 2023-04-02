import { FunctionalComponent } from 'preact'
import { Layout } from './layout'

type Props = {
  /**
   * The URL to the encrypted message
   */
  url: string
}

export const OneTimeLinkConfirmationPage: FunctionalComponent<Props> = ({ url }) => {
  return (
    <Layout>
      <h2>You successfully encrypted a message</h2>
      <p>
        We generated a link for you to share. The link can only be used once, and it will only work
        for the next 30 minutes. If you use the link yourself, your recipient will not be able to
        use it.
      </p>

      <div
        className="space-y-4"
        x-data={`{
            url: "${url}",
            showConfirmation: false,
            copy() {
              navigator.clipboard.writeText(this.url);
              this.showConfirmation = true;
              setTimeout(() => {
                this.showConfirmation = false;
              }, 3000)
            }
        }`}
      >
        <div className="card bg-base-300 shadow-xl w-full">
          <div className="card-body overflow-scroll">{url}</div>
        </div>

        <button className="btn btn-primary w-full" x-on:click="copy()">
          Copy Link
        </button>

        <div x-show="showConfirmation" x-transition class="alert alert-success shadow-lg">
          <div>
            <span>📋 Copied to clipboard</span>
          </div>
        </div>
      </div>
    </Layout>
  )
}
