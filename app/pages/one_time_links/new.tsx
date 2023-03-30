import { FunctionComponent } from 'preact'
import { CsrfInput } from 'App/components/forms/csrf'
import { Layout } from './layout'
import { ComponentContext } from '..'

export const NewOneTimeLinkPage: FunctionComponent = (_props, ctx: ComponentContext) => {
  return (
    <Layout>
      <p>
        Generate a one-time link to a message. We encrypt the message in our database. The link will
        only be valid for 30 minutes. The message will be destroyed after reading.
      </p>

      <form className="space-y-4" action="/1tl/new" method="post">
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
            value={ctx.flash.get('message', '')}
          ></textarea>
          {ctx.flash.has('errors.message') && (
            <div className="mt-2 text-sm text-error">{ctx.flash.get('errors.message')}</div>
          )}
        </div>
        <div className="form-control">
          <input type="submit" className="btn btn-primary" value="Create" />
        </div>
      </form>
    </Layout>
  )
}
