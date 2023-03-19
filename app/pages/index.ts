import type { HttpContextContract } from '@ioc:Adonis/Core/HttpContext'
import type { VNode } from 'preact'
import { render as preactRenderToString } from 'preact-render-to-string'

export const render = (ctx: HttpContextContract, component: VNode) => {
  return ctx.view.render('layouts/app', { contents: preactRenderToString(component) })
}
