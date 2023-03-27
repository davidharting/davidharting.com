import type { HttpContextContract } from '@ioc:Adonis/Core/HttpContext'
import Logger from '@ioc:Adonis/Core/Logger'
import { schema, rules } from '@ioc:Adonis/Core/Validator'
import OneTimeLink from 'App/Models/OneTimeLink'

import { render } from 'App/pages'
import { OneTimeLinkConfirmationPage } from 'App/pages/one_time_links/confirmation'
import { NewOneTimeLinkPage } from 'App/pages/one_time_links/new'

export default class OneTimeLinksController {
  public async new(ctx: HttpContextContract) {
    return render(ctx, <NewOneTimeLinkPage />)
  }

  public async create(ctx: HttpContextContract) {
    const maxMessageLength = 1000
    const validationSchema = schema.create({
      message: schema.string({ trim: true }, [
        rules.required(),
        rules.minLength(1),
        rules.maxLength(maxMessageLength),
      ]),
    })

    const data = await ctx.request.validate({
      schema: validationSchema,
      messages: {
        'message.maxLength': `Must be less than ${maxMessageLength} characters.`,
      },
    })

    const oneTimeLink = await OneTimeLink.create({
      signedUrl: 'www.cool.gov',
      encryptedMessage: data.message,
    })

    Logger.info({ data }, 'received message')

    return render(ctx, <OneTimeLinkConfirmationPage url={oneTimeLink.signedUrl} />)
  }
}
