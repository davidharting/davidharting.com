import type { HttpContextContract } from '@ioc:Adonis/Core/HttpContext'
import type { VNode } from 'preact'
import { render as preactRenderToString } from 'preact-render-to-string'

/**
 * TODO: This needs a new home
 */
export interface ComponentContext {
  csrfToken: string
}

export const render = (ctx: HttpContextContract, component: VNode) => {
  const preactCtx: ComponentContext = {
    csrfToken: ctx.request.csrfToken,
  }
  console.log('csrfToken', ctx.request.csrfToken)
  return ctx.view.render('layouts/app', { contents: preactRenderToString(component, preactCtx) })
}
