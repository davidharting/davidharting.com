import type { HttpContextContract } from '@ioc:Adonis/Core/HttpContext'
import type { VNode, FunctionComponent } from 'preact'

import { render as preactRenderToString } from 'preact-render-to-string'

export default class HomeController {
  public async show(ctx: HttpContextContract) {
    return render(ctx, <HomePage />)
  }
}

const HomePage: FunctionComponent = () => {
  return (
    <div>
      <h1>👋 Hey, I am David Harting!</h1>
    </div>
  )
}

const render = (ctx: HttpContextContract, component: VNode) => {
  return ctx.view.render('layouts/app', { contents: preactRenderToString(component) })
}
