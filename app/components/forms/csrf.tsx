import { ComponentContext } from 'App/pages'

export const CsrfInput = (_props, ctx: ComponentContext) => {
  return <input type="hidden" name="_csrf" value={ctx.csrfToken} />
}
