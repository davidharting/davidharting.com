import crypto from 'node:crypto'
import type { HttpContextContract } from '@ioc:Adonis/Core/HttpContext'
import Route from '@ioc:Adonis/Core/Route'
import { schema, rules } from '@ioc:Adonis/Core/Validator'
import OneTimeLink from 'App/Models/OneTimeLink'
import { render } from 'App/pages'
import { OneTimeLinkConfirmationPage } from 'App/pages/one_time_links/confirmation'
import { NewOneTimeLinkPage } from 'App/pages/one_time_links/new'
import { ShowOneTimeLinkPage } from 'App/pages/one_time_links/show'
import Encryption from '@ioc:Adonis/Core/Encryption'

export default class OneTimeLinksController {
  public async show(ctx: HttpContextContract) {
    if (!ctx.request.hasValidSignature()) {
      return ctx.view.render('errors/not-found.edge')
    }

    const id = ctx.params.id
    const oneTimeLink = await OneTimeLink.findBy('id', id)
    if (!oneTimeLink) {
      return ctx.view.render('errors/not-found.edge')
    }
    const message = Encryption.decrypt(oneTimeLink.encryptedMessage)
    if (typeof message !== 'string') {
      throw Error('decryption bad')
    }

    await oneTimeLink.delete()

    return render(ctx, <ShowOneTimeLinkPage message={message} />)
  }

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

    // TODO: Can i use APP_URL to make absolute URLs easier?

    const id = crypto.randomUUID()
    const encryptedMessage = Encryption.encrypt(data.message, '30m')
    const signedUrl = Route.makeSignedUrl(
      'showOneTimeLink',
      { id },
      { prefixUrl: `${ctx.request.protocol()}://${ctx.request.hostname()}`, expiresIn: '30m' }
    )
    const oneTimeLink = await OneTimeLink.create({
      id,
      signedUrl,
      encryptedMessage,
    })

    return render(ctx, <OneTimeLinkConfirmationPage url={oneTimeLink.signedUrl} />)
  }
}
