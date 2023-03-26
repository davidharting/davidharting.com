import type { HttpContextContract } from '@ioc:Adonis/Core/HttpContext'
import Logger from '@ioc:Adonis/Core/Logger'
import { schema, rules } from '@ioc:Adonis/Core/Validator'

import { render } from 'App/pages'
import { OneTimeLinkConfirmationPage } from 'App/pages/one_time_links/confirmation'
import { NewOneTimeLinkPage } from 'App/pages/one_time_links/new'

export default class OneTimeLinksController {
  public async new(ctx: HttpContextContract) {
    return render(ctx, <NewOneTimeLinkPage />)
  }

  public async create(ctx: HttpContextContract) {
    const validationSchema = schema.create({
      message: schema.string({ trim: true }, [
        rules.required(),
        rules.minLength(1),
        rules.maxLength(1000),
      ]),
    })

    const data = await ctx.request.validate({
      schema: validationSchema,
    })

    Logger.info({ data }, 'Recieved some data!')
    ctx.response.status(201)
    return render(ctx, <OneTimeLinkConfirmationPage url="https://www.davidharting.com" />)
  }
}
