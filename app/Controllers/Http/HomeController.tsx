import type { HttpContextContract } from '@ioc:Adonis/Core/HttpContext'

import { render } from 'App/pages'
import { HomePage } from 'App/pages/home_page'

export default class HomeController {
  public async show(ctx: HttpContextContract) {
    return render(ctx, <HomePage />)
  }
}
