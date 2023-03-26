import { FunctionComponent } from 'preact'
import { AppLayout } from 'App/pages/app_layout'
import { CsrfInput } from 'App/components/forms/csrf'

export const NewOneTimeLinkPage: FunctionComponent = () => {
  return (
    <AppLayout>
      <div className="prose">
        <h1>One-Time Links</h1>
        <p>
          Generate a one-time link to a message. We encrypt the message in our database. The link
          will only be valid for 30 minutes. The message will be destroyed after reading.
        </p>

        <form className="space-y-4" method="post">
          <CsrfInput />
          <div className="form-control">
            <label className="label" htmlFor="message">
              <span className="label-text">Your secret message</span>
            </label>
            <textarea
              className="textarea textarea-bordered h-24"
              name="message"
              placeholder="Secret information"
              required
            ></textarea>
          </div>
          <div className="form-control">
            <input type="submit" className="btn btn-primary" value="Create" />
          </div>
        </form>
      </div>
    </AppLayout>
  )
}
